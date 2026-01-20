<?php

/**
 * @file tests/classes/security/LocalPasswordBlacklistVerifierTest.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class LocalPasswordBlacklistVerifierTest
 *
 * @brief Test class for LocalPasswordBlacklistVerifier.
 */

namespace PKP\tests\classes\security;

use Illuminate\Support\Facades\Cache;
use Mockery;
use PKP\security\LocalPasswordBlacklistVerifier;
use PKP\tests\PKPTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(LocalPasswordBlacklistVerifier::class)]
class LocalPasswordBlacklistVerifierTest extends PKPTestCase
{
    private string $testBlacklistPath;

    /** @var LocalPasswordBlacklistVerifier&\Mockery\MockInterface */
    private LocalPasswordBlacklistVerifier $verifier;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear cache before each test
        Cache::flush();

        // Create a test blacklist file with common weak passwords
        $this->testBlacklistPath = sys_get_temp_dir() . '/test_blacklist_' . uniqid() . '.txt';
        file_put_contents($this->testBlacklistPath, implode("\n", [
            '# This is a comment',
            'password123',
            'qwerty',
            'letmein',
            '123456',
        ]));

        // Create verifier with mocked path
        /**
         * @disregard P1006 Intelephense error suppression
         * @disregard P1013 Intelephense error suppression
         */
        $this->verifier = Mockery::mock(LocalPasswordBlacklistVerifier::class)
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $this->verifier->shouldReceive('getBlacklistFilePath')
            ->andReturn($this->testBlacklistPath);
    }

    protected function tearDown(): void
    {
        // Clean up temp file
        if (file_exists($this->testBlacklistPath)) {
            unlink($this->testBlacklistPath);
        }

        parent::tearDown();
    }

    /**
     * Test that a blacklisted password returns false (not safe).
     */
    public function testBlacklistedPasswordFails()
    {
        $result = $this->verifier->verify(['value' => 'password123']);
        self::assertFalse($result);

        $result = $this->verifier->verify(['value' => 'qwerty']);
        self::assertFalse($result);
    }

    /**
     * Test that a safe password returns true (safe to use).
     */
    public function testSafePasswordPasses()
    {
        $result = $this->verifier->verify(['value' => 'MySecure@Pass123']);
        self::assertTrue($result);

        $result = $this->verifier->verify(['value' => 'UniqueP@ssw0rd!']);
        self::assertTrue($result);
    }

    /**
     * Test that blacklist check is case-insensitive.
     */
    public function testCaseInsensitiveBlacklistCheck()
    {
        // All case variations should be blocked
        self::assertFalse($this->verifier->verify(['value' => 'PASSWORD123']));
        self::assertFalse($this->verifier->verify(['value' => 'Password123']));
        self::assertFalse($this->verifier->verify(['value' => 'QWERTY']));
        self::assertFalse($this->verifier->verify(['value' => 'Qwerty']));
    }
}
