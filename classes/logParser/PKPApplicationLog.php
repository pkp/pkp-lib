<?php

/**
 * @file classes/logParser/PKPApplicationLog.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPApplicationLog
 *
 * @brief Application log parser for Log Viewer, handling both Monolog output formats.
 *
 * The [logs] log_formatter setting selects the Monolog formatter for the file channels, so the
 * application log may be written either in Monolog's default line format:
 *
 *   [2026-09-02 05:27:55] production.ERROR: Something broke {"exception":"..."}
 *
 * or, with log_formatter = Monolog\Formatter\JsonFormatter, as one JSON record per line:
 *
 *   {"message":"...","context":{},"level":400,"level_name":"ERROR","channel":"production", ...}
 *
 * Log Viewer picks a single parser per file, and a file routinely holds both shapes because
 * changing log_formatter does not start a new file. A parser that understood only one shape
 * would not merely hide the other: the reader appends non-matching lines to the preceding
 * entry, or drops them entirely when no entry is open yet, so those records would silently
 * vanish along with their severity and timestamp. This parser therefore accepts either shape
 * and dispatches per entry; line-format entries are handed to the parent untouched.
 */

namespace PKP\logParser;

use Illuminate\Support\Carbon;
use Opcodes\LogViewer\Facades\LogViewer;
use Opcodes\LogViewer\Logs\LaravelLog;
use Opcodes\LogViewer\Utils\Utils;
use Throwable;

class PKPApplicationLog extends LaravelLog
{
    public static string $name = 'Application';

    /**
     * @copydoc \Opcodes\LogViewer\Logs\Log::matches()
     */
    public static function matches(string $text, ?int &$timestamp = null, ?string &$level = null): bool
    {
        if (parent::matches($text, $timestamp, $level)) {
            return true;
        }

        if (!($record = static::decodeJsonRecord($text))) {
            return false;
        }

        try {
            $timestamp = Carbon::parse($record['datetime'])->timestamp;
        } catch (Throwable $exception) {
            return false;
        }

        $level = strtoupper((string) $record['level_name']);

        return true;
    }

    /**
     * @copydoc \Opcodes\LogViewer\Logs\LaravelLog::parseText()
     */
    protected function parseText(array &$matches = []): void
    {
        if (!($record = static::decodeJsonRecord($this->text))) {
            parent::parseText($matches);

            return;
        }

        $this->text = trim(mb_convert_encoding($this->text, 'UTF-8', 'UTF-8'));
        $length = strlen($this->text);

        $this->extra['log_size'] = $length;
        $this->extra['log_size_formatted'] = Utils::bytesForHumans($length);
        $this->extra['environment'] = $record['channel'] ?? null;

        $this->datetime = Carbon::parse($record['datetime'])->setTimezone(LogViewer::timezone());
        $this->level = strtoupper((string) $record['level_name']);
        $this->message = trim((string) $record['message']);

        // JsonFormatter keeps Monolog's context and extra apart; the viewer shows a single
        // context panel, so nest 'extra' under the context rather than losing it.
        $context = is_array($record['context'] ?? null) ? $record['context'] : [];
        if (!empty($record['extra']) && !array_key_exists('extra', $context)) {
            $context['extra'] = $record['extra'];
        }
        $this->context = $context;

        if ($length > LogViewer::maxLogSize()) {
            $this->text = mb_strimwidth($this->text, 0, LogViewer::maxLogSize());
            $this->extra['log_text_incomplete'] = true;
        }
    }

    /**
     * Decode a single JsonFormatter record, or null when the text is not one.
     *
     * Requires the keys JsonFormatter always emits. 'datetime' and 'level_name' in particular
     * distinguish these records from the usage-event logs handled by PKPUsageEventLog, which
     * are also one JSON object per line but key their timestamp as 'time'.
     */
    protected static function decodeJsonRecord(string $text): ?array
    {
        $text = trim($text);

        if (!str_starts_with($text, '{')) {
            return null;
        }

        $record = json_decode($text, true);

        if (!is_array($record)
            || !array_key_exists('message', $record)
            || !isset($record['level_name'], $record['datetime'])
        ) {
            return null;
        }

        return $record;
    }
}
