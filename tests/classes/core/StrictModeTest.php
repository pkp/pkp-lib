<?php

/**
 * @file tests/classes/core/StrictModeTest.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class StrictModeTest
 *
 * @brief Tests the app strict mode
 */

namespace PKP\tests\classes\core;

use APP\core\Application;
use PKP\core\PKPContainer;
use PKP\tests\PKPTestCase;
use Throwable;

class StrictModeTest extends PKPTestCase
{
    public function testStrictModeEnabledInTests(): void
    {
        $container = PKPContainer::getInstance();

        // Strict mode should be forced ON for all PHPUnit tests
        $this->assertTrue(
            $container->getApplicationStrictModeStatus(),
            'Strict mode should be enabled for PHPUnit tests'
        );
    }

    public function testGlobalConstantsNotDefined(): void
    {
        $this->assertFalse(
            defined('ASSOC_TYPE_SUBMISSION'),
            'Global constants should not be defined in strict mode'
        );

        $this->assertTrue(
            defined('PKP\core\PKPApplication::ASSOC_TYPE_SUBMISSION'),
            'Class constants should always be available'
        );
    }

    public function testSettingGlobalConstInStrictModeThrowsException()
    {
        $container = PKPContainer::getInstance();

        $this->expectException(Throwable::class);
        $container->registerGlobalConstants(Application::class, ['ASSOC_TYPE_']);
    }

    public function testStrictModeStatusCanBeToggled()
    {
        $container = PKPContainer::getInstance();

        $container->setApplicationStrictModeStatus(false);
        $this->assertFalse(
            $container->getApplicationStrictModeStatus(),
            'Strict mode should be disable'
        );

        $container->setApplicationStrictModeStatus(true);
        $this->assertTrue(
            $container->getApplicationStrictModeStatus(),
            'Strict mode should be enable'
        );
    }

    public function testGlobalConstCanBeSetWhenStrictModeDisbale()
    {
        $container = PKPContainer::getInstance();
        $container->setApplicationStrictModeStatus(false);

        $class = new class {
            public const MY_TEST_CLASS_CONST = 'MY_TEST_CLASS_CONST_VALUE';
        };

        $container->registerGlobalConstants(get_class($class), ['MY_TEST_CLASS_CONST']);

        $this->assertTrue(
            defined('MY_TEST_CLASS_CONST'),
            'Global const is registered when strict mode is disable'
        );

        /**
         * @disregard P1011 PHP Intelephense error suppression
         * const MY_TEST_CLASS_CONST should be available as global const
         */
        $this->assertEquals(
            MY_TEST_CLASS_CONST,
            $class::MY_TEST_CLASS_CONST,
            'Registered global const form class const has proper value set when strict mode disable'
        );
    }
}
