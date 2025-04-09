<?php

/**
 * @file classes/emailTemplate/Repository.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Repository
 *
 * @brief A repository to find and manage email templates.
 */

namespace PKP\emailTemplate;

use APP\core\Application;
use APP\emailTemplate\DAO;
use APP\facades\Repo;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use PKP\context\Context;
use PKP\core\PKPRequest;
use PKP\plugins\Hook;
use PKP\security\Role;
use PKP\services\PKPSchemaService;
use PKP\user\User;
use PKP\userGroup\UserGroup;
use PKP\validation\ValidatorFactory;

class Repository
{
    public DAO $dao;

    // The name of the class to map this entity to its schema
    public string $schemaMap = maps\Schema::class;

    protected PKPRequest $request;

    protected PKPSchemaService $schemaService;

    public function __construct(DAO $dao, PKPRequest $request, PKPSchemaService $schemaService)
    {
        $this->dao = $dao;
        $this->request = $request;
        $this->schemaService = $schemaService;
    }

    /** @copydoc DAO::newDataObject() */
    public function newDataObject(array $params = []): Emailtemplate
    {
        $object = $this->dao->newDataObject();
        if (!empty($params)) {
            $object->setAllData($params);
        }
        return $object;
    }

    /** @copydoc DAO::getByKey() */
    public function getByKey(?int $contextId = null, string $key): ?EmailTemplate
    {
        return $this->dao->getByKey($contextId, $key);
    }

    /** @copydoc DAO::getCollector() */
    public function getCollector(?int $contextId = null): Collector
    {
        return app(Collector::class, ['contextId' => $contextId]);
    }

    /**
     * Get an instance of the map class for mapping
     * announcements to their schema
     */
    public function getSchemaMap(): maps\Schema
    {
        return app('maps')->withExtensions($this->schemaMap);
    }

    /**
     * Validate properties for an email template
     *
     * Perform validation checks on data used to add or edit an email template.
     *
     * @param array $props A key/value array with the new data to validate
     *
     * @return array A key/value array with validation errors. Empty if no errors
     *
     * @hook EmailTemplate::validate [[&$errors, $object, $props, $allowedLocales, $primaryLocale]]
     */
    public function validate(?EmailTemplate $object, array $props, Context $context): array
    {
        $primaryLocale = $context->getData('primaryLocale');
        $allowedLocales = $context->getData('supportedFormLocales');

        $errors = [];
        $validator = ValidatorFactory::make(
            $props,
            $this->schemaService->getValidationRules(PKPSchemaService::SCHEMA_EMAIL_TEMPLATE, $allowedLocales)
        );

        // Check required fields
        ValidatorFactory::required(
            $validator,
            $object,
            $this->schemaService->getRequiredProps(PKPSchemaService::SCHEMA_EMAIL_TEMPLATE),
            $this->schemaService->getMultilingualProps(PKPSchemaService::SCHEMA_EMAIL_TEMPLATE),
            $allowedLocales,
            $primaryLocale
        );

        if (isset($props['contextId'])) {
            $validator->after(function ($validator) use ($props, $context) {
                if (!app()->get('context')->exists($props['contextId'])) {
                    $validator->errors()->add('contextId', __('api.contexts.404.contextNotFound'));
                }
                if ($context->getId() !== $props['contextId']) {
                    $validator->errors()->add('contextId', __('api.emailTemplates.400.invalidContext'));
                }
            });
        }

        // An email template can only be an alternate to a mailable's default email template
        if (isset($props['alternateTo'])) {
            $validator->after(function ($validator) use ($props, $context) {
                $mailableExists = Repo::mailable()
                    ->getMany($context)
                    ->contains(fn (string $mailable) => $mailable::getEmailTemplateKey() === $props['alternateTo']);

                if (!$mailableExists) {
                    $validator->errors()->add('alternateTo', __('api.emailTemplates.400.invalidAlternateTo'));
                }
            });
        }

        //  If assignedUserGroupIds were passed to limit email access, check that the user groups exists within the context
        if (isset($props['assignedUserGroupIds'])) {
            $validator->after(function () use ($validator, $props, $context) {
                $existingGroupIds = UserGroup::withContextIds([$context->getId()])
                    ->withUserGroupIds($props['assignedUserGroupIds'])
                    ->pluck('user_group_id')
                    ->all();

                if (!empty(array_diff($existingGroupIds, $props['assignedUserGroupIds']))) {
                    $validator->errors()->add('assignedUserGroupIds', __('api.emailTemplates.404.userGroupIds'));
                }
            });
        }

        // Check for input from disallowed locales
        ValidatorFactory::allowedLocales($validator, $this->schemaService->getMultilingualProps($this->dao->schema), $allowedLocales);

        if ($validator->fails()) {
            $errors = $this->schemaService->formatValidationErrors($validator->errors());
        }

        Hook::call('EmailTemplate::validate', [&$errors, $object, $props, $allowedLocales, $primaryLocale]);

        return $errors;
    }

    /**
     * Add a new email template
     *
    * @hook EmailTemplate::add [[$emailTemplate]]
    */
    public function add(EmailTemplate $emailTemplate): string
    {
        $key = $this->dao->insert($emailTemplate);

        Hook::call('EmailTemplate::add', [$emailTemplate]);

        return $key;
    }

    /** @copydoc DAO::update() */
    public function edit(EmailTemplate $emailTemplate, array $params)
    {
        $newEmailTemplate = clone $emailTemplate;
        $newEmailTemplate->setAllData(array_merge($newEmailTemplate->_data, $params));

        Hook::call('EmailTemplate::edit', [$newEmailTemplate, $emailTemplate, $params]);

        if ($newEmailTemplate->getId()) {
            $this->dao->update($newEmailTemplate);
        } else {
            $this->dao->insert($newEmailTemplate);
        }
    }

    /** @copydoc DAO::delete() */
    public function delete(EmailTemplate $emailTemplate)
    {
        Hook::call('EmailTemplate::delete::before', [&$emailTemplate]);
        $this->dao->delete($emailTemplate);
        Hook::call('EmailTemplate::delete', [&$emailTemplate]);
    }

    /**
     * Delete a collection of email templates
     */
    public function deleteMany(Collector $collector): void
    {
        foreach ($collector->getMany() as $emailTemplate) {
            $this->delete($emailTemplate);
            $this->deleteTemplateGroupAccess(Application::get()->getRequest()->getContext()->getId(), [$emailTemplate->getData('key')]);
        }
    }

    /**
     * Remove all custom templates and template modifications. Resets the
     * email template settings to their installed defaults.
     *
     * @return array List of keys that were deleted or reset
     *
     * @hook EmailTemplate::restoreDefaults [[&$deletedKeys, $contextId]]
     */
    public function restoreDefaults($contextId): array
    {
        $results = $this->getCollector($contextId)
            ->isModified(true)
            ->getMany();

        $deletedKeys = [];
        $results->each(function ($emailTemplate) use ($contextId, &$deletedKeys) {
            $deletedKeys[] = $emailTemplate->getData('key');
            $this->delete($emailTemplate);
        });

        $this->dao->installAlternateEmailTemplates($contextId);
        Repo::emailTemplate()->restoreTemplateUserGroupAccess($contextId, $deletedKeys);
        Hook::call('EmailTemplate::restoreDefaults', [&$deletedKeys, $contextId]);
        return $deletedKeys;
    }

    /***
     * Gets the IDs of the user groups assigned to an email template.
     */
    public function getAssignedGroupsIds(string $templateKey, int $contextId): array
    {
        return EmailTemplateAccessGroup::withEmailKey([$templateKey])
            ->withContextId($contextId)
            ->whereNot('user_group_id', null)
            ->pluck('user_group_id')
            ->all();
    }

    /***
     * Checks if an Email Template is unrestricted.
     */
    public function isTemplateUnrestricted(string $templateKey, int $contextId): bool
    {
        return !!EmailTemplateAccessGroup::withEmailKey([$templateKey])
            ->withContextId($contextId)
            ->whereNull('user_group_id')
            ->first();
    }

    /**
     * Checks if an email template is accessible to a user. A template is accessible if it is assigned to a user group that the user belongs to or if the template is unrestricted.
     */
    public function isTemplateAccessibleToUser(User $user, EmailTemplate $template, int $contextId): bool
    {
        if ($user->hasRole([Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER,], $contextId) || $this->isTemplateUnrestricted($template->getData('key'), $contextId)) {
            return true;
        }

        $userUserGroups = Repo::userGroup()->userUserGroups($user->getId(), $contextId)->all();
        $templateUserGroups = $this->getAssignedGroupsIds($template->getData('key'), $contextId);

        foreach ($userUserGroups as $userGroup) {
            if (in_array($userGroup->id, $templateUserGroups)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Filters a list of EmailTemplates to return only those accessible by a specified user.
     *
     * @param Enumerable $templates List of EmailTemplates to filter.
     * @param User $user The user whose access level is used for filtering.
     *
     * @return Collection Filtered list of EmailTemplates accessible to the user.
     */
    public function filterTemplatesByUserAccess(Enumerable $templates, User $user, int $contextId): Collection
    {
        $filteredTemplates = collect();

        foreach ($templates as $template) {
            if ($this->isTemplateAccessibleToUser($user, $template, $contextId)) {
                $filteredTemplates->add($template);
            }
        }

        return $filteredTemplates;
    }

    /***
     * Internal method used to assign user group IDs to an email template.
     */
    private function updateTemplateAccessGroups(EmailTemplate $emailTemplate, array $newUserGroupIds, int $contextId): void
    {
        EmailTemplateAccessGroup::withEmailKey([$emailTemplate->getData('key')])
            ->withContextId($contextId)
            ->whereNotIn('user_group_id', $newUserGroupIds)->delete();

        foreach ($newUserGroupIds as $id) {
            EmailTemplateAccessGroup::updateOrCreate(
                [
                    // The where conditions
                    'email_key' => $emailTemplate->getData('key'),
                    'user_group_id' => $id,
                    'context_id' => $contextId,
                ],
                [
                    // The data to insert or update
                    'emailKey' => $emailTemplate->getData('key'),
                    'userGroupId' => $id,
                    'contextId' => $contextId,
                ]
            );
        }
    }

    /**
     * Sets the restrictions for an email template.
     * Pass empty array in $userGroupIds to delete all existing user groups for a template.
     */
    public function setEmailTemplateAccess(EmailTemplate $emailTemplate, int $contextId, ?array $userGroupIds, ?bool $isUnrestricted): void
    {
        if ($userGroupIds !== null) {
            $this->updateTemplateAccessGroups($emailTemplate, $userGroupIds, $contextId);
        }

        if ($isUnrestricted !== null) {
            $this->markTemplateAsUnrestricted($emailTemplate->getData('key'), $isUnrestricted, $contextId);
        }
    }

    /**
     * Mark an email template as unrestricted or not.
     * An unrestricted email template is available to all user groups.
     */
    public function markTemplateAsUnrestricted(string $emailKey, bool $isUnrestricted, int $contextId): void
    {
        // Unrestricted emails are represented by an entry with a `null` value for the user group ID
        if ($isUnrestricted) {
            EmailTemplateAccessGroup::updateOrCreate(
                [
                    // The where conditions
                    'email_key' => $emailKey,
                    'user_group_id' => null,
                    'context_id' => $contextId,
                ],
                [
                    // The data to insert or update
                    'emailKey' => $emailKey,
                    'userGroupId' => null,
                    'contextId' => $contextId,
                ]
            );

        } else {
            EmailTemplateAccessGroup::where('email_key', $emailKey)
                ->where('context_id', $contextId)
                ->whereNull('user_group_id')
                ->delete();
        }
    }

    /**
     * Deletes all user group access for an email.
     */
    public function deleteTemplateGroupAccess(int $contextId, array $emailKey): void
    {
        EmailTemplateAccessGroup::where('context_id', $contextId)->whereIn('email_key', $emailKey)->delete();
    }

    /**
     * Deletes all user group access for an email template and set unrestricted to their default.
     */
    public function restoreTemplateUserGroupAccess(int $contextId, array $emailKeys)
    {
        if (!empty($emailKeys)) {
            $this->deleteTemplateGroupAccess($contextId, $emailKeys);
            return $this->dao->setTemplateDefaultUnrestirctedSetting($contextId, $emailKeys);
        }
    }
}
