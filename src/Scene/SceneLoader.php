<?php

namespace VISU\Scene;

use GL\Math\GLM;
use GL\Math\Quat;
use GL\Math\Vec3;
use VISU\Component\NameComponent;
use VISU\ECS\ComponentRegistry;
use VISU\ECS\EntitiesInterface;
use VISU\Geo\Transform;

class SceneLoader implements SceneLoaderInterface
{
    public function __construct(
        private ComponentRegistry $componentRegistry,
    ) {
    }

    /**
     * Loads a scene JSON file and populates entities in the registry.
     *
     * @return array<int> List of created entity IDs.
     */
    public function loadFile(string $path, EntitiesInterface $entities): array
    {
        if (!file_exists($path)) {
            throw new \RuntimeException("Scene file not found: {$path}");
        }

        $json = file_get_contents($path);
        if ($json === false) {
            throw new \RuntimeException("Failed to read scene file: {$path}");
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new \RuntimeException("Invalid JSON in scene file: {$path}");
        }

        return $this->loadArray($data, $entities);
    }

    /**
     * Loads entities from a scene data array.
     *
     * @param array<string, mixed> $data
     * @return array<int> List of created entity IDs.
     */
    public function loadArray(array $data, EntitiesInterface $entities): array
    {
        $createdEntities = [];
        $entityDefs = $data['entities'] ?? [];

        foreach ($entityDefs as $entityDef) {
            $created = $this->loadEntity($entityDef, $entities, null);
            array_push($createdEntities, ...$created);
        }

        return $createdEntities;
    }

    /**
     * Loads a single entity definition recursively (including children).
     *
     * @param array<string, mixed> $def
     * @return array<int> All created entity IDs (parent + children).
     */
    private function loadEntity(array $def, EntitiesInterface $entities, ?int $parentEntity): array
    {
        $entity = $entities->create();
        $created = [$entity];

        // Name component
        if (isset($def['name'])) {
            $entities->attach($entity, new NameComponent((string)$def['name']));
        }

        // Transform
        $transform = $this->buildTransform($def['transform'] ?? []);
        if ($parentEntity !== null) {
            $transform->setParent($entities, $parentEntity);
        }
        $entities->attach($entity, $transform);

        // Components
        foreach ($def['components'] ?? [] as $componentDef) {
            $typeName = $componentDef['type'] ?? null;
            if ($typeName === null) {
                continue;
            }

            // Extract properties (everything except 'type')
            $properties = $componentDef;
            unset($properties['type']);

            $component = $this->componentRegistry->create($typeName, $properties);
            $entities->attach($entity, $component);
        }

        // Children (recursive)
        foreach ($def['children'] ?? [] as $childDef) {
            $childCreated = $this->loadEntity($childDef, $entities, $entity);
            array_push($created, ...$childCreated);
        }

        return $created;
    }

    /**
     * Builds a Transform from a JSON definition.
     *
     * @param array<string, mixed> $def
     */
    private function buildTransform(array $def): Transform
    {
        $transform = new Transform();

        if (isset($def['position'])) {
            $p = $def['position'];
            if (is_array($p)) {
                $transform->position = new Vec3(
                    (float)($p[0] ?? $p['x'] ?? 0),
                    (float)($p[1] ?? $p['y'] ?? 0),
                    (float)($p[2] ?? $p['z'] ?? 0),
                );
            }
        }

        if (isset($def['rotation'])) {
            $r = $def['rotation'];
            if (is_array($r)) {
                // Euler angles in degrees -> quaternion
                $q = new Quat();
                $rx = GLM::radians((float)($r[0] ?? $r['x'] ?? 0));
                $ry = GLM::radians((float)($r[1] ?? $r['y'] ?? 0));
                $rz = GLM::radians((float)($r[2] ?? $r['z'] ?? 0));
                if ($rx != 0.0) {
                    $q->rotate($rx, new Vec3(1, 0, 0));
                }
                if ($ry != 0.0) {
                    $q->rotate($ry, new Vec3(0, 1, 0));
                }
                if ($rz != 0.0) {
                    $q->rotate($rz, new Vec3(0, 0, 1));
                }
                $transform->orientation = $q;
            }
        }

        if (isset($def['scale'])) {
            $s = $def['scale'];
            if (is_array($s)) {
                $transform->scale = new Vec3(
                    (float)($s[0] ?? $s['x'] ?? 1),
                    (float)($s[1] ?? $s['y'] ?? 1),
                    (float)($s[2] ?? $s['z'] ?? 1),
                );
            }
        }

        $transform->markDirty();
        return $transform;
    }
}
