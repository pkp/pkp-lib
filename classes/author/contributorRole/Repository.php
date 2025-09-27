<?php

/**
 * @file classes/author/contributorRole/Repository.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Repository
 *
 * @brief A repository to manage actions related to contributor roles
 */

namespace PKP\author\contributorRole;

use APP\core\Application;
use Illuminate\Validation\Rule;
use PKP\author\contributorRole\ContributorRoleIdentifier;
use PKP\context\Context;
use PKP\facades\Locale;
use PKP\validation\ValidatorFactory;

class Repository
{
    // Add or edit. Use role id to edit. Use identifier and context id to add or edit.
    public function add(array $translations, ?string $identifier = null, ?int $contextId = null, ?int $roleId = null): int
    {
        return ContributorRole::updateOrCreate(
            [
                ...$roleId
                    ? ['contributor_role_id' => $roleId] /* Edit */
                    : ['context_id' => $contextId, 'contributor_role_identifier' => $identifier] /* Add/Edit */
            ],
            [
                'name' => $translations,
                ...($roleId && $identifier) /* Edit */
                    ? ['contributor_role_identifier' => $identifier]
                    : []
            ]
        )
        ->id;
    }

    public function delete(?int $contextId = null, ?string $identifier = null, ?int $roleId = null): void
    {
        if ($roleId) {
            ContributorRole::query()
                ->withRoleId($roleId)
                ->delete();
            return;
        }
        ContributorRole::query()
            ->withContextId($contextId)
            ->withIdentifiers([$identifier])
            ->delete();
    }

    /**
     * Get localized, or all, context's contributor roles with settings in assoc: identifier => translation(s).
     */
    public function getByContextId(int $contextId, ?string $locale = null): array
    {
        return ContributorRole::query()
            ->withContextId($contextId)
            ->get()
            ->mapWithKeys(fn (ContributorRole $role) => [
                $role->contributor_role_identifier => $locale ? $role->getLocalizedData('name', $locale) : $role->name
            ])
            ->toArray();
    }

    /**
     * Get localized, or all, context's contributor roles with settings in assoc: roleId => [identifier => translation(s)].
     */
    public function getByContextIdWithRoleId(int $contextId, ?string $locale = null): array
    {
        return ContributorRole::query()
            ->withContextId($contextId)
            ->get()
            ->mapWithKeys(fn (ContributorRole $role) => [
                $role->contributor_role_id => [
                    'identifier' => $role->contributor_role_identifier,
                    'name' => $locale ? $role->getLocalizedData('name', $locale) : $role->name,
                ]
            ])
            ->toArray();
    }

    /**
     * Get localized, or all, context's contributor role with settings in assoc: identifier => translation(s).
     */
    public function getByContextIdAndIdentifiers(int $contextId, array $identifiers, ?string $locale = null): array
    {
        return ContributorRole::query()
            ->withContextId($contextId)
            ->withIdentifiers($identifiers)
            ->get()
            ->mapWithKeys(fn (ContributorRole $role) => [
                $role->contributor_role_identifier => $locale ? $role->getLocalizedData('name', $locale) : $role->name
            ])
            ->toArray();
    }

    /**
     * Get context's contributor role with settings by identifier in assoc: identifier => translation(s).
     */
    public function getByRoleId(int $roleId, ?string $locale = null): array
    {
        return ContributorRole::query()
            ->withRoleId($roleId)
            ->get()
            ->mapWithKeys(fn (ContributorRole $role) => [
                $role->contributor_role_identifier => $locale ? $role->getLocalizedData('name', $locale) : $role->name
            ])
            ->toArray();
    }

    public function validate(array $params, ?int $roleId, Context $context): array
    {
        $locales = $context->getSupportedLocales();
        $identifiers = ContributorRoleIdentifier::getRoles();
        // Identifier required when adding
        // Name must have all the ui locales, and each filled
        $validator = ValidatorFactory::make(
            $params, [
            'identifier' => [
                Rule::requiredIf(fn (): bool => !$roleId),
                Rule::in($identifiers),
            ],
            'name' => [
                'required',
                'array',
                function (string $attribute, array $value, \Closure $fail) use ($locales) {
                    if (count(array_filter($value)) !== count($locales) || array_diff(array_keys($value), $locales)) {
                        return $fail(__('manager.contributorRoles.error.nameRequired'));
                    }
                    return true;
                }
            ],
        ]);

        return app()->get('schema')->formatValidationErrors($validator->errors());
    }
}
