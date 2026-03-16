<?php

namespace VISU\Build;

class GameBuilder
{
    private BuildConfig $config;
    private PharBuilder $pharBuilder;
    private StaticPhpResolver $staticPhpResolver;
    private PlatformPackager $platformPackager;

    /** @var callable|null */
    private $logger = null;

    public function __construct(BuildConfig $config)
    {
        $this->config = $config;
        $this->pharBuilder = new PharBuilder($config);
        $this->staticPhpResolver = new StaticPhpResolver();
        $this->platformPackager = new PlatformPackager($config);
    }

    /**
     * @param callable(string, string): void $logger  fn(level, message)
     */
    public function setLogger(callable $logger): void
    {
        $this->logger = $logger;
        $this->staticPhpResolver->setLogger(fn(string $msg) => $logger('info', $msg));
    }

    /**
     * Run the full build pipeline.
     *
     * @return array{outputPath: string, pharSize: int, binarySize: int, bundleSize: int}
     */
    public function build(string $platform, string $outputDir, ?string $microSfxPath = null, ?string $arch = null): array
    {
        $arch = $arch ?? StaticPhpResolver::detectArch();
        $platformOutputDir = $outputDir . '/' . $platform . '-' . $arch;

        // Clean previous build output
        if (is_dir($platformOutputDir)) {
            $this->log('info', 'Cleaning previous build...');
            $this->removeDirectory($platformOutputDir);
        }
        mkdir($platformOutputDir, 0755, true);

        $tempDir = sys_get_temp_dir() . '/visu-build-' . $this->config->name . '-' . getmypid();

        try {
            // Phase 1: Prepare vendor (install --no-dev)
            $this->log('info', 'Installing production dependencies...');
            $this->prepareVendor();

            // Phase 2: Stage sources
            $stagingDir = $tempDir . '/staging';
            $this->log('info', 'Staging sources...');
            $this->pharBuilder->stage($stagingDir);
            $fileCount = $this->countFiles($stagingDir);
            $this->log('success', "Staged {$fileCount} files");

            // Phase 3: Create PHAR
            $pharPath = $tempDir . '/' . strtolower($this->config->name) . '.phar';
            $this->log('info', 'Creating PHAR archive...');
            $this->pharBuilder->build($stagingDir, $pharPath);
            $pharSize = filesize($pharPath);
            $this->log('success', sprintf('PHAR created: %.2f MB', $pharSize / 1024 / 1024));

            // Phase 4: Resolve static PHP binary
            $this->log('info', 'Resolving micro.sfx binary...');
            $sfxPath = $this->staticPhpResolver->resolve($microSfxPath, $platform, $arch);
            $this->log('success', 'Found micro.sfx: ' . $sfxPath);

            // Phase 5: Combine executable
            $combinedPath = $tempDir . '/' . $this->config->name;
            $this->log('info', 'Combining executable...');
            $this->combineExecutable($sfxPath, $pharPath, $combinedPath);
            $binarySize = filesize($combinedPath);
            $this->log('success', sprintf('Binary: %.2f MB', $binarySize / 1024 / 1024));

            // Phase 6: Package for platform
            $this->log('info', "Packaging for {$platform}...");
            $outputPath = $this->platformPackager->package($combinedPath, $platformOutputDir, $platform);
            $this->log('success', 'Output: ' . $outputPath);

            // Phase 7: Report
            $bundleSize = $this->getDirectorySize($outputPath);

            return [
                'outputPath' => $outputPath,
                'pharSize' => (int) $pharSize,
                'binarySize' => (int) $binarySize,
                'bundleSize' => $bundleSize,
            ];
        } finally {
            // Cleanup temp dir
            if (is_dir($tempDir)) {
                $this->removeDirectory($tempDir);
            }

            // Restore dev dependencies
            $this->restoreVendor();
        }
    }

    private function prepareVendor(): void
    {
        // Use --no-dev to exclude dev dependencies from the build.
        // We run `composer update --no-dev` instead of `install --no-dev`
        // because the lock file may contain dev-only platform requirements
        // that would cause `install --no-dev` to fail.
        $cmd = sprintf(
            'cd %s && composer update --no-dev --no-interaction --ignore-platform-reqs 2>&1',
            escapeshellarg($this->config->projectRoot)
        );
        exec($cmd, $output, $returnCode);
        if ($returnCode !== 0) {
            throw new \RuntimeException("composer update --no-dev failed:\n" . implode("\n", $output));
        }
    }

    private function restoreVendor(): void
    {
        // Restore dev dependencies after build
        $cmd = sprintf(
            'cd %s && composer update --no-interaction --ignore-platform-reqs 2>&1',
            escapeshellarg($this->config->projectRoot)
        );
        exec($cmd);
    }

    private function combineExecutable(string $sfxPath, string $pharPath, string $outputPath): void
    {
        // Concatenate micro.sfx + game.phar into a single executable
        $out = fopen($outputPath, 'wb');
        if ($out === false) {
            throw new \RuntimeException("Failed to open output file: {$outputPath}");
        }
        try {
            foreach ([$sfxPath, $pharPath] as $inputFile) {
                $in = fopen($inputFile, 'rb');
                if ($in === false) {
                    throw new \RuntimeException("Failed to open input file: {$inputFile}");
                }
                stream_copy_to_stream($in, $out);
                fclose($in);
            }
        } finally {
            fclose($out);
        }
        chmod($outputPath, 0755);
    }

    private function countFiles(string $dir): int
    {
        $count = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($iterator as $_) {
            $count++;
        }
        return $count;
    }

    private function getDirectorySize(string $path): int
    {
        if (is_file($path)) {
            return (int) filesize($path);
        }
        $size = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($iterator as $file) {
            $size += $file->getSize();
        }
        return $size;
    }

    private function removeDirectory(string $dir): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }

    private function log(string $level, string $message): void
    {
        if ($this->logger) {
            ($this->logger)($level, $message);
        }
    }
}
