<?php

namespace VISU\Command;

class WorldEditorCommand extends Command
{
    protected ?string $descriptionShort = 'Launch the visual world editor in your browser';

    protected $expectedArguments = [
        'host' => [
            'prefix'      => 'H',
            'longPrefix'  => 'host',
            'description' => 'Host to bind the development server to',
            'defaultValue' => '127.0.0.1',
        ],
        'port' => [
            'prefix'      => 'p',
            'longPrefix'  => 'port',
            'description' => 'Port to bind the development server to',
            'defaultValue' => 8765,
            'castTo'      => 'int',
        ],
    ];

    public function execute()
    {
        $host = $this->cli->arguments->get('host');
        $port = $this->cli->arguments->get('port');

        $worldsDir = VISU_PATH_ROOT . '/worlds';
        if (!is_dir($worldsDir)) {
            mkdir($worldsDir, 0755, true);
        }

        $distDir   = __DIR__ . '/../../resources/editor/dist';
        $routerFile = __DIR__ . '/../WorldEditor/WorldEditorRouter.php';

        if (!is_dir($distDir)) {
            $this->cli->error('Editor assets not found at: ' . realpath(__DIR__ . '/../../resources/editor/dist'));
            $this->cli->out('Run <yellow>cd editor && npm install && npm run build</yellow> first.');
            return;
        }

        $url = "http://{$host}:{$port}";

        $this->info("Starting world editor at <green>{$url}</green>");
        $this->info("Worlds directory: <yellow>{$worldsDir}</yellow>");
        $this->cli->out('Press <red>Ctrl+C</red> to stop the server.');

        // Pass config to the router via environment variables
        putenv("VISU_WORLDS_DIR={$worldsDir}");
        putenv("VISU_EDITOR_DIST={$distDir}");

        // Try to open browser
        $os = PHP_OS_FAMILY;
        if ($os === 'Darwin') {
            exec("open {$url} &");
        } elseif ($os === 'Linux') {
            exec("xdg-open {$url} > /dev/null 2>&1 &");
        } elseif ($os === 'Windows') {
            exec("start {$url}");
        }

        $cmd = sprintf(
            'VISU_WORLDS_DIR=%s VISU_EDITOR_DIST=%s php -S %s:%d %s',
            escapeshellarg($worldsDir),
            escapeshellarg($distDir),
            $host,
            $port,
            escapeshellarg($routerFile)
        );

        passthru($cmd);
    }
}
