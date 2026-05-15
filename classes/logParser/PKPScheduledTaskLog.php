<?php

/**
 * @file classes/logParser/PKPScheduledTaskLog.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPScheduledTaskLog
 *
 * @brief Custom scheduled task log parser for Log Viewer
 *
 * Handles OJS scheduled task log format:
 * - [YYYY-MM-DD HH:MM:SS] [Notice] Task process started.
 * - [YYYY-MM-DD HH:MM:SS] [Warning] Some warning message.
 * - [YYYY-MM-DD HH:MM:SS] http://localhost (URL line, no level)
 */

namespace PKP\logParser;

use Opcodes\LogViewer\Logs\Log;

class PKPScheduledTaskLog extends Log
{
    public static string $name = 'Scheduled Tasks';

    // Matches: [2026-02-10 06:07:02] [Notice] Task process started.
    // Also: [2026-02-10 06:07:02] http://localhost (no level bracket)
    public static string $regex = '/^\[(?<datetime>\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2})\]\s*(?:\[(?<level>[^\]]+)\]\s*)?(?<message>.+)/s';

    protected function fillMatches(array $matches = []): void
    {
        $this->datetime = static::parseDatetime($matches['datetime'] ?? null);
        $this->message = trim($matches['message'] ?? '');

        // Map level from bracket text to Laravel log levels
        $rawLevel = strtolower(trim($matches['level'] ?? ''));
        $this->level = match ($rawLevel) {
            'notice' => 'NOTICE',
            'warning' => 'WARNING',
            'error' => 'ERROR',
            'completed' => 'INFO',
            '' => 'INFO',
            default => 'INFO',
        };
    }

    public static function matches(string $text, ?int &$timestamp = null, ?string &$level = null): bool
    {
        // Must start with [YYYY-MM-DD HH:MM:SS] pattern
        if (!preg_match('/^\[\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2}\]/', $text)) {
            return false;
        }

        return parent::matches($text, $timestamp, $level);
    }
}
