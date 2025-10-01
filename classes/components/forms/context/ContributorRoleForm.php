<?php

/**
 * @file classes/components/form/context/ContributorRoleForm.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ContributorRoleForm
 *
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for adding and editing contributor role.
 */

namespace PKP\components\forms\context;

use APP\core\Application;
use APP\facades\Repo;
use PKP\author\contributorRole\ContributorRole;
use PKP\author\contributorRole\ContributorRoleIdentifier;
use PKP\components\forms\FieldSelect;
use PKP\components\forms\FieldText;
use PKP\components\forms\FormComponent;
use PKP\context\Context;

class ContributorRoleForm extends FormComponent
{
    public const FORM_CONTRIBUTOR_ROLE = 'editContributorRole';
    public $id = self::FORM_CONTRIBUTOR_ROLE;

    /**
     * Constructor
     */
    public function __construct(string $action, Context $context)
    {
        $this->action = $action;
        $this->locales = collect($context->getSupportedLocaleNames())
            ->map(fn (string $name, string $locale): array => ['key' => $locale, 'label' => $name])
            ->values()
            ->toArray();
        $this->method = 'POST';

        // Available options only
        $roleOptions = collect(ContributorRoleIdentifier::getRoles())
            ->diff(Repo::contributorRole()
                ->getSchemaMap()
                ->summarizeMany(ContributorRole::query()->withContextId($context->getId())->get())
                ->pluck('identifier')
            )
            ->values();

        $this->addField(new FieldSelect('identifier', [
            'label' => __('manager.contributorRoles.identifier'),
            'options' => $roleOptions
                ->map(fn (string $identifier): array => ['label' => $identifier, 'value' => $identifier])
                ->toArray(),
            'isRequired' => true,
            'value' => $roleOptions->first(),
        ]))
        ->addField(new FieldText('name', [
            'label' => __('manager.contributorRoles.name'),
            'isRequired' => true,
            'isMultilingual' => true,
        ]));
    }
}
