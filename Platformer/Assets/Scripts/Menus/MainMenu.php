<?php

declare(strict_types=1);

namespace Lenga\Platformer\Scripts\Menus;

use Lenga\Engine\Attributes\AddComponentMenu;
use Lenga\Engine\Attributes\Header;
use Lenga\Engine\Attributes\Range;
use Lenga\Engine\Attributes\Tooltip;
use Lenga\Engine\Core\Application;
use Lenga\Engine\Audio\AudioSource;
use Lenga\Engine\Core\Behaviour;
use Lenga\Engine\Core\Debug;
use Lenga\Engine\Core\Vector2;
use Lenga\Engine\SceneManagement\Scene;
use Lenga\Engine\SceneManagement\SceneManager;
use Lenga\Engine\Tweening\EasingFunction;
use Lenga\Engine\Tweening\Tween;
use Lenga\Engine\Tweening\TweenHandle;
use Lenga\Engine\Tweening\TweenOptions;
use Lenga\Engine\UI\Button;
use Lenga\Engine\UI\Canvas;
use Lenga\Engine\UI\Slider;
use Lenga\Engine\UI\UIElement;
use Lenga\Platformer\Scripts\Audio\AudioPreferences;
use Lenga\Platformer\Scripts\Menus\Interfaces\MainMenuStateInterface;
use Lenga\Platformer\Scripts\Menus\States\AudioSettingsMenuState;
use Lenga\Platformer\Scripts\Menus\States\SettingsMenuState;
use Lenga\Platformer\Scripts\Menus\States\TitleMenuState;

#[AddComponentMenu("Menus/MainMenu")]
final class MainMenu extends Behaviour
{
    protected ?MainMenuStateInterface $state = null;

    #[Header("Menu Animation")]
    #[Tooltip("Horizontal offset used when menu items slide into their resting position.")]
    #[Range(-240.0, 240.0)]
    public float $menuEnterOffsetX = -44.0;

    #[Tooltip("Delay added between each menu element during the entrance animation.")]
    #[Range(0.0, 0.25)]
    public float $menuEnterStagger = 0.045;

    #[Tooltip("Duration of the entrance tween for each menu element.")]
    #[Range(0.05, 2.0)]
    public float $menuEnterDuration = 0.34;

    #[Tooltip("Starting scale multiplier applied before each menu element animates in.")]
    #[Range(0.5, 1.5)]
    public float $menuEnterStartScale = 0.94;

    #[Tooltip("Starting rotation offset, in degrees, applied before each menu element animates in.")]
    #[Range(0.0, 15.0)]
    public float $menuEnterRotationDegrees = 1.75;

    #[Tooltip("Easing function used by menu entrance tweens.")]
    public EasingFunction $menuEnterEasingFunction = EasingFunction::EaseOutCubic;

    #[Header("Control Focus Animation")]
    #[Tooltip("Scale multiplier applied while a focusable menu control has keyboard or gamepad focus.")]
    #[Range(1.0, 1.5)]
    public float $controlFocusScale = 1.08;

    #[Tooltip("Duration of the tween that grows a control when it gains focus.")]
    #[Range(0.03, 0.5)]
    public float $controlFocusDuration = 0.12;

    #[Tooltip("Duration of the tween that shrinks a control back after it loses focus.")]
    #[Range(0.03, 0.5)]
    public float $controlBlurDuration = 0.10;

    #[Tooltip("Easing function used when a control grows on focus.")]
    public EasingFunction $controlFocusEasingFunction = EasingFunction::EaseOutCubic;

    #[Tooltip("Easing function used when a control returns to its resting scale.")]
    public EasingFunction $controlBlurEasingFunction = EasingFunction::EaseOutCubic;

    #[Header("Audio Settings")]
    #[Tooltip("Fallback music volume used when the player has not saved an audio preference yet.")]
    #[Range(0.0, 1.0)]
    public float $defaultBgmVolume = 1.0;

    #[Tooltip("Fallback sound effects volume used when the player has not saved an audio preference yet.")]
    #[Range(0.0, 1.0)]
    public float $defaultSfxVolume = 1.0;

    #[Header("Menu References")]
    public ?Canvas $titleMenu = null;
    public ?Canvas $settingsMenu = null;
    public ?Canvas $audioSettingsMenu = null;
    public ?Canvas $controllerSettingsMenu = null;
    public ?Canvas $creditsScreen = null;
    public ?AudioSource $bgmAudioSource = null;
    private ?MainMenuContext $context = null;
    /** @var array<int, array{position: Vector2, scale: Vector2, rotation: float}> */
    private array $uiRestStates = [];
    /** @var array<int, list<TweenHandle>> */
    private array $uiTweensByElement = [];
    /** @var array<int, TweenHandle> */
    private array $uiScaleTweensByElement = [];
    /** @var array<int, bool> */
    private array $controlFocusStates = [];

    public function start(): void
    {
        if (!$this->titleMenu && !$this->titleMenu = Scene::getActive()->findCanvas("Title Menu")) {
            Debug::warn("Can't find the Title Menu canvas.");
        }

        if (!$this->settingsMenu && !$this->settingsMenu = Scene::getActive()->findCanvas("Settings Menu")) {
            Debug::warn("Can't find the Settings Menu canvas.");
        }

        if (!$this->audioSettingsMenu && !$this->audioSettingsMenu = Scene::getActive()->findCanvas("Audio Settings Menu")) {
            Debug::warn("Can't find the Audio Settings Menu canvas.");
        }

        if (!$this->controllerSettingsMenu && !$this->controllerSettingsMenu = Scene::getActive()->findCanvas("Controller Settings Menu")) {
            Debug::warn("Can't find the Controller Settings Menu canvas.");
        }

        if (!$this->creditsScreen) {
            $this->creditsScreen = Scene::getActive()->findCanvas("Credits Screen");
        }
        if (!$this->bgmAudioSource) {
            $audioSource = $this->gameObject->getComponent(AudioSource::class);
            if ($audioSource instanceof AudioSource) {
                $this->bgmAudioSource = $audioSource;
            }
        }

        $this->context = new MainMenuContext(
            $this->titleMenu,
            $this->settingsMenu,
            $this->audioSettingsMenu,
            $this->controllerSettingsMenu,
            $this->creditsScreen,
        );
        if ($this->settingsMenu) {
            $this->settingsMenu->enabled = false;
        }
        if ($this->audioSettingsMenu) {
            $this->audioSettingsMenu->enabled = false;
        }
        if ($this->controllerSettingsMenu) {
            $this->controllerSettingsMenu->enabled = false;
        }
        if ($this->creditsScreen) {
            $this->creditsScreen->enabled = false;
        }
        $this->applyBgmVolume(AudioPreferences::getBgmVolume($this->getDefaultBgmVolume()));
        $this->setState(new TitleMenuState($this));
    }

    public function update(): void
    {
        $this->state?->update($this->context);
        $this->updateControlFocusTweens();
    }

    public function setState(MainMenuStateInterface $state): void
    {
        $this->state?->exit($this->context);
        $this->state = $state;
        $this->state->enter($this->context);
    }

    public function animateCanvasIn(?Canvas $canvas): void
    {
        if ($canvas === null) {
            return;
        }

        $index = 0;
        foreach ($this->collectCanvasElements($canvas) as $element) {
            $rest = $this->captureRestState($element);
            $elementId = $element->getId();
            $this->cancelElementTweens($elementId);

            $position = $rest['position'];
            $scale = $rest['scale'];
            $rotation = $rest['rotation'];
            $direction = $index % 2 === 0 ? -1.0 : 1.0;
            $delay = $index * $this->menuEnterStagger;
            $options = TweenOptions::make()
                ->delay($delay)
                ->ease($this->menuEnterEasingFunction)
                ->unscaled();

            $element->rectTransform->anchoredPosition = new Vector2(
                $position->x + $this->menuEnterOffsetX,
                $position->y,
            );
            $element->rectTransform->scale = new Vector2(
                $scale->x * $this->menuEnterStartScale,
                $scale->y * $this->menuEnterStartScale,
            );
            $element->rectTransform->rotation = $rotation + ($direction * $this->menuEnterRotationDegrees);

            $this->uiTweensByElement[$elementId] = [
                Tween::anchoredPositionTo(
                    $element,
                    new Vector2($position->x, $position->y),
                    $this->menuEnterDuration,
                    $options,
                ),
                Tween::uiRotateTo($element, $rotation, $this->menuEnterDuration, $options),
            ];
            $this->uiScaleTweensByElement[$elementId] = Tween::uiScaleTo(
                $element,
                new Vector2($scale->x, $scale->y),
                $this->menuEnterDuration,
                $options,
            );
            ++$index;
        }
    }

    public function restoreCanvasRestState(?Canvas $canvas): void
    {
        if ($canvas === null) {
            return;
        }

        foreach ($this->collectCanvasElements($canvas) as $element) {
            $elementId = $element->getId();
            $this->cancelElementTweens($elementId);
            unset($this->controlFocusStates[$elementId]);

            if (!isset($this->uiRestStates[$elementId])) {
                continue;
            }

            $rest = $this->uiRestStates[$elementId];
            $element->rectTransform->anchoredPosition = new Vector2(
                $rest['position']->x,
                $rest['position']->y,
            );
            $element->rectTransform->scale = new Vector2($rest['scale']->x, $rest['scale']->y);
            $element->rectTransform->rotation = $rest['rotation'];
        }
    }

    private function updateControlFocusTweens(): void
    {
        foreach ($this->collectActiveMenuCanvases() as $canvas) {
            foreach ($this->collectCanvasFocusableControls($canvas) as $control) {
                $controlId = $control->getId();
                $focused = $control->isFocused();

                if (!array_key_exists($controlId, $this->controlFocusStates)) {
                    $this->controlFocusStates[$controlId] = $focused;
                    if ($focused) {
                        $this->tweenControlFocusScale($control, true);
                    }

                    continue;
                }

                if ($this->controlFocusStates[$controlId] === $focused) {
                    continue;
                }

                $this->controlFocusStates[$controlId] = $focused;
                $this->tweenControlFocusScale($control, $focused);
            }
        }
    }

    private function tweenControlFocusScale(UIElement $control, bool $focused): void
    {
        $rest = $this->captureRestState($control);
        $controlId = $control->getId();
        $scaleMultiplier = $focused ? $this->controlFocusScale : 1.0;
        $duration = $focused ? $this->controlFocusDuration : $this->controlBlurDuration;
        $easingFunction = $focused ? $this->controlFocusEasingFunction : $this->controlBlurEasingFunction;

        $this->cancelElementScaleTween($controlId);
        $this->uiScaleTweensByElement[$controlId] = Tween::uiScaleTo(
            $control,
            new Vector2(
                $rest['scale']->x * $scaleMultiplier,
                $rest['scale']->y * $scaleMultiplier,
            ),
            $duration,
            TweenOptions::make()
                ->ease($easingFunction)
                ->unscaled(),
        );
    }

    public function onPlayButtonClicked(): void
    {
        if (!$this->state instanceof TitleMenuState) {
            return;
        }

        SceneManager::loadScene("Level1");
    }

    public function onSettingsButtonClicked(): void
    {
        if (!$this->state instanceof TitleMenuState) {
            return;
        }

        $this->setState(new SettingsMenuState($this));
    }

    public function onAudioSettingsButtonClicked(): void
    {
        if (!$this->state instanceof SettingsMenuState) {
            return;
        }

        $this->setState(new AudioSettingsMenuState($this));
    }

    public function onCloseAudioSettingsButtonClicked(): void
    {
        if (!$this->state instanceof AudioSettingsMenuState) {
            return;
        }

        $this->setState(new SettingsMenuState($this));
    }

    public function onControllerSettingsButtonClicked(): void
    {
        if (!$this->state instanceof SettingsMenuState) {
            return;
        }

        Debug::log("Controller settings are not available yet.");
    }

    public function onCreditsButtonClicked(): void
    {
        if (!$this->state instanceof SettingsMenuState) {
            return;
        }

        Debug::log("Credits screen is not available yet.");
    }

    public function onQuitButtonClicked(): void
    {
        if (!$this->state instanceof TitleMenuState) {
            return;
        }

        Application::quit();
    }

    public function onCloseSettingsButtonClicked(): void
    {
        if (!$this->state instanceof SettingsMenuState) {
            return;
        }

        $this->setState(new TitleMenuState($this));
    }

    public function getDefaultBgmVolume(): float
    {
        return AudioPreferences::normalizeVolume($this->defaultBgmVolume);
    }

    public function getDefaultSfxVolume(): float
    {
        return AudioPreferences::normalizeVolume($this->defaultSfxVolume);
    }

    public function applyBgmVolume(float $volume): void
    {
        if ($this->bgmAudioSource !== null) {
            $this->bgmAudioSource->volume = AudioPreferences::normalizeVolume($volume);
        }
    }

    /**
     * @return list<UIElement>
     */
    private function collectCanvasElements(Canvas $canvas): array
    {
        $elements = [];
        foreach ($canvas->getRootElements() as $element) {
            $this->collectElementTree($element, $elements);
        }

        return $elements;
    }

    /**
     * @return list<Canvas>
     */
    private function collectActiveMenuCanvases(): array
    {
        $canvases = [];
        $seenCanvasIds = [];
        foreach ([
            $this->titleMenu,
            $this->settingsMenu,
            $this->audioSettingsMenu,
            $this->controllerSettingsMenu,
            $this->creditsScreen,
        ] as $canvas) {
            if ($canvas === null || !$canvas->enabled) {
                continue;
            }

            $canvasId = $canvas->getId();
            if (isset($seenCanvasIds[$canvasId])) {
                continue;
            }

            $seenCanvasIds[$canvasId] = true;
            $canvases[] = $canvas;
        }

        return $canvases;
    }

    /**
     * @return list<UIElement>
     */
    private function collectCanvasFocusableControls(Canvas $canvas): array
    {
        $controls = [];
        foreach ($this->collectCanvasElements($canvas) as $element) {
            if ($element instanceof Button || $element instanceof Slider) {
                $controls[] = $element;
            }
        }

        return $controls;
    }

    /**
     * @param list<UIElement> $elements
     */
    private function collectElementTree(UIElement $element, array &$elements): void
    {
        $elements[] = $element;
        foreach ($element->getChildren() as $child) {
            $this->collectElementTree($child, $elements);
        }
    }

    /**
     * @return array{position: Vector2, scale: Vector2, rotation: float}
     */
    private function captureRestState(UIElement $element): array
    {
        $elementId = $element->getId();
        if (!isset($this->uiRestStates[$elementId])) {
            $position = $element->rectTransform->anchoredPosition;
            $scale = $element->rectTransform->scale;
            $this->uiRestStates[$elementId] = [
                'position' => new Vector2($position->x, $position->y),
                'scale' => new Vector2($scale->x, $scale->y),
                'rotation' => $element->rectTransform->rotation,
            ];
        }

        return $this->uiRestStates[$elementId];
    }

    private function cancelElementTweens(int $elementId): void
    {
        foreach ($this->uiTweensByElement[$elementId] ?? [] as $handle) {
            $handle->cancel();
        }

        unset($this->uiTweensByElement[$elementId]);
        $this->cancelElementScaleTween($elementId);
    }

    private function cancelElementScaleTween(int $elementId): void
    {
        if (isset($this->uiScaleTweensByElement[$elementId])) {
            $this->uiScaleTweensByElement[$elementId]->cancel();
            unset($this->uiScaleTweensByElement[$elementId]);
        }
    }
}
