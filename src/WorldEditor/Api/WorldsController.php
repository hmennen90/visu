<?php

namespace VISU\WorldEditor\Api;

use VISU\WorldEditor\WorldFile;

class WorldsController
{
    public function __construct(private string $worldsDir) {}

    public function handle(string $method, string $path): void
    {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');

        if ($method === 'OPTIONS') {
            http_response_code(204);
            return;
        }

        // /api/config
        if ($path === '/api/config') {
            echo json_encode([
                'tileSize' => 32,
                'gridWidth' => 32,
                'gridHeight' => 32,
                'worldsDir' => $this->worldsDir,
            ]);
            return;
        }

        // /api/worlds
        if ($path === '/api/worlds' && $method === 'GET') {
            $this->listWorlds();
            return;
        }

        // /api/worlds/{name}
        if (preg_match('#^/api/worlds/([a-zA-Z0-9_\-]+)$#', $path, $m)) {
            $name = $m[1];
            match ($method) {
                'GET'    => $this->getWorld($name),
                'POST'   => $this->saveWorld($name),
                'DELETE' => $this->deleteWorld($name),
                default  => $this->error(405, 'Method not allowed'),
            };
            return;
        }

        $this->error(404, 'API endpoint not found');
    }

    private function listWorlds(): void
    {
        $worlds = [];
        if (is_dir($this->worldsDir)) {
            foreach (glob($this->worldsDir . '/*.world.json') ?: [] as $file) {
                $name = basename($file, '.world.json');
                $worlds[] = [
                    'name'     => $name,
                    'modified' => date('c', filemtime($file) ?: 0),
                    'size'     => filesize($file),
                ];
            }
        }
        echo json_encode($worlds);
    }

    private function getWorld(string $name): void
    {
        $path = $this->worldPath($name);
        if (!file_exists($path)) {
            $this->error(404, "World '{$name}' not found");
            return;
        }

        $content = file_get_contents($path);
        if ($content === false) {
            $this->error(500, 'Failed to read world file');
            return;
        }

        echo $content;
    }

    private function saveWorld(string $name): void
    {
        $body = file_get_contents('php://input');
        if ($body === false || $body === '') {
            $this->error(400, 'Empty request body');
            return;
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            $this->error(400, 'Invalid JSON body');
            return;
        }

        try {
            $world = WorldFile::fromArray($data);
            $world->save($this->worldPath($name));
            echo json_encode(['ok' => true, 'name' => $name]);
        } catch (\Throwable $e) {
            $this->error(500, $e->getMessage());
        }
    }

    private function deleteWorld(string $name): void
    {
        $path = $this->worldPath($name);
        if (!file_exists($path)) {
            $this->error(404, "World '{$name}' not found");
            return;
        }

        if (!unlink($path)) {
            $this->error(500, 'Failed to delete world file');
            return;
        }

        echo json_encode(['ok' => true]);
    }

    private function worldPath(string $name): string
    {
        return rtrim($this->worldsDir, '/') . '/' . $name . '.world.json';
    }

    private function error(int $code, string $message): void
    {
        http_response_code($code);
        echo json_encode(['error' => $message]);
    }
}
