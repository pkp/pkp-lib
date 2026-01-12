<?php

/**
 * @file classes/security/RateLimitingService.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Service class for handling rate limiting on login and password reset.
 *
 * Uses Laravel's RateLimiter facade to track and limit authentication attempts.
 * Rate limiting is keyed by IP + username for login attempts and IP-only for
 * password reset requests. Configuration is read from site settings.
 */

namespace PKP\security;

use PKP\site\Site;
use Illuminate\Support\Facades\RateLimiter;

class RateLimitingService
{
    /**
     * Default values for rate limiting configuration (used when site settings not available)
     */
    public const DEFAULT_MAX_ATTEMPTS = 5;
    public const DEFAULT_DECAY_SECONDS = 300;

    /**
     * Random delay range (in seconds) to prevent timing attacks
     */
    protected const RATE_LIMIT_DELAY_MIN = 2;
    protected const RATE_LIMIT_DELAY_MAX = 5;

    /*
     * Singleton instance
     */
    private static $instance = null;

    /*
     * Application's site instance to read site configs
     */
    protected ?Site $site = null;

    // Private constructor to prevent direct instantiation
    private function __construct() {}

    /*
     * Get the singleton instance
     */
    public static function getInstance(?Site $site = null): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
            self::$instance->site = $site;
        }

        return self::$instance;
    }

    /**
     * Check if rate limiting is enabled.
     *
     * @return bool True if rate limiting is enabled
     */
    public function isRateLimitEnabled(): bool
    {
        return (bool) ($this->site?->getData('rateLimitEnabled') ?? false);
    }

    /**
     * Check if login attempts are rate limited for the given username and IP.
     *
     * @param string $username The username attempting to log in
     * @param string $ip The IP address of the request
     *
     * @return bool True if rate limited, false if attempts are allowed
     */
    public function isLoginLimited(string $username, string $ip): bool
    {
        $key = $this->getLoginRateLimitKey($username, $ip);
        $maxAttempts = $this->getMaxAttempts();

        return RateLimiter::tooManyAttempts($key, $maxAttempts);
    }

    /**
     * Record a failed login attempt.
     *
     * @param string $username The username that failed to log in
     * @param string $ip The IP address of the request
     */
    public function recordLoginAttempt(string $username, string $ip): void
    {
        $key = $this->getLoginRateLimitKey($username, $ip);
        $decaySeconds = $this->getDecaySeconds();

        RateLimiter::hit($key, $decaySeconds);
    }

    /**
     * Clear the rate limit for a successful login.
     *
     * @param string $username The username that successfully logged in
     * @param string $ip The IP address of the request
     */
    public function clearLoginLimit(string $username, string $ip): void
    {
        $key = $this->getLoginRateLimitKey($username, $ip);
        RateLimiter::clear($key);
    }

    /**
     * Get the remaining seconds until the login rate limit resets.
     *
     * @param string $username The username
     * @param string $ip The IP address
     *
     * @return int Seconds until rate limit resets
     */
    public function getLoginAvailableIn(string $username, string $ip): int
    {
        $key = $this->getLoginRateLimitKey($username, $ip);

        return RateLimiter::availableIn($key);
    }

    /**
     * Get the number of remaining login attempts.
     *
     * @param string $username The username
     * @param string $ip The IP address
     *
     * @return int Number of remaining attempts
     */
    public function getLoginRemainingAttempts(string $username, string $ip): int
    {
        $key = $this->getLoginRateLimitKey($username, $ip);
        $maxAttempts = $this->getMaxAttempts();

        return RateLimiter::remaining($key, $maxAttempts);
    }

    /**
     * Check if password reset requests are rate limited for the given IP.
     *
     * @param string $ip The IP address of the request
     * @return bool True if rate limited, false if requests are allowed
     */
    public function isPasswordResetLimited(string $ip): bool
    {
        $key = $this->getPasswordResetRateLimitKey($ip);
        $maxAttempts = $this->getMaxAttempts();

        return RateLimiter::tooManyAttempts($key, $maxAttempts);
    }

    /**
     * Record a password reset request attempt.
     *
     * @param string $ip The IP address of the request
     */
    public function recordPasswordResetAttempt(string $ip): void
    {
        $key = $this->getPasswordResetRateLimitKey($ip);
        $decaySeconds = $this->getDecaySeconds();

        RateLimiter::hit($key, $decaySeconds);
    }

    /**
     * Clear the password reset rate limit.
     *
     * @param string $ip The IP address
     */
    public function clearPasswordResetLimit(string $ip): void
    {
        $key = $this->getPasswordResetRateLimitKey($ip);
        RateLimiter::clear($key);
    }

    /**
     * Get the remaining seconds until the password reset rate limit resets.
     *
     * @param string $ip The IP address
     * @return int Seconds until rate limit resets
     */
    public function getPasswordResetAvailableIn(string $ip): int
    {
        $key = $this->getPasswordResetRateLimitKey($ip);

        return RateLimiter::availableIn($key);
    }

    /**
     * Apply an artificial delay to prevent timing attacks.
     *
     * When rate limited, this adds a random delay (2-5 seconds) to make it
     * harder for attackers to determine if the rate limit was triggered
     * versus a slow authentication process.
     */
    public function applyRateLimitDelay(): void
    {
        $delay = random_int(
            self::RATE_LIMIT_DELAY_MIN * 1000000,
            self::RATE_LIMIT_DELAY_MAX * 1000000
        );
        usleep($delay);
    }

    /**
     * Generate the rate limit key for login attempts.
     *
     * Uses IP + lowercase username to prevent case-based bypasses.
     * Falls back to IP-only key when the username is empty.
     *
     * @param string $username The username
     * @param string $ip The IP address
     *
     * @return string The rate limit key
     */
    protected function getLoginRateLimitKey(string $username, string $ip): string
    {
        $normalizedUsername = mb_strtolower(trim($username), 'UTF-8');

        if (empty($normalizedUsername)) {
            return 'login:' . $ip;
        }

        return 'login:' . $ip . ':' . $normalizedUsername;
    }

    /**
     * Generate the rate limit key for password reset requests.
     * Uses IP-only to prevent email enumeration attacks.
     *
     * @param string $ip The IP address
     * @return string The rate limit key
     */
    protected function getPasswordResetRateLimitKey(string $ip): string
    {
        return 'password_reset:' . $ip;
    }

    /**
     * Get the maximum number of attempts from site settings.
     *
     * @return int Maximum attempts
     */
    protected function getMaxAttempts(): int
    {
        return (int) ($this->site?->getData('rateLimitMaxAttempts') ?? self::DEFAULT_MAX_ATTEMPTS);
    }

    /**
     * Get the rate limit decay time in seconds from site settings.
     *
     * @return int Decay time in seconds
     */
    protected function getDecaySeconds(): int
    {
        return (int) ($this->site?->getData('rateLimitDecaySeconds') ?? self::DEFAULT_DECAY_SECONDS);
    }
}
