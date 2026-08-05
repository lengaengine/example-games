<?php

declare(strict_types=1);

namespace RollerWorld\Game\Scripts\World;

use Lenga\Engine\Core\Behaviour;
use Lenga\Engine\SceneManagement\Scene;
use Lenga\Engine\UI\Canvas;
use Lenga\Engine\UI\Text;

final class ObstacleCourseManager extends Behaviour
{
    private ?Text $statusText = null;

    public function start(): void
    {
        $canvas = Scene::getActive()?->findCanvas('HUD');
        if (!$canvas instanceof Canvas) {
            return;
        }

        $statusText = $canvas->findTextByName('Course Status');
        $this->statusText = $statusText instanceof Text ? $statusText : null;

        if ($this->statusText instanceof Text) {
            $this->statusText->visible = true;
            $this->statusText->text = 'Reach the cyan finish block.';
        }
    }

    public function completeCourse(): void
    {
        if ($this->statusText instanceof Text) {
            $this->statusText->visible = true;
            $this->statusText->text = 'Course complete!';
        }
    }
}
