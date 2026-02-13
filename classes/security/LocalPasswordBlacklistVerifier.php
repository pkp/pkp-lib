<?php

/**
 * @file classes/security/LocalPasswordBlacklistVerifier.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class LocalPasswordBlacklistVerifier
 *
 * @brief Verifies passwords against a local blacklist file
 */

namespace PKP\security;

use Illuminate\Contracts\Validation\UncompromisedVerifier;

class LocalPasswordBlacklistVerifier implements UncompromisedVerifier
{
    /** @var string Path to the blacklist file relative to BASE_SYS_DIR */
    protected const BLACKLIST_FILE_PATH = 'lib/pkp/registry/blacklistedPasswords.txt';

    /**
     * Verify that the given password has not been blacklisted.
     *
     * @param array $data Contains 'value' (password) and 'threshold' (ignored for local check)
     * @return bool True if password is NOT blacklisted (safe to use), false if blacklisted
     */
    public function verify($data): bool
    {
        $password = $data['value'] ?? '';

        if (empty($password = (string) $password)) {
            return false;
        }

        $blacklist = $this->getBlacklist();

        // Check if password exists in blacklist (case-insensitive, Unicode-aware)
        return !in_array(mb_strtolower($password, 'UTF-8'), $blacklist, true);
    }

    /**
     * Get the blacklist file path
     */
    public function getBlacklistFilePath(): string
    {
        return app()->basePath() . DIRECTORY_SEPARATOR . static::BLACKLIST_FILE_PATH;
    }

    /**
     * Check if the blacklist file exists
     */
    public function blacklistFileExists(): bool
    {
        return file_exists($this->getBlacklistFilePath());
    }

    /**
     * Get the blacklist from file.
     *
     * @return array Array of blacklisted passwords (lowercase)
     */
    protected function getBlacklist(): array
    {
        $filePath = $this->getBlacklistFilePath();

        if (!file_exists($filePath)) {
            return [];
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            return [];
        }

        return array_filter(
            array_map(fn ($line) => mb_strtolower(trim($line), 'UTF-8'), $lines),
            fn ($line) => !empty($line) && $line[0] !== '#'
        );
    }
}
