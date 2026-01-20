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
    public function testUsernameNormalization()
    {
        $service = $this->createService(enabled: true, maxAttempts: 2);

        // Record attempts with different case variations
        $service->recordLoginAttempt('User@Example.com', self::TEST_IP);
        $service->recordLoginAttempt('USER@EXAMPLE.COM', self::TEST_IP);

        // Should be limited because both normalize to same key
        self::assertTrue($service->isLoginLimited('user@example.com', self::TEST_IP));
    }
}
