<?php

declare(strict_types=1);

namespace Lenga\Pong\Scripts;

use Lenga\Engine\Attributes\Header;
use Lenga\Engine\Attributes\Range;
use Lenga\Engine\Attributes\SerializeField;
use Lenga\Engine\Core\Application;
use Lenga\Engine\Audio\AudioSource;
use Lenga\Engine\Core\Behaviour;
use Lenga\Engine\Core\GameObject;
use Lenga\Engine\Core\Input;
use Lenga\Engine\Core\Time;
use Lenga\Engine\Core\WaitForSeconds;
use Lenga\Engine\Enumerations\KeyCode;
use Lenga\Engine\SceneManagement\Scene;
use Lenga\Engine\UI\Button;
use Lenga\Engine\UI\Canvas;
use Lenga\Engine\UI\Text;
use Lenga\Pong\Scripts\Enumerations\DifficultyLevel;

final class LevelController extends Behaviour
{
    public ?GameObject $ball = null;
    public ?GameObject $aiPaddle = null;
    public DifficultyLevel $difficulty = DifficultyLevel::MEDIUM;
    #[Range(3, 10)]
    public float $startCountdownSeconds = 3.0;
    public ?Canvas $pauseMenu = null;
    public ?Canvas $gameOverMenu = null;
    public ?Button $resumeButton = null;
    public ?Button $playAgainButton = null;
    public ?Button $quitButton = null;
    private bool $isPlaying = false;
    private bool $roundTransitionActive = false;
    private bool $hasPlayedOpeningCountdown = false;
    private bool $isGameOver = false;
    #[Header("Scoring")]
    private int $playerOneScore = 0;
    private int $playerTwoScore = 0;
    #[SerializeField]
    private int $targetScore = 10;
    private ?AudioSource $audio = null;
    private ?Ball $ballBehaviour = null;
    private ?Paddle $aiPaddleBehaviour = null;
    private ?Text $playerOneScoreText = null;
    private ?Text $playerTwoScoreText = null;
    private ?Text $countdownText = null;

    public function start(): void
    {
        if ($scene = Scene::getActive()) {
            if ($this->pauseMenu = $scene->findCanvas("Pause Menu") ) {
                $this->resumeButton = $this->pauseMenu->findButtonByName("Resume Button");
                $this->quitButton = $this->pauseMenu->findButtonByName("Quit Button");
                $this->pauseMenu->enabled = false;
            }
            if ($this->gameOverMenu = $scene->findCanvas("Game Over Menu") ) {
                $this->playAgainButton = $this->gameOverMenu->findButtonByName("Play Again Button");
                $this->gameOverMenu->enabled = false;
            }
        }
        $this->audio = $this->gameObject->getComponent(AudioSource::class);
        $this->audio?->play();

        $ballBehaviour = $this->ball?->getComponent(Ball::class);
        $this->ballBehaviour = $ballBehaviour instanceof Ball ? $ballBehaviour : null;
        $this->ballBehaviour?->resetToSpawn(true);

        $aiPaddleBehaviour = $this->aiPaddle?->getComponent(Paddle::class);
        $this->aiPaddleBehaviour = $aiPaddleBehaviour instanceof Paddle ? $aiPaddleBehaviour : null;
        $this->applyAiDifficultyTuning();

        $this->resolveHudReferences();
        $this->syncScoreHud();
        $this->hideCountdown();

        $this->startCoroutine($this->runOpeningSequence());
    }

    private function applyAiDifficultyTuning(): void
    {
        $paddle = $this->aiPaddleBehaviour;
        if (!$paddle instanceof Paddle || !$paddle->isAiDriven) {
            return;
        }

        $tuning = $this->getAiDifficultyTuning();
        $paddle->aiReactionRate = $tuning['reactionRate'];
        $paddle->aiDeadZone = $tuning['deadZone'];
        $paddle->aiErrorMargin = $tuning['errorMargin'];
    }

    /**
     * @return array{reactionRate: float, deadZone: float, errorMargin: float}
     */
    private function getAiDifficultyTuning(): array
    {
        return match ($this->difficulty) {
            DifficultyLevel::EASY => [
                'reactionRate' => 4.5,
                'deadZone' => 0.75,
                'errorMargin' => 1.6,
            ],
            DifficultyLevel::HARD => [
                'reactionRate' => 10.5,
                'deadZone' => 0.12,
                'errorMargin' => 0.25,
            ],
            default => [
                'reactionRate' => 7.0,
                'deadZone' => 0.35,
                'errorMargin' => 0.9,
            ],
        };
    }

    private function resolveHudReferences(): void
    {
        $scene = Scene::getActive();
        $canvas = $scene?->findCanvas('HUD');
        if (!$canvas instanceof Canvas) {
            $canvases = $scene?->getCanvases() ?? [];
            $canvas = $canvases[0] ?? null;
        }

        if (!$canvas instanceof Canvas) {
            return;
        }

        $playerOneScoreText = $canvas->findTextByName('Player 1 Score');
        $playerTwoScoreText = $canvas->findTextByName('Player 2 Score');
        $countdownText = $canvas->findTextByName('Start Countdown');

        $this->playerOneScoreText = $playerOneScoreText instanceof Text ? $playerOneScoreText : null;
        $this->playerTwoScoreText = $playerTwoScoreText instanceof Text ? $playerTwoScoreText : null;
        $this->countdownText = $countdownText instanceof Text ? $countdownText : null;
    }

    private function syncScoreHud(): void
    {
        if ($this->playerOneScoreText instanceof Text) {
            $this->playerOneScoreText->text = (string)$this->playerOneScore;
        }

        if ($this->playerTwoScoreText instanceof Text) {
            $this->playerTwoScoreText->text = (string)$this->playerTwoScore;
        }
    }

    private function hideCountdown(): void
    {
        if ($this->countdownText instanceof Text) {
            $this->countdownText->visible = false;
        }
    }

    private function runOpeningSequence(): \Generator
    {
        $this->roundTransitionActive = true;

        if (!$this->hasPlayedOpeningCountdown) {
            $this->hasPlayedOpeningCountdown = true;
            yield from $this->playOpeningCountdown();
        }

        $this->launchRound();
    }

    private function playOpeningCountdown(): \Generator
    {
        $remaining = max(0.0, $this->startCountdownSeconds);

        if (!$this->countdownText instanceof Text) {
            if ($remaining > 0.0) {
                yield new WaitForSeconds($remaining);
            }
            return;
        }

        $this->countdownText->visible = true;

        while ($remaining > 0.0) {
            $this->countdownText->text = (string)max(1, (int)ceil($remaining));
            $step = min(1.0, $remaining);
            yield new WaitForSeconds($step);
            $remaining -= $step;
        }

        $this->hideCountdown();
    }

    private function launchRound(): void
    {
        $this->applyAiDifficultyTuning();
        $this->roundTransitionActive = false;
        $this->isPlaying = true;
        $this->ballBehaviour?->launch($this->getBallSpeedScale());
    }

    private function getBallSpeedScale(): float
    {
        return match ($this->difficulty) {
            DifficultyLevel::EASY => 0.85,
            DifficultyLevel::HARD => 1.25,
            default => 1.0,
        };
    }

    public function update(): void
    {
        if ($this->isGameOver) {
            $shouldRestart = ($this->playAgainButton?->clicked ?? false)
                || Input::getKeyDown(KeyCode::R)
                || Input::getKeyDown(KeyCode::ENTER);

            if ($shouldRestart) {
                $this->restartGame();
            }

            return;
        }

        if (Input::getButtonDown("Pause")) {
            $this->updatePauseMenu();
        }

        if ($this->resumeButton?->clicked) {
            $this->updatePauseMenu();
        }

        if ($this->quitButton?->clicked) {
            Application::quit();
        }
    }

    public function updatePauseMenu(): void
    {
        Time::toggleGameplayPause();

        if ($this->pauseMenu instanceof Canvas) {
            $this->pauseMenu->enabled = Time::isGameplayPaused();
        }
    }

    public function scorePoint(int $player): void
    {
        if ($this->roundTransitionActive || !$this->isPlaying) {
            return;
        }

        $this->roundTransitionActive = true;
        $this->isPlaying = false;

        if ($player === 1) {
            ++$this->playerOneScore;
        } else {
            ++$this->playerTwoScore;
        }

        $this->syncScoreHud();

        $hasWinner = $this->playerOneScore >= $this->targetScore
            || $this->playerTwoScore >= $this->targetScore;

        if ($hasWinner) {
            $this->winTheGame($player);
            return;
        }

        $this->ballBehaviour?->resetToSpawn(true);
        $this->hideCountdown();
        $this->startCoroutine($this->restartRoundAfterDelay());
    }

    private function winTheGame(int $player): void
    {
        $this->isGameOver = true;
        $this->roundTransitionActive = true;
        $this->isPlaying = false;
        $this->stopAllCoroutines();
        $this->ballBehaviour?->resetToSpawn(true);
        $this->hideCountdown();

        if ($this->pauseMenu instanceof Canvas) {
            $this->pauseMenu->enabled = false;
        }

        if ($this->gameOverMenu instanceof Canvas) {
            $this->gameOverMenu->enabled = true;
            if ($winningMessage = $this->gameOverMenu->findTextByName('Winner Message')) {
                $winningMessage->text = "Player $player wins!";
            }
        }
    }

    private function restartGame(): void
    {
        $this->stopAllCoroutines();

        $this->isGameOver = false;
        $this->isPlaying = false;
        $this->roundTransitionActive = false;
        $this->hasPlayedOpeningCountdown = false;
        $this->playerOneScore = 0;
        $this->playerTwoScore = 0;

        $this->syncScoreHud();
        $this->hideCountdown();
        $this->ballBehaviour?->resetToSpawn(true);

        if ($this->gameOverMenu instanceof Canvas) {
            $this->gameOverMenu->enabled = false;
        }

        if ($this->pauseMenu instanceof Canvas) {
            $this->pauseMenu->enabled = false;
        }

        if (Time::isGameplayPaused()) {
            Time::resumeGameplay();
        }

        $this->startCoroutine($this->runOpeningSequence());
    }

    private function restartRoundAfterDelay(): \Generator
    {
        yield new WaitForSeconds($this->getRestartDelaySeconds());
        $this->launchRound();
    }

    private function getRestartDelaySeconds(): float
    {
        return match ($this->difficulty) {
            DifficultyLevel::EASY => 2.0,
            DifficultyLevel::HARD => 0.75,
            default => 1.25,
        };
    }
}
