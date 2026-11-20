<?php

declare(strict_types=1);

namespace Blasters\Game\Scripts\Level;

use Lenga\Engine\Attributes\Header;
use Lenga\Engine\Attributes\Range;
use Lenga\Engine\Core\Behaviour;
use Lenga\Engine\Core\Camera;
use Lenga\Engine\Core\GameObject;
use Lenga\Engine\Core\Input;
use Lenga\Engine\Core\Time;
use Lenga\Engine\Core\Transform;
use Lenga\Engine\Core\Vector3;

final class LevelController extends Behaviour
{
    /**
     * The starfield layers by their scene IDs, and how fast each scrolls as
     * a share of the starfield's speed. Lenga turned the level's backdrop
     * layers into these sprites: the first is a child of the camera, the
     * others sit at the depths that give their parallax.
     */
    private const array STARFIELD_LAYER_SPEEDS = [
        'backdrop-1' => 0.20,
        'backdrop-2' => 0.45,
        'backdrop-3' => 0.75,
        'backdrop-4' => 1.00,
    ];

    private const string CAMERA_SCENE_ID = 'main-camera';

    /** World units per second, as seen on the play plane. */
    #[Header("Starfield")]
    #[Range(0.25, 4)]
    public float $scrollSpeed = 1.6667;

    #[Range(0, 1)]
    public float $reverseScrollMultiplier = 0.35;

    #[Range(1, 5)]
    public float $boostScrollMultiplier = 2.6;

    /**
     * Each layer's transform, and how far it moves in the world for every
     * unit the starfield scrolls.
     *
     * @var list<array{transform: Transform, worldUnitsPerUnit: float}>
     */
    private array $layers = [];

    public function start(): void
    {
        $camera = GameObject::findBySceneId(self::CAMERA_SCENE_ID);
        foreach (self::STARFIELD_LAYER_SPEEDS as $sceneId => $layerSpeed) {
            $layer = GameObject::findBySceneId($sceneId);
            if ($layer === null) {
                continue;
            }

            $this->layers[] = [
                'transform' => $layer->transform,
                'worldUnitsPerUnit' => $layerSpeed * self::worldUnitsPerPlaneUnit($layer, $camera),
            ];
        }
    }

    public function update(): void
    {
        $horizontal = Input::getAxisRaw("Horizontal");
        $isBoosting = Input::getAxisRaw("Boost") > 0.0;

        $scrollMultiplier = 1.0;
        if ($isBoosting) {
            $scrollMultiplier = $this->boostScrollMultiplier;
        } elseif ($horizontal < 0.0) {
            $scrollMultiplier = $this->reverseScrollMultiplier;
        }

        $scrollDistance = $this->scrollSpeed * $scrollMultiplier * Time::deltaTime();
        if ($scrollDistance <= 0.0) {
            return;
        }

        foreach ($this->layers as $layer) {
            $position = $layer['transform']->localPosition;
            $layer['transform']->localPosition = new Vector3(
                $position->x - ($scrollDistance * $layer['worldUnitsPerUnit']),
                $position->y,
                $position->z,
            );
        }
    }

    /**
     * How far a layer moves in the world to look as if it moved one unit on
     * the play plane. Under a perspective 2D camera a layer at depth is
     * drawn smaller by eye / (eye + depth), where eye is the camera's
     * distance to the play plane, so it moves that much farther. A child of
     * the camera, and any layer under an orthographic camera, moves one unit.
     */
    private static function worldUnitsPerPlaneUnit(GameObject $layer, ?GameObject $cameraObject): float
    {
        $camera = $cameraObject?->getComponent(Camera::class);
        if (!$camera instanceof Camera || $camera->projection !== 'Perspective') {
            return 1.0;
        }
        $parent = $layer->transform->parent;
        if ($parent !== null && $parent->gameObject->getInstanceId() === $cameraObject->getInstanceId()) {
            return 1.0;
        }

        $eye = $camera->orthographicSize / tan(deg2rad($camera->fieldOfView) / 2.0);
        // A camera at positive Z looks toward -Z, so depth grows toward -Z.
        $viewDirection = $cameraObject->transform->position->z > 0.0 ? -1.0 : 1.0;
        $depth = $layer->transform->position->z * $viewDirection;

        return ($eye + $depth) / $eye;
    }
}
