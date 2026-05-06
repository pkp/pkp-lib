<?php

/**
 * @file classes/plugins/PluginFailureHandler.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PluginFailureHandler
 *
 * @ingroup plugins
 *
 * @brief Helper for identifying and logging plugin-related failures to avoid plugins breaking the application.
 */

namespace PKP\plugins;

use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use Throwable;

class PluginFailureHandler
{
    /**
     * Log and identify failures that originated in plugin code.
     */
    public static function logIfPluginFailure(Throwable $throwable, string $failureContext): bool
    {
        $pluginName = static::getPluginFailureName($throwable);
        if ($pluginName === null) {
            return false;
        }

        error_log("Plugin {$pluginName} {$failureContext}\n{$throwable}");
        return true;
    }

    /**
     * Identify the plugin class or plugin directory responsible for a failure.
     */
    private static function getPluginFailureName(Throwable $throwable): ?string
    {
        $pluginDirectories = [
            realpath(BASE_SYS_DIR . '/' . PKP_LIB_PATH . '/plugins'),
            realpath(BASE_SYS_DIR . '/plugins'),
        ];

        foreach ($throwable->getTrace() as $stackFrame) {
            $class = $stackFrame['class'] ?? null;
            // If the code was implemented inside a plugin class, we want to let external flow continue without throwing
            if (is_string($class) && is_subclass_of($class, Plugin::class)) {
                return $class;
            }

            // Avoid fatal errors due to parse failures during autoload.
            if (($stackFrame['function'] ?? '') === 'Composer\Autoload\{closure}') {
                continue;
            }

            // Attempt to recover the file where the callback was implemented
            $filename = static::getStackFrameFilename($stackFrame, $class);
            if ($filename === null) {
                continue;
            }

            // If the code was implemented inside a plugin folder, let the external flow continue
            foreach ($pluginDirectories as $pluginDirectory) {
                $pluginPath = $pluginDirectory . DIRECTORY_SEPARATOR;
                if (!str_starts_with($filename, $pluginPath)) {
                    continue;
                }

                $pieces = explode(DIRECTORY_SEPARATOR, substr($filename, strlen($pluginPath)));
                if (count($pieces) >= 2) {
                    return "{$pieces[0]}/{$pieces[1]}";
                }
            }
        }

        return null;
    }

    /**
     * Attempt to resolve the source filename for a stack frame.
     */
    private static function getStackFrameFilename(array $stackFrame, ?string $class): ?string
    {
        try {
            $filename = ($class ? (new ReflectionClass($class))->getFileName() : null)
                ?? (($function = $stackFrame['function'] ?? null) ? (new ReflectionFunction($function))->getFileName() : null)
                ?? $stackFrame['file']
                ?? null;
        } catch (ReflectionException) {
            $filename = $stackFrame['file'] ?? null;
        }

        if (!$filename) {
            return null;
        }

        return realpath($filename) ?: null;
    }
}
