<?php

namespace VISU\System;

use VISU\ECS\EntitiesInterface;
use VISU\ECS\SystemInterface;
use VISU\Geo\Transform;
use VISU\Graphics\Rendering\Pass\Camera2DData;
use VISU\Graphics\Rendering\RenderContext;

class Camera2DSystem implements SystemInterface
{
    /**
     * Camera 2D data, shared with SpriteBatchPass.
     */
    public Camera2DData $cameraData;

    /**
     * Entity ID that the camera follows (null = no follow).
     */
    private ?int $followTarget = null;

    /**
     * Smooth follow damping (0 = instant, 1 = no movement).
     */
    private float $followDamping = 0.1;

    /**
     * Zoom limits.
     */
    private float $minZoom = 0.1;
    private float $maxZoom = 10.0;

    /**
     * World bounds (null = no bounds).
     *
     * @var array{float, float, float, float}|null [minX, minY, maxX, maxY]
     */
    private ?array $bounds = null;

    public function __construct()
    {
        $this->cameraData = new Camera2DData();
    }

    public function register(EntitiesInterface $entities): void
    {
    }

    public function unregister(EntitiesInterface $entities): void
    {
    }

    /**
     * Set the entity for the camera to follow.
     */
    public function setFollowTarget(?int $entityId, float $damping = 0.1): void
    {
        $this->followTarget = $entityId;
        $this->followDamping = $damping;
    }

    /**
     * Set zoom level.
     */
    public function setZoom(float $zoom): void
    {
        $this->cameraData->zoom = max($this->minZoom, min($this->maxZoom, $zoom));
    }

    /**
     * Adjust zoom by delta.
     */
    public function zoom(float $delta): void
    {
        $this->setZoom($this->cameraData->zoom + $delta);
    }

    /**
     * Set zoom limits.
     */
    public function setZoomLimits(float $min, float $max): void
    {
        $this->minZoom = $min;
        $this->maxZoom = $max;
    }

    /**
     * Set world bounds to clamp camera position.
     */
    public function setBounds(float $minX, float $minY, float $maxX, float $maxY): void
    {
        $this->bounds = [$minX, $minY, $maxX, $maxY];
    }

    /**
     * Set camera position directly.
     */
    public function setPosition(float $x, float $y): void
    {
        $this->cameraData->x = $x;
        $this->cameraData->y = $y;
        $this->clampToBounds();
    }

    public function update(EntitiesInterface $entities): void
    {
        if ($this->followTarget === null) {
            return;
        }

        $transform = $entities->tryGet($this->followTarget, Transform::class);
        if ($transform === null) {
            return;
        }

        $targetX = $transform->position->x;
        $targetY = $transform->position->y;

        // Smooth follow (lerp)
        $t = 1.0 - $this->followDamping;
        $this->cameraData->x += ($targetX - $this->cameraData->x) * $t;
        $this->cameraData->y += ($targetY - $this->cameraData->y) * $t;

        $this->clampToBounds();
    }

    public function render(EntitiesInterface $entities, RenderContext $context): void
    {
    }

    private function clampToBounds(): void
    {
        if ($this->bounds === null) {
            return;
        }

        $this->cameraData->x = max($this->bounds[0], min($this->bounds[2], $this->cameraData->x));
        $this->cameraData->y = max($this->bounds[1], min($this->bounds[3], $this->cameraData->y));
    }
}
