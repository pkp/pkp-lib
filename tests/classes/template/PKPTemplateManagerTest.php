<?php

/**
 * @file tests/classes/template/PKPTemplateManagerTest.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPTemplateManagerTest
 *
 * @ingroup tests_classes_template
 *
 * @see PKPTemplateManager
 *
 * @brief Tests for the role check that decides if the editorial dashboard is shown in the main navigation.
 */

namespace PKP\tests\classes\template;

use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PKP\security\Role;
use PKP\template\PKPTemplateManager;
use PKP\tests\PKPTestCase;

#[CoversMethod(PKPTemplateManager::class, 'hasEditorialDashboardAccess')]
class PKPTemplateManagerTest extends PKPTestCase
{
    #[DataProvider('editorialDashboardAccessProvider')]
    public function testHasEditorialDashboardAccess(array $userRoles, bool $expected): void
    {
        $this->assertSame($expected, PKPTemplateManager::hasEditorialDashboardAccess($userRoles));
    }

    public static function editorialDashboardAccessProvider(): array
    {
        return [
            'no roles' => [[], false],
            'site admin only' => [[Role::ROLE_ID_SITE_ADMIN], false],
            'site admin without manager role but with author role' => [[Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_AUTHOR], false],
            'site admin without manager role but with reviewer role' => [[Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_REVIEWER], false],
            'author' => [[Role::ROLE_ID_AUTHOR], false],
            'reviewer' => [[Role::ROLE_ID_REVIEWER], false],
            'manager' => [[Role::ROLE_ID_MANAGER], true],
            'sub editor' => [[Role::ROLE_ID_SUB_EDITOR], true],
            'assistant' => [[Role::ROLE_ID_ASSISTANT], true],
            'site admin and manager' => [[Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER], true],
            'site admin and sub editor' => [[Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_SUB_EDITOR], true],
            'author and assistant' => [[Role::ROLE_ID_AUTHOR, Role::ROLE_ID_ASSISTANT], true],
        ];
    }
}
