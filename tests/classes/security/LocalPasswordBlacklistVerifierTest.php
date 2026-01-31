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

        // Create a test blacklist file with common weak passwords
        $this->testBlacklistPath = sys_get_temp_dir() . '/test_blacklist_' . uniqid() . '.txt';
        file_put_contents($this->testBlacklistPath, implode("\n", [
            '# This is a comment',
            'password123',
            'qwerty',
            'letmein',
            '123456',
            // Unicode passwords for testing mb_strtolower
            'über',          // German umlaut
            'пароль',        // Cyrillic "password"
            'contraseña',    // Spanish with ñ
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
     * Test that when blacklist file doesn't exist, all passwords pass.
     */
    public function testNonExistentBlacklistFilePassesAllPasswords()
    {
        /** @var LocalPasswordBlacklistVerifier&\Mockery\MockInterface $verifier */
        $verifier = Mockery::mock(LocalPasswordBlacklistVerifier::class)
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $verifier->shouldReceive('getBlacklistFilePath')
            ->andReturn('/non/existent/path/blacklist.txt');

        // Any password should pass when file doesn't exist
        self::assertTrue($verifier->verify(['value' => 'password123']));
        self::assertTrue($verifier->verify(['value' => 'qwerty']));
    }

    /**
     * Test that when blacklist file is empty, all passwords pass.
     */
    public function testEmptyBlacklistFilePassesAllPasswords()
    {
        $emptyFilePath = sys_get_temp_dir() . '/empty_blacklist_' . uniqid() . '.txt';
        file_put_contents($emptyFilePath, '');

        /** @var LocalPasswordBlacklistVerifier&\Mockery\MockInterface $verifier */
        $verifier = Mockery::mock(LocalPasswordBlacklistVerifier::class)
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $verifier->shouldReceive('getBlacklistFilePath')
            ->andReturn($emptyFilePath);

        self::assertTrue($verifier->verify(['value' => 'password123']));
        self::assertTrue($verifier->verify(['value' => 'qwerty']));

        unlink($emptyFilePath);
    }

    /**
     * Test that when blacklist file contains only comments, all passwords pass.
     */
    public function testBlacklistFileWithOnlyCommentsPassesAllPasswords()
    {
        $commentsOnlyPath = sys_get_temp_dir() . '/comments_blacklist_' . uniqid() . '.txt';
        file_put_contents($commentsOnlyPath, "# Comment 1\n# Comment 2\n# Comment 3\n");

        /** @var LocalPasswordBlacklistVerifier&\Mockery\MockInterface $verifier */
        $verifier = Mockery::mock(LocalPasswordBlacklistVerifier::class)
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $verifier->shouldReceive('getBlacklistFilePath')
            ->andReturn($commentsOnlyPath);

        self::assertTrue($verifier->verify(['value' => 'password123']));
        self::assertTrue($verifier->verify(['value' => 'qwerty']));

        unlink($commentsOnlyPath);
    }

    /**
     * Test that comment lines in blacklist are properly ignored.
     */
    public function testCommentsAreIgnoredInBlacklist()
    {
        // The blacklist has "# This is a comment"
        // Verify "This is a comment" is NOT blocked (the # should exclude the line)
        self::assertTrue($this->verifier->verify(['value' => 'This is a comment']));
        self::assertTrue($this->verifier->verify(['value' => '# This is a comment']));
    }

    /**
     * Test that empty password fails validation.
     */
    public function testEmptyPasswordFails()
    {
        self::assertFalse($this->verifier->verify(['value' => '']));
    }

    /**
     * Test that null password fails validation.
     */
    public function testNullPasswordFails()
    {
        self::assertFalse($this->verifier->verify(['value' => null]));
    }

    /**
     * Test that missing 'value' key fails validation.
     */
    public function testMissingValueKeyFails()
    {
        self::assertFalse($this->verifier->verify([]));
        self::assertFalse($this->verifier->verify(['threshold' => 0]));
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

    /**
     * Test that Unicode passwords are handled correctly with mb_strtolower.
     * This ensures non-Latin characters are properly lowercased for comparison.
     */
    public function testUnicodeCaseInsensitiveBlacklistCheck()
    {
        // German umlaut - ÜBER should match über in blacklist
        self::assertFalse($this->verifier->verify(['value' => 'ÜBER']));
        self::assertFalse($this->verifier->verify(['value' => 'Über']));
        self::assertFalse($this->verifier->verify(['value' => 'über']));

        // Cyrillic - ПАРОЛЬ should match пароль in blacklist
        self::assertFalse($this->verifier->verify(['value' => 'ПАРОЛЬ']));
        self::assertFalse($this->verifier->verify(['value' => 'Пароль']));
        self::assertFalse($this->verifier->verify(['value' => 'пароль']));

        // Spanish with ñ - CONTRASEÑA should match contraseña in blacklist
        self::assertFalse($this->verifier->verify(['value' => 'CONTRASEÑA']));
        self::assertFalse($this->verifier->verify(['value' => 'Contraseña']));
        self::assertFalse($this->verifier->verify(['value' => 'contraseña']));

        // Non-blacklisted Unicode password should pass
        self::assertTrue($this->verifier->verify(['value' => 'SécürePäss123!']));
    }
}
