<?php

/**
 * @file tests/classes/template/TemplateManagerHelpUrlTest.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class TemplateManagerHelpUrlTest
 *
 * @brief Tests for the TemplateManager::helpUrl hook
 */

namespace PKP\tests\classes\template;

use APP\core\Application;
use APP\template\TemplateManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PKP\plugins\Hook;
use PKP\tests\PKPTestCase;

class TemplateManagerHelpUrlTest extends PKPTestCase
{
    protected function tearDown(): void
    {
        Hook::clear('TemplateManager::helpUrl');
        parent::tearDown();
    }

    private function templateManager(): TemplateManager
    {
        return TemplateManager::getManager($this->mockRequest());
    }

    public function testDefaultHelpUrlIsTheApplicationHelpUrl(): void
    {
        $this->assertSame(Application::get()->getHelpUrl(), $this->templateManager()->getHelpUrl());
    }

    public function testHookCanReplaceHelpUrl(): void
    {
        Hook::add('TemplateManager::helpUrl', function (string $hookName, array $args): bool {
            $helpUrl = &$args[0];
            $helpUrl = 'https://help.example.org/';
            return Hook::CONTINUE;
        });

        $this->assertSame('https://help.example.org/', $this->templateManager()->getHelpUrl());
    }

    public static function invalidHelpUrls(): array
    {
        return [
            'null' => [null],
            'empty string' => [''],
            'integer' => [42],
        ];
    }

    #[DataProvider('invalidHelpUrls')]
    public function testInvalidHookValueFallsBackToApplicationHelpUrl(mixed $invalidHelpUrl): void
    {
        Hook::add('TemplateManager::helpUrl', function (string $hookName, array $args) use ($invalidHelpUrl): bool {
            $helpUrl = &$args[0];
            $helpUrl = $invalidHelpUrl;
            return Hook::CONTINUE;
        });

        $this->assertSame(Application::get()->getHelpUrl(), $this->templateManager()->getHelpUrl());
    }
}
