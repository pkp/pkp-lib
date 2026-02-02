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
 * Rate limiting is keyed by 
 *  - IP + username for login attempts
 *  - IP + email for password reset requests
 */

namespace PKP\security;

use Illuminate\Support\Facades\RateLimiter;
use PKP\site\Site;

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

        $isLimited = RateLimiter::tooManyAttempts($key, $maxAttempts);

        // Log rate limit event for security monitoring
        if ($isLimited) {
            $this->logRateLimitEvent('login', $ip, $username ?: null);
        }

        return $isLimited;
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
     * Check if password reset requests are rate limited for the given IP and email.
     *
     * @param string $ip The IP address of the request
     * @param string $email The email address for the reset request
     *
     * @return bool True if rate limited, false if requests are allowed
     */
    public function isPasswordResetLimited(string $ip, string $email): bool
    {
        $key = $this->getPasswordResetRateLimitKey($ip, $email);
        $maxAttempts = $this->getMaxAttempts();

        $isLimited = RateLimiter::tooManyAttempts($key, $maxAttempts);

        // Log rate limit event for security monitoring
        if ($isLimited) {
            $this->logRateLimitEvent('password_reset', $ip, $email ?: null);
        }

        return $isLimited;
    }

    /**
     * Record a password reset request attempt.
     *
     * @param string $ip The IP address of the request
     * @param string $email The email address for the reset request
     */
    public function recordPasswordResetAttempt(string $ip, string $email): void
    {
        $key = $this->getPasswordResetRateLimitKey($ip, $email);
        $decaySeconds = $this->getDecaySeconds();

        RateLimiter::hit($key, $decaySeconds);
    }

    /**
     * Clear the password reset rate limit.
     *
     * @param string $ip The IP address
     * @param string $email The email address
     */
    public function clearPasswordResetLimit(string $ip, string $email): void
    {
        $key = $this->getPasswordResetRateLimitKey($ip, $email);
        RateLimiter::clear($key);
    }

    /**
     * Get the remaining seconds until the password reset rate limit resets.
     *
     * @param string $ip The IP address
     * @param string $email The email address
     *
     * @return int Seconds until rate limit resets
     */
    public function getPasswordResetAvailableIn(string $ip, string $email): int
    {
        $key = $this->getPasswordResetRateLimitKey($ip, $email);

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
     * Normalize IP address for rate limiting.
     *
     * For IPv6, normalizes to /64 prefix to prevent rotation attacks
     * using privacy extensions (RFC 4941). All addresses within the same
     * /64 network will share a rate limit counter.
     *
     * @param string $ip The IP address
     *
     * @return string Normalized IP address
     */
    protected function normalizeIp(string $ip): string
    {
        // Check if IPv6
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $binary = inet_pton($ip);
            if ($binary !== false) {
                // Keep first 8 bytes (/64 prefix), zero out, e,g. remove the interface identifier which can be rotetable
                $prefix = substr($binary, 0, 8) . str_repeat("\0", 8);
                return inet_ntop($prefix);
            }
        }

        return $ip;
    }

    /**
     * Log a rate limit event for security monitoring.
     *
     * @param string $type The type of rate limit ('login' or 'password_reset')
     * @param string $ip The IP address (original, not normalized)
     * @param string|null $identifier The username (for login) or email (for password reset)
     */
    protected function logRateLimitEvent(string $type, string $ip, ?string $identifier = null): void
    {
        $identifierLabel = $type === 'password_reset' ? 'Email' : 'Username';
        $message = sprintf(
            '[RateLimit] %s triggered - IP: %s%s',
            ucfirst($type),
            $ip,
            $identifier !== null ? ", {$identifierLabel}: {$identifier}" : ''
        );

        error_log($message);
    }

    /**
     * Generate the rate limit key for login attempts.
     *
     * Uses normalized IP (IPv6 /64 prefix) + normalized username to prevent
     * case-based and Unicode variant bypasses.
     * Falls back to IP-only key when the username is empty.
     *
     * @param string $username The username
     * @param string $ip The IP address
     *
     * @return string The rate limit key
     */
    protected function getLoginRateLimitKey(string $username, string $ip): string
    {
        // Normalize IP (IPv6 /64 prefix)
        $normalizedIp = $this->normalizeIp($ip);

        // Normalize username: trim, lowercase, and Unicode normalize to NFC
        $normalizedUsername = mb_strtolower(trim($username), 'UTF-8');

        // Apply Unicode NFC normalization if Normalizer class is available
        if (class_exists('Normalizer')) {
            $normalized = \Normalizer::normalize($normalizedUsername, \Normalizer::FORM_C);
            if ($normalized !== false) {
                $normalizedUsername = $normalized;
            }
        }

        if (empty($normalizedUsername)) {
            return 'login:' . $normalizedIp;
        }

        return 'login:' . $normalizedIp . ':' . $normalizedUsername;
    }

    /**
     * Generate the rate limit key for password reset requests.
     *
     * Uses normalized IP (IPv6 /64 prefix) + normalized email
     * Falls back to IP-only key when the email is empty.
     *
     * @param string $ip The IP address
     * @param string $email The email address
     *
     * @return string The rate limit key
     */
    protected function getPasswordResetRateLimitKey(string $ip, string $email): string
    {
        $normalizedIp = $this->normalizeIp($ip);

        $normalizedEmail = mb_strtolower(trim($email), 'UTF-8'); // Normalize email: trim and lowercase

        if (empty($normalizedEmail)) {
            return 'password_reset:' . $normalizedIp;
        }

        return 'password_reset:' . $normalizedIp . ':' . $normalizedEmail;
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
