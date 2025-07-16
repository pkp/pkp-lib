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

use APP\facades\Repo;;
use PKP\author\creditRole\CreditRoleDegree;

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
     */
    protected function mapCreditRoles(): array
    {
        ['roles' => $creditRoleTerms, 'degrees' => $degrees] = Repo::CreditRole()->getTerms();
        return [
            'roles' => collect($creditRoleTerms)
                ->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])
                ->values()
                ->toArray(),
            'degrees' => collect($degrees)
                ->map(fn (string $translation, string $label): array => ['value' => CreditRoleDegree::toValue($label), 'label' => $translation])
                ->values()
                ->toArray(),
        ];
    }
}
