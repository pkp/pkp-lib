<?php

/**
 * @file classes/core/PKPPhpErrorLog.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPPhpErrorLog
 *
 * @brief Custom PHP error log parser for Log Viewer
 *
 * Handles PHP error log formats including exceptions with stack traces:
 * - [datetime] PHP Warning: message in /file on line N
 * - [datetime] PHP Fatal error: message in /file on line N
 * - [datetime] ExceptionClass: message in /file:N
 *   Stack trace:
 *   #0 /file(line): function()
 */

namespace PKP\core;

use Opcodes\LogViewer\Logs\Log;
use Opcodes\LogViewer\LogLevels\LaravelLogLevel;

class PKPPhpErrorLog extends Log
{
    public static string $name = 'PHP Error Log';
    public static string $levelClass = LaravelLogLevel::class;

    // Flexible regex: captures [datetime] and everything after as message
    // The 's' flag allows . to match newlines for multiline stack traces
    public static string $regex = '/^\[(?<datetime>[^\]]+)\]\s*(?<message>.+)/s';

    // PHP error level mappings to Laravel log levels
    protected static array $levelMappings = [
        'PHP Fatal error' => 'CRITICAL',
        'PHP Parse error' => 'CRITICAL',
        'PHP Compile' => 'CRITICAL',
        'PHP Core' => 'CRITICAL',
        'PHP Warning' => 'WARNING',
        'PHP Notice' => 'NOTICE',
        'PHP Deprecated' => 'NOTICE',
        'PHP Strict' => 'NOTICE',
    ];

    protected function parseText(array &$matches = []): void
    {
        preg_match(static::$regex, $this->text, $matches);

        if (!empty($matches['message'])) {
            $matches['level'] = $this->extractLevel($matches['message']);
        }
    }

    /**
     * Extract severity level from message content
     */
    protected function extractLevel(string $message): string
    {
        // Check for PHP error type prefixes
        foreach (static::$levelMappings as $prefix => $level) {
            if (str_starts_with($message, $prefix)) {
                return $level;
            }
        }

        // Check for exception classes (FQN with backslash or ends with Exception/Error)
        if (preg_match('/^[\w\\\\]+(Exception|Error):/', $message)) {
            return 'ERROR';
        }

        return 'ERROR'; // Default for unrecognized formats
    }

    /**
     * Check if text matches PHP error log format
     */
    public static function matches(string $text, ?int &$timestamp = null, ?string &$level = null): bool
    {
        // Must start with [datetime] pattern like [23-Jan-2026 06:32:42 UTC]
        if (!preg_match('/^\[[\d]{1,2}-[A-Za-z]{3}-[\d]{4}\s[\d:]+/', $text)) {
            return false;
        }

        return parent::matches($text, $timestamp, $level);
    }
}
