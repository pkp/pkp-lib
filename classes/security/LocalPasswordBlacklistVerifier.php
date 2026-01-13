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
use Illuminate\Support\Facades\Cache;

class LocalPasswordBlacklistVerifier implements UncompromisedVerifier
{
    /** @var string Cache key prefix */
    protected const CACHE_KEY_PREFIX = 'password_blacklist';

    /** @var string Path to the blacklist file relative to BASE_SYS_DIR */
    protected const BLACKLIST_FILE_PATH = 'lib/pkp/registry/blacklistedPasswords.txt';

    /** @var int Cache lifetime in seconds (24 hours) */
    protected const CACHE_LIFETIME_SECONDS = 86400;

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

        // Check if password exists in blacklist (case-insensitive)
        return !in_array(strtolower($password), $blacklist, true);
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
     * Clear the blacklist cache
     */
    public function clearCache(): void
    {
        $filePath = $this->getBlacklistFilePath();
        if (file_exists($filePath)) {
            $cacheKey = $this->getCacheKey($filePath);
            Cache::forget($cacheKey);
        }
    }

    /**
     * Get the cached blacklist, loading from file if necessary
     *
     * @return array Array of blacklisted passwords (lowercase)
     */
    protected function getBlacklist(): array
    {
        $filePath = $this->getBlacklistFilePath();

        if (!file_exists($filePath)) {
            return [];
        }

        $cacheKey = $this->getCacheKey($filePath);

        return Cache::remember($cacheKey, static::CACHE_LIFETIME_SECONDS, function () use ($filePath) {
            return $this->loadBlacklistFromFile($filePath);
        });
    }

    /**
     * Load blacklist from file
     *
     * @param string $filePath Path to the blacklist file
     * @return array Array of blacklisted passwords (lowercase)
     */
    protected function loadBlacklistFromFile(string $filePath): array
    {
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            return [];
        }

        $blacklist = [];
        foreach ($lines as $line) {
            $line = trim($line);
            // Skip comments (lines starting with #)
            if (!empty($line) && $line[0] !== '#') {
                $blacklist[] = strtolower($line);
            }
        }

        return $blacklist;
    }

    /**
     * Generate cache key incorporating file modification time for automatic invalidation
     *
     * @param string $filePath Path to the blacklist file
     * @return string Cache key
     */
    protected function getCacheKey(string $filePath): string
    {
        $filemtime = filemtime($filePath) ?: 0;

        return static::CACHE_KEY_PREFIX . '::' . md5($filePath . '::' . $filemtime);
    }
}
