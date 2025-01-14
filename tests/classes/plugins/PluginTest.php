<?php

/**
 * @file tests/classes/plugins/PluginTest.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PluginTest
 *
 * @brief Tests for the Plugin class.
 */

namespace PKP\tests\classes\plugins;

use Exception;
use PKP\plugins\Hook;
use PKP\plugins\Plugin;
use PKP\plugins\PluginRegistry;
use PKP\tests\PKPTestCase;

class PluginTest extends PKPTestCase
{
    public function testFailToInstantiate()
    {
        $plugin = new class(false) extends Plugin {
            public static $triggered = false;
            public function __construct(bool $run = true)
            {
                if (!$run) {
                    return;
                }
                static::$triggered = true;
                throw new Exception();
            }

            public function getName()
            {
            }

            public function getDisplayName()
            {
            }

            public function getDescription()
            {
            }
        };

        class_alias($plugin::class, 'APP\plugins\generic\failToInstantiate\FailToInstantiatePlugin');
        PluginRegistry::loadPlugin('generic', 'failToInstantiate');
        static::assertEquals($plugin::$triggered, true);
    }

    public function testFailToRegister()
    {
        $plugin = new class extends Plugin {
            public static $triggered = false;

            public function register($category, $path, $mainContextId = null)
            {
                static::$triggered = true;
                throw new Exception();
            }

            public function getName()
            {
            }

            public function getDisplayName()
            {
            }

            public function getDescription()
            {
            }
        };

        class_alias($plugin::class, 'APP\plugins\generic\failToRegister\FailToRegisterPlugin');
        PluginRegistry::loadPlugin('generic', 'failToRegister');
        static::assertEquals($plugin::$triggered, true);
    }

    public function testFailToHandleHook()
    {
        $plugin = new class(false) extends Plugin {
            public static $counter = 0;

            public function __construct(bool $run = true)
            {
                if (!$run) {
                    return;
                }

                Hook::add('testPluginHook', function () {
                    ++static::$counter;
                    return Hook::CONTINUE;
                });
                Hook::add('testPluginHook', function () {
                    ++static::$counter;
                    throw new Exception();
                });
                Hook::add('testPluginHook', function () {
                    ++static::$counter;
                    return Hook::ABORT;
                });
            }

            public function getName()
            {
            }

            public function getDisplayName()
            {
            }

            public function getDescription()
            {
            }
        };

        class_alias($plugin::class, 'APP\plugins\generic\failToHandleHook\FailToHandleHookPlugin');
        PluginRegistry::loadPlugin('generic', 'failToHandleHook');
        Hook::call('testPluginHook');
        static::assertEquals($plugin::$counter, 3);
    }
}
