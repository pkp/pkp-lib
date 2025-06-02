<?php

/**
 * @file classes/components/form/FieldCreditRoles.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FieldCreditRoles
 *
 * @ingroup classes_controllers_form
 *
 * @brief A field for author Credit Roles.
 */

namespace PKP\components\forms;

use PKP\author\Author;
use PKP\author\creditRoles\CreditRoleDegree;

class FieldCreditRoles extends Field
{
    /** @copydoc Field::$component */
    public $component = 'field-credit-roles';

    /**
     * @copydoc Field::getConfig()
     */
    public function getConfig()
    {
        $config = parent::getConfig();
        $config['value'] = $this->value ?? [];
        $config['options'] = $this->mapCreditRoles();
        return $config;
    }

    /**
     * Get roles to the contributor form
     * @param array $values, list of roles
     */
    protected function mapCreditRoles(): array
    {
        $mapToForm = fn (array $roles): array => collect($roles)
            ->map(fn (string $label, string $value): array => ['value' => $value === CreditRoleDegree::NO_DEGREE->value ? null : $value, 'label' => $label])
            ->values()
            ->toArray();
        ['roles' => $creditRoleTerms, 'degrees' => $degrees] = Author::getCreditRoleTerms();
        return [
            'roles' => $mapToForm($creditRoleTerms),
            'degrees' => $mapToForm($degrees),
        ];
    }
}
