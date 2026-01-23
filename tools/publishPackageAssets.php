<?php

/**
 * @file tools/publishPackageAssets.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CommandPublishPackageAssets
 *
 * @brief CLI tool to publish package assets to the public directory
 *
 * This tool copies assets from Laravel packages (like log-viewer) to the
 * public directory, since OJS doesn't have full Laravel console/artisan support.
 *
 * Usage:
 *   php lib/pkp/tools/publishPackageAssets.php list
 *   php lib/pkp/tools/publishPackageAssets.php publish
 *   php lib/pkp/tools/publishPackageAssets.php publish --package=log-viewer
 *   php lib/pkp/tools/publishPackageAssets.php publish --force
 */

namespace PKP\tools;

use PKP\cliTool\CommandLineTool;
use PKP\cliTool\traits\HasCommandInterface;
use PKP\cliTool\traits\HasParameterList;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\Console\Exception\InvalidArgumentException as CommandInvalidArgumentException;
use Symfony\Component\Console\Helper\Helper;
use Throwable;

define('APP_ROOT', dirname(__FILE__, 4));
require_once APP_ROOT . '/tools/bootstrap.php';

class CommandPublishPackageAssets extends CommandLineTool
{
    use HasParameterList;
    use HasCommandInterface;

    /**
     * Available CLI options/commands
     */
    protected const AVAILABLE_OPTIONS = [
        'list'    => 'List packages with publishable assets',
        'publish' => 'Publish package assets to public directory',
        'usage'   => 'Display usage information',
    ];

    /**
     * Registry of packages with publishable assets
     * Each package defines source path (relative to base) and destination (relative to public/)
     */
    protected const PUBLISHABLE_PACKAGES = [
        'log-viewer' => [
            'source' => 'lib/pkp/lib/vendor/opcodesio/log-viewer/public',
            'destination' => 'vendor/log-viewer',
            'description' => 'Log Viewer web interface assets',
        ],
    ];

    /**
     * Which option will be called
     */
    protected ?string $option;

    /**
     * Constructor
     */
    public function __construct(array $argv = [])
    {
        parent::__construct($argv);

        array_shift($argv); // Shift the tool name off the top

        $this->setParameterList($argv);

        if (!isset($this->getParameterList()[0])) {
            throw new CommandNotFoundException(
                'No option provided. Run with "usage" for help.',
                array_keys(self::AVAILABLE_OPTIONS)
            );
        }

        $this->option = $this->getParameterList()[0];

        $this->setCommandInterface();
    }

    /**
     * Parse and execute the command
     */
    public function execute(): void
    {
        if (!isset(self::AVAILABLE_OPTIONS[$this->option])) {
            throw new CommandNotFoundException(
                "Unknown option: {$this->option}",
                array_keys(self::AVAILABLE_OPTIONS)
            );
        }

        $this->{$this->option}();
    }

    /**
     * Print command usage information
     */
    public function usage(): void
    {
        $this->getCommandInterface()->line('<comment>Usage:</comment>');
        $this->getCommandInterface()->line('  php lib/pkp/tools/publishPackageAssets.php <command> [options]' . PHP_EOL);
        $this->getCommandInterface()->line('<comment>Available commands:</comment>');

        $this->printCommandList(self::AVAILABLE_OPTIONS, false);

        $this->getCommandInterface()->line('');
        $this->getCommandInterface()->line('<comment>Options for publish:</comment>');
        $this->getCommandInterface()->line('  <info>--package=NAME</info>  Publish assets for a specific package only');
        $this->getCommandInterface()->line('  <info>--force</info>         Overwrite existing files');
    }

    /**
     * List available packages with publishable assets
     */
    protected function list(): void
    {
        $output = $this->getCommandInterface()->getOutput();

        $output->writeln('<comment>Available packages with publishable assets:</comment>' . PHP_EOL);

        $packages = $this->getAvailablePackages();

        if (empty($packages)) {
            $output->writeln('<info>No packages with publishable assets found.</info>');
            return;
        }

        // Calculate max width for alignment
        $maxWidth = max(array_map('strlen', array_keys($packages))) + 2;

        foreach ($packages as $name => $config) {
            $spacing = str_repeat(' ', $maxWidth - strlen($name));
            $output->writeln("  <info>{$name}</info>{$spacing}{$config['description']}");
            $output->writeln(str_repeat(' ', $maxWidth + 2) . "Source: {$config['source']}");
            $output->writeln(str_repeat(' ', $maxWidth + 2) . "Destination: public/{$config['destination']}");
            $output->writeln('');
        }
    }

    /**
     * Publish package assets to the public directory
     */
    protected function publish(): void
    {
        $output = $this->getCommandInterface()->getOutput();
        $force = $this->hasFlagSet('--force');
        $packageFilter = $this->getParameterValue('package');

        $packages = $this->getAvailablePackages();

        if (empty($packages)) {
            $output->writeln('<error>No packages with publishable assets found.</error>');
            return;
        }

        // Filter to specific package if requested
        if ($packageFilter) {
            if (!isset($packages[$packageFilter])) {
                $output->writeln("<error>Package '{$packageFilter}' not found.</error>");
                $output->writeln('Available packages: ' . implode(', ', array_keys($packages)));
                return;
            }
            $packages = [$packageFilter => $packages[$packageFilter]];
        }

        // Check which packages are already published
        $alreadyPublished = [];
        $notPublished = [];
        foreach ($packages as $name => $config) {
            if ($this->isPackagePublished($name, $config)) {
                $alreadyPublished[$name] = $config;
            } else {
                $notPublished[$name] = $config;
            }
        }

        // If not using --force and all packages are already published
        if (!$force && empty($notPublished) && !empty($alreadyPublished)) {
            $packageNames = implode(', ', array_keys($alreadyPublished));
            if ($packageFilter) {
                // Single package requested and already published
                $output->writeln("<comment>Package '{$packageFilter}' assets are already published.</comment>");
                $output->writeln('');
                $output->writeln('Use <info>--force</info> to overwrite existing files.');
            } else {
                // All packages already published
                $output->writeln('<comment>All package assets are already published:</comment>');
                foreach (array_keys($alreadyPublished) as $name) {
                    $output->writeln("  - {$name}");
                }
                $output->writeln('');
                $output->writeln('Use <info>--force</info> to overwrite existing files.');
            }
            return;
        }

        // If using --force, confirm with user
        if ($force && !empty($alreadyPublished)) {
            $output->writeln('<comment>Warning: The following package assets will be overwritten:</comment>');
            foreach (array_keys($alreadyPublished) as $name) {
                $output->writeln("  - {$name}");
            }
            $output->writeln('');
            $output->writeln('<error>Any custom modifications to these files will be lost!</error>');
            $output->writeln('');

            if (!$this->confirm('Are you sure you want to continue?')) {
                $output->writeln('<info>Operation cancelled.</info>');
                return;
            }
            $output->writeln('');
        }

        $totalCopied = 0;
        $totalSkipped = 0;
        $publishedPackages = [];
        $skippedPackages = [];

        foreach ($packages as $name => $config) {
            $output->writeln("<comment>Publishing assets for {$name}...</comment>");

            $sourcePath = base_path($config['source']);
            $destPath = public_path($config['destination']);

            if (!is_dir($sourcePath)) {
                $output->writeln("  <error>Source directory not found: {$sourcePath}</error>");
                continue;
            }

            $result = $this->copyDirectory($sourcePath, $destPath, $force);

            foreach ($result['copied'] as $file) {
                $output->writeln("  <info>Copied:</info> {$file}");
            }

            foreach ($result['skipped'] as $file) {
                $output->writeln("  <comment>Skipped:</comment> {$file}");
            }

            $copiedCount = count($result['copied']);
            $skippedCount = count($result['skipped']);

            $totalCopied += $copiedCount;
            $totalSkipped += $skippedCount;

            if ($copiedCount > 0) {
                $publishedPackages[] = $name;
            } elseif ($skippedCount > 0) {
                $skippedPackages[] = $name;
            }
        }

        $output->writeln('');

        // Summary of what was published
        if (!empty($publishedPackages)) {
            $output->writeln('<info>Assets published successfully!</info>');
            $output->writeln('');
            $output->writeln('Published packages:');
            foreach ($publishedPackages as $name) {
                $output->writeln("  <info>+</info> {$name}");
            }
            $output->writeln('');
            $output->writeln("Total files copied: {$totalCopied}");
        }

        if (!empty($skippedPackages)) {
            $output->writeln('');
            $output->writeln('Skipped packages (already published):');
            foreach ($skippedPackages as $name) {
                $output->writeln("  <comment>-</comment> {$name}");
            }
            $output->writeln('');
            $output->writeln("Total files skipped: {$totalSkipped}");
            $output->writeln('Use <info>--force</info> to overwrite existing files.');
        }

        if (empty($publishedPackages) && empty($skippedPackages)) {
            $output->writeln('<comment>No files to publish.</comment>');
        }
    }

    /**
     * Check if a package's assets are already published
     *
     * @param string $name Package name
     * @param array $config Package configuration
     *
     * @return bool True if the package destination directory exists and has files
     */
    protected function isPackagePublished(string $name, array $config): bool
    {
        $destPath = public_path($config['destination']);

        if (!is_dir($destPath)) {
            return false;
        }

        // Check if destination has any files (not just empty directory)
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($destPath, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            if ($item->isFile()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Prompt user for confirmation
     *
     * @param string $question The question to ask
     *
     * @return bool True if user confirms, false otherwise
     */
    protected function confirm(string $question): bool
    {
        $output = $this->getCommandInterface()->getOutput();
        $output->write("<question>{$question}</question> [y/N]: ");

        $handle = fopen('php://stdin', 'r');
        $response = trim(fgets($handle));
        fclose($handle);

        return strtolower($response) === 'y' || strtolower($response) === 'yes';
    }

    /**
     * Get available packages that exist on the filesystem
     *
     * @return array Filtered packages that have valid source directories
     */
    protected function getAvailablePackages(): array
    {
        $available = [];

        foreach (self::PUBLISHABLE_PACKAGES as $name => $config) {
            $sourcePath = base_path($config['source']);
            if (is_dir($sourcePath)) {
                $available[$name] = $config;
            }
        }

        return $available;
    }

    /**
     * Recursively copy a directory
     *
     * @param string $source Source directory path
     * @param string $destination Destination directory path
     * @param bool $force Overwrite existing files
     *
     * @return array{copied: string[], skipped: string[]} Lists of copied and skipped files
     */
    protected function copyDirectory(string $source, string $destination, bool $force = false): array
    {
        $copied = [];
        $skipped = [];

        // Create destination directory if it doesn't exist
        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }

        $directoryIterator = new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS);
        $iterator = new RecursiveIteratorIterator($directoryIterator, RecursiveIteratorIterator::SELF_FIRST);

        foreach ($iterator as $item) {
            /** @var RecursiveDirectoryIterator $innerIterator */
            $innerIterator = $iterator->getInnerIterator();
            $subPath = $innerIterator->getSubPathname();
            $destPath = $destination . DIRECTORY_SEPARATOR . $subPath;

            if ($item->isDir()) {
                if (!is_dir($destPath)) {
                    mkdir($destPath, 0755, true);
                }
            } else {
                if (!$force && file_exists($destPath)) {
                    $skipped[] = $subPath;
                    continue;
                }

                // Ensure parent directory exists
                $parentDir = dirname($destPath);
                if (!is_dir($parentDir)) {
                    mkdir($parentDir, 0755, true);
                }

                copy($item->getPathname(), $destPath);
                $copied[] = $subPath;
            }
        }

        return ['copied' => $copied, 'skipped' => $skipped];
    }

    /**
     * Print given options in a formatted list
     *
     * @param array $options Option name => description mapping
     * @param bool $shouldTranslate Whether to translate descriptions (always false for this tool)
     */
    protected function printCommandList(array $options, bool $shouldTranslate = false): void
    {
        $width = (int) collect(array_keys($options))
            ->map(fn ($command) => Helper::width($command))
            ->sort()
            ->last() + 2;

        foreach ($options as $commandName => $description) {
            $spacingWidth = $width - Helper::width($commandName);
            $this->getCommandInterface()->line(
                sprintf(
                    '  <info>%s</info>%s%s',
                    $commandName,
                    str_repeat(' ', $spacingWidth),
                    $description
                )
            );
        }
    }
}

try {
    $tool = new CommandPublishPackageAssets($argv ?? []);
    $tool->execute();
} catch (Throwable $exception) {
    $output = new \PKP\cliTool\CommandInterface;

    if ($exception instanceof CommandInvalidArgumentException) {
        $output->errorBlock([$exception->getMessage()]);
        exit(1);
    }

    if ($exception instanceof CommandNotFoundException) {
        $alternatives = $exception->getAlternatives();
        $message = 'Did you mean one of these?' . PHP_EOL . implode(PHP_EOL, $alternatives);
        $output->errorBlock([$exception->getMessage(), $message]);
        exit(1);
    }

    throw $exception;
}
