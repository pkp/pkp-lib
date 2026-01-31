<?php

/**
 * @file tests/classes/security/RateLimitingServiceTest.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RateLimitingServiceTest
 *
 * @brief Test class for RateLimitingService.
 */

namespace PKP\tests\classes\security;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Mockery;
use PKP\security\RateLimitingService;
use PKP\site\Site;
use PKP\tests\PKPTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionClass;

#[CoversClass(RateLimitingService::class)]
class RateLimitingServiceTest extends PKPTestCase
{
    private const TEST_IP = '192.168.1.100';
    private const TEST_USERNAME = 'testuser@example.com';

    protected function setUp(): void
    {
        parent::setUp();

        // Reset singleton instance before each test
        $this->resetSingleton();

        // Clear rate limiter cache
        Cache::flush();
    }

    protected function tearDown(): void
    {
        // Reset singleton after tests
        $this->resetSingleton();

        parent::tearDown();
    }

    /**
     * Reset the RateLimitingService singleton instance.
     */
    private function resetSingleton(): void
    {
        $reflection = new ReflectionClass(RateLimitingService::class);
        $instance = $reflection->getProperty('instance');
        $instance->setValue(null, null);
    }

    /**
     * Create a service instance with mocked Site settings.
     */
    private function createService(bool $enabled = true, int $maxAttempts = 3, int $decaySeconds = 60): RateLimitingService
    {
        $siteMock = Mockery::mock(Site::class);
        $siteMock->shouldReceive('getData')
            ->with('rateLimitEnabled')
            ->andReturn($enabled);
        $siteMock->shouldReceive('getData')
            ->with('rateLimitMaxAttempts')
            ->andReturn($maxAttempts);
        $siteMock->shouldReceive('getData')
            ->with('rateLimitDecaySeconds')
            ->andReturn($decaySeconds);

        return RateLimitingService::getInstance($siteMock);
    }

    /**
     * Test that login becomes rate limited after exceeding max attempts.
     */
    public function testLoginRateLimitAfterMaxAttempts()
    {
        $service = $this->createService(enabled: true, maxAttempts: 3);

        // Initially not limited
        self::assertFalse($service->isLoginLimited(self::TEST_USERNAME, self::TEST_IP));

        // Record 3 failed attempts
        $service->recordLoginAttempt(self::TEST_USERNAME, self::TEST_IP);
        $service->recordLoginAttempt(self::TEST_USERNAME, self::TEST_IP);
        $service->recordLoginAttempt(self::TEST_USERNAME, self::TEST_IP);

        // Should now be limited
        self::assertTrue($service->isLoginLimited(self::TEST_USERNAME, self::TEST_IP));
    }

    /**
     * Test that clearing login limit resets the counter.
     */
    public function testLoginRateLimitClearedOnSuccess()
    {
        $service = $this->createService(enabled: true, maxAttempts: 3);

        // Record failures to trigger limit
        $service->recordLoginAttempt(self::TEST_USERNAME, self::TEST_IP);
        $service->recordLoginAttempt(self::TEST_USERNAME, self::TEST_IP);
        $service->recordLoginAttempt(self::TEST_USERNAME, self::TEST_IP);
        self::assertTrue($service->isLoginLimited(self::TEST_USERNAME, self::TEST_IP));

        // Clear the limit (simulating successful login)
        $service->clearLoginLimit(self::TEST_USERNAME, self::TEST_IP);

        // Should no longer be limited
        self::assertFalse($service->isLoginLimited(self::TEST_USERNAME, self::TEST_IP));
    }

    /**
     * Test that password reset becomes rate limited after exceeding max attempts.
     */
    public function testPasswordResetRateLimitAfterMaxAttempts()
    {
        $service = $this->createService(enabled: true, maxAttempts: 3);

        // Initially not limited
        self::assertFalse($service->isPasswordResetLimited(self::TEST_IP));

        // Record 3 attempts
        $service->recordPasswordResetAttempt(self::TEST_IP);
        $service->recordPasswordResetAttempt(self::TEST_IP);
        $service->recordPasswordResetAttempt(self::TEST_IP);

        // Should now be limited
        self::assertTrue($service->isPasswordResetLimited(self::TEST_IP));
    }

    /**
     * Test that when rate limiting is disabled, requests are never limited.
     */
    public function testRateLimitDisabledReturnsNotLimited()
    {
        $service = $this->createService(enabled: false, maxAttempts: 1);

        // Even after recording an attempt, should not be limited
        $service->recordLoginAttempt(self::TEST_USERNAME, self::TEST_IP);

        // Rate limiting disabled means isRateLimitEnabled returns false
        self::assertFalse($service->isRateLimitEnabled());
    }

    /**
     * Test that username normalization works (case-insensitive).
     */
    public function testUsernameCaseNormalization()
    {
        $service = $this->createService(enabled: true, maxAttempts: 2);

        // Record attempts with different case variations
        $service->recordLoginAttempt('User@Example.com', self::TEST_IP);
        $service->recordLoginAttempt('USER@EXAMPLE.COM', self::TEST_IP);

        // Should be limited because both normalize to same key
        self::assertTrue($service->isLoginLimited('user@example.com', self::TEST_IP));
    }

    /**
     * Test that username normalization handles whitespace.
     */
    public function testUsernameWhitespaceNormalization()
    {
        $service = $this->createService(enabled: true, maxAttempts: 2);

        // Record attempts with whitespace variations
        $service->recordLoginAttempt('  admin  ', self::TEST_IP);
        $service->recordLoginAttempt('admin', self::TEST_IP);

        // Should be limited because whitespace is trimmed
        self::assertTrue($service->isLoginLimited('admin', self::TEST_IP));
    }

    /**
     * Test that Unicode NFC normalization works for usernames.
     * Composed (é = U+00E9) and decomposed (e + ́ = U+0065 U+0301) should be treated as same.
     */
    public function testUsernameUnicodeNfcNormalization()
    {
        if (!class_exists('Normalizer')) {
            $this->markTestSkipped('Normalizer class not available (intl extension required)');
        }

        $service = $this->createService(enabled: true, maxAttempts: 2);

        // Precomposed: café with é as single character (U+00E9)
        $precomposed = "caf\u{00E9}";

        // Decomposed: café with e + combining acute accent (U+0065 U+0301)
        $decomposed = "cafe\u{0301}";

        // Record attempts with both forms
        $service->recordLoginAttempt($precomposed, self::TEST_IP);
        $service->recordLoginAttempt($decomposed, self::TEST_IP);

        // Should be limited because NFC normalizes both to same form
        self::assertTrue($service->isLoginLimited($precomposed, self::TEST_IP));
        self::assertTrue($service->isLoginLimited($decomposed, self::TEST_IP));
    }

    /**
     * Test that IPv6 addresses in the same /64 network share rate limit.
     */
    public function testIpv6NormalizationSameNetwork()
    {
        $service = $this->createService(enabled: true, maxAttempts: 2);

        // Two different IPv6 addresses in the same /64 network
        $ipv6Address1 = '2001:db8:1234:5678::1';
        $ipv6Address2 = '2001:db8:1234:5678:abcd:ef01:2345:6789';

        // Record attempts from different addresses in same /64
        $service->recordLoginAttempt(self::TEST_USERNAME, $ipv6Address1);
        $service->recordLoginAttempt(self::TEST_USERNAME, $ipv6Address2);

        // Should be limited because both normalize to same /64 prefix
        self::assertTrue($service->isLoginLimited(self::TEST_USERNAME, $ipv6Address1));
        self::assertTrue($service->isLoginLimited(self::TEST_USERNAME, $ipv6Address2));
    }

    /**
     * Test that IPv6 addresses in different /64 networks have separate rate limits.
     */
    public function testIpv6NormalizationDifferentNetworks()
    {
        $service = $this->createService(enabled: true, maxAttempts: 2);

        // Two IPv6 addresses in different /64 networks
        $ipv6Network1 = '2001:db8:1234:5678::1';
        $ipv6Network2 = '2001:db8:1234:9999::1';  // Different /64 (5678 vs 9999)

        // Record attempts only on network 1
        $service->recordLoginAttempt(self::TEST_USERNAME, $ipv6Network1);
        $service->recordLoginAttempt(self::TEST_USERNAME, $ipv6Network1);

        // Network 1 should be limited
        self::assertTrue($service->isLoginLimited(self::TEST_USERNAME, $ipv6Network1));

        // Network 2 should NOT be limited (different /64)
        self::assertFalse($service->isLoginLimited(self::TEST_USERNAME, $ipv6Network2));
    }

    /**
     * Test that IPv4 addresses are not affected by IPv6 normalization.
     */
    public function testIpv4NotAffectedByNormalization()
    {
        $service = $this->createService(enabled: true, maxAttempts: 2);

        $ipv4Address1 = '192.168.1.100';
        $ipv4Address2 = '192.168.1.101';

        // Record attempts from one IPv4 address
        $service->recordLoginAttempt(self::TEST_USERNAME, $ipv4Address1);
        $service->recordLoginAttempt(self::TEST_USERNAME, $ipv4Address1);

        // First address should be limited
        self::assertTrue($service->isLoginLimited(self::TEST_USERNAME, $ipv4Address1));

        // Second address should NOT be limited (different IP)
        self::assertFalse($service->isLoginLimited(self::TEST_USERNAME, $ipv4Address2));
    }

    /**
     * Test that different usernames from the same IP have separate rate limits.
     */
    public function testDifferentUsernamesSeparateLimits()
    {
        $service = $this->createService(enabled: true, maxAttempts: 2);

        // Record attempts for user1
        $service->recordLoginAttempt('user1@example.com', self::TEST_IP);
        $service->recordLoginAttempt('user1@example.com', self::TEST_IP);

        // user1 should be limited
        self::assertTrue($service->isLoginLimited('user1@example.com', self::TEST_IP));

        // user2 should NOT be limited (different username)
        self::assertFalse($service->isLoginLimited('user2@example.com', self::TEST_IP));
    }

    /**
     * Test that empty username falls back to IP-only rate limit key.
     */
    public function testEmptyUsernameUsesIpOnlyKey()
    {
        $service = $this->createService(enabled: true, maxAttempts: 2);

        // Record attempts with empty username
        $service->recordLoginAttempt('', self::TEST_IP);
        $service->recordLoginAttempt('', self::TEST_IP);

        // Should be limited using IP-only key
        self::assertTrue($service->isLoginLimited('', self::TEST_IP));

        // Different username same IP should NOT be limited (different key)
        self::assertFalse($service->isLoginLimited('someuser', self::TEST_IP));
    }

    /**
     * Test that applyRateLimitDelay takes between 2-5 seconds.
     */
    public function testApplyRateLimitDelayTiming()
    {
        $service = $this->createService();

        $start = microtime(true);
        $service->applyRateLimitDelay();
        $elapsed = microtime(true) - $start;

        // Should be between 2 and 5 seconds (with small tolerance)
        self::assertGreaterThanOrEqual(2.0, $elapsed);
        self::assertLessThanOrEqual(5.5, $elapsed);
    }

    /**
     * Test that clearing password reset limit resets the counter.
     */
    public function testClearPasswordResetLimit()
    {
        $service = $this->createService(enabled: true, maxAttempts: 2);

        // Trigger limit
        $service->recordPasswordResetAttempt(self::TEST_IP);
        $service->recordPasswordResetAttempt(self::TEST_IP);
        self::assertTrue($service->isPasswordResetLimited(self::TEST_IP));

        // Clear limit
        $service->clearPasswordResetLimit(self::TEST_IP);

        // Should no longer be limited
        self::assertFalse($service->isPasswordResetLimited(self::TEST_IP));
    }

    /**
     * Test that getLoginRemainingAttempts returns correct count.
     */
    public function testGetLoginRemainingAttempts()
    {
        $service = $this->createService(enabled: true, maxAttempts: 5);

        // Initially should have all attempts remaining
        self::assertEquals(5, $service->getLoginRemainingAttempts(self::TEST_USERNAME, self::TEST_IP));

        // After 2 attempts, should have 3 remaining
        $service->recordLoginAttempt(self::TEST_USERNAME, self::TEST_IP);
        $service->recordLoginAttempt(self::TEST_USERNAME, self::TEST_IP);
        self::assertEquals(3, $service->getLoginRemainingAttempts(self::TEST_USERNAME, self::TEST_IP));
    }

    /**
     * Test that password reset rate limiting is IP-only (not username-based).
     * This prevents email enumeration attacks.
     */
    public function testPasswordResetIsIpOnlyNotUsernameBased()
    {
        $service = $this->createService(enabled: true, maxAttempts: 2);

        // Record attempts - password reset doesn't take username
        $service->recordPasswordResetAttempt(self::TEST_IP);
        $service->recordPasswordResetAttempt(self::TEST_IP);

        // Should be limited for ANY request from this IP
        self::assertTrue($service->isPasswordResetLimited(self::TEST_IP));

        // Different IP should NOT be limited
        self::assertFalse($service->isPasswordResetLimited('10.0.0.1'));
    }

    /**
     * Test that same username from different IPs have separate rate limits.
     */
    public function testSameUsernameFromDifferentIpsHaveSeparateLimits()
    {
        $service = $this->createService(enabled: true, maxAttempts: 2);

        // Record attempts from IP1
        $service->recordLoginAttempt(self::TEST_USERNAME, '192.168.1.1');
        $service->recordLoginAttempt(self::TEST_USERNAME, '192.168.1.1');

        // IP1 should be limited
        self::assertTrue($service->isLoginLimited(self::TEST_USERNAME, '192.168.1.1'));

        // Same user from IP2 should NOT be limited
        self::assertFalse($service->isLoginLimited(self::TEST_USERNAME, '192.168.1.2'));
    }

    /**
     * Test boundary condition: exactly at max attempts.
     */
    public function testBoundaryExactlyAtMaxAttempts()
    {
        $service = $this->createService(enabled: true, maxAttempts: 3);

        // Record exactly 2 attempts (one less than max)
        $service->recordLoginAttempt(self::TEST_USERNAME, self::TEST_IP);
        $service->recordLoginAttempt(self::TEST_USERNAME, self::TEST_IP);

        // Should NOT be limited yet
        self::assertFalse($service->isLoginLimited(self::TEST_USERNAME, self::TEST_IP));

        // Record 3rd attempt (at max)
        $service->recordLoginAttempt(self::TEST_USERNAME, self::TEST_IP);

        // NOW should be limited
        self::assertTrue($service->isLoginLimited(self::TEST_USERNAME, self::TEST_IP));
    }

    /**
     * Test that default values are used when site settings return null.
     */
    public function testDefaultValuesWhenSiteSettingsNull()
    {
        $siteMock = Mockery::mock(Site::class);
        $siteMock->shouldReceive('getData')->with('rateLimitEnabled')->andReturn(true);
        $siteMock->shouldReceive('getData')->with('rateLimitMaxAttempts')->andReturn(null);
        $siteMock->shouldReceive('getData')->with('rateLimitDecaySeconds')->andReturn(null);

        $service = RateLimitingService::getInstance($siteMock);

        // Should use default: 5 attempts (DEFAULT_MAX_ATTEMPTS)
        for ($i = 0; $i < 5; $i++) {
            self::assertFalse($service->isLoginLimited(self::TEST_USERNAME, self::TEST_IP));
            $service->recordLoginAttempt(self::TEST_USERNAME, self::TEST_IP);
        }
        self::assertTrue($service->isLoginLimited(self::TEST_USERNAME, self::TEST_IP));
    }

    /**
     * Test that invalid IPv6 addresses are handled gracefully.
     */
    public function testInvalidIpv6AddressHandling()
    {
        $service = $this->createService(enabled: true, maxAttempts: 2);

        // Invalid IPv6 address should be treated as-is (not crash)
        $invalidIpv6 = 'not-a-valid-ipv6';

        $service->recordLoginAttempt(self::TEST_USERNAME, $invalidIpv6);
        $service->recordLoginAttempt(self::TEST_USERNAME, $invalidIpv6);

        // Should still work (invalid IP passed through unchanged)
        self::assertTrue($service->isLoginLimited(self::TEST_USERNAME, $invalidIpv6));
    }
}
