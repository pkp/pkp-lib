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
    public function getByKey(int $contextId, string $key): ?EmailTemplate
    {
        return $this->dao->getByKey($contextId, $key);
    }

    /** @copydoc DAO::getCollector() */
    public function getCollector(int $contextId): Collector
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

        //  If groupIds were passed to limit email access, check that groups exists within the context
        if (isset($props['userGroupIds'])) {
            $validator->after(function () use ($validator, $props, $context) {
                $existingGroupIds = Repo::userGroup()->getCollector()
                    ->filterByContextIds([$context->getId()])
                    ->filterByUserGroupIds($props['userGroupIds'])->getIds()->toArray();

                if (!empty(array_diff($existingGroupIds, $props['userGroupIds']))) {
                    $validator->errors()->add('userGroupIds', __('api.emailTemplates.404.userGroupIds'));
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
    public function edit(EmailTemplate $emailTemplate, array $params, $contextId)
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
    public function deleteMany(Collector $collector)
    {
        foreach ($collector->getMany() as $emailTemplate) {
            $this->delete($emailTemplate);
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
        $results->each(function ($emailTemplate) use ($deletedKeys) {
            $deletedKeys[] = $emailTemplate->getData('key');
            $this->delete($emailTemplate);
        });
        $this->dao->installAlternateEmailTemplates($contextId);
        Hook::call('EmailTemplate::restoreDefaults', [&$deletedKeys, $contextId]);
        return $deletedKeys;
    }


    /***
     * Gets the IDs of the user groups assigned to an email template
     */
    public function getUserGroupsIdsAssignedToTemplate(string $templateKey, int $contextId): array
    {
        return EmailTemplateAccessGroup::withEmailKey([$templateKey])
            ->withContextId($contextId)
            ->whereNot('user_group_id', null)
            ->get()
            ->pluck('userGroupId')
            ->all();
    }

    /***
     * Checks if an Email Template is unrestricted
     */
    public function isTemplateUnrestricted(string $templateKey, int $contextId): bool
    {
        return !!EmailTemplateAccessGroup::withEmailKey([$templateKey])
            ->withContextId($contextId)
            ->where('user_group_id', null)
            ->first();
    }


    /**
     * Checks if an email template is accessible to a user. A template is accessible if it is assigned to a user group that the user belongs to or if the template is unrestricted
     */
    public function isTemplateAccessibleToUser(User $user, EmailTemplate $template, int $contextId): bool
    {
        if ($user->hasRole([Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER,], $contextId)) {
            return true;
        }

        $userUserGroups = Repo::userGroup()->userUserGroups($user->getId(), $contextId)->all();
        $templateUserGroups = $this->getUserGroupsIdsAssignedToTemplate($template->getData('key'), $contextId);

        // Null entry indicates that template is unrestricted
        if(in_array(null, $templateUserGroups)) {
            return true;
        }


        foreach ($userUserGroups as $userGroup) {
            if (in_array($userGroup->getId(), $templateUserGroups)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Filters a list of EmailTemplates to return only those accessible by a specified user.
     *
     * @param Enumerable $templates List of EmailTemplate objects to filter.
     * @param User $user The user whose access level is used for filtering.
     *
     * @return Collection Filtered list of EmailTemplate objects accessible to the user.
     */
    public function filterTemplatesByUserAccess(Enumerable $templates, User $user, int $contextId): Collection
    {
        $filteredTemplates = collect();

        foreach ($templates as $template) {
            if($this->isTemplateAccessibleToUser($user, $template, $contextId)) {
                $filteredTemplates->add($template);
            }
        }

        return $filteredTemplates;
    }

    /***
     * Internal method used to assign user group IDs to an email template
     */
    private function _updateTemplateAccessGroups(EmailTemplate $emailTemplate, array $newUserGroupIds, int $contextId): void
    {
        EmailTemplateAccessGroup::withEmailKey([$emailTemplate->getData('key')])
            ->withContextId($contextId)
            ->whereNotIn('user_group_id', $newUserGroupIds)->delete();

        foreach ($newUserGroupIds as $id) {
            EmailTemplateAccessGroup::updateOrCreate(
                [
                    // The where conditions (keys that should match)
                    'email_key' => $emailTemplate->getData('key'),
                    'user_group_id' => $id,
                    'context_id' => $contextId,
                ],
                [
                    // The data to insert or update (values to set)
                    'emailKey' => $emailTemplate->getData('key'),
                    'userGroupId' => $id,
                    'contextId' => $contextId,
                ]
            );
        }
    }

    /**
     * Pass empty array in $userGroupIds to delete all existing user groups for a template
     */
    public function setEmailTemplateAccess(EmailTemplate $emailTemplate, int $contextId, ?array $userGroupIds, ?bool $isUnrestricted): void
    {
        if($userGroupIds !== null) {
            $this->_updateTemplateAccessGroups($emailTemplate, $userGroupIds, $contextId);
        }

        if($isUnrestricted !== null) {
            $this->markTemplateAsUnrestricted($emailTemplate, $isUnrestricted, $contextId);
        }
    }


    /**
     * Mark an email template as unrestricted or not.
     * An unrestricted email template is available to all user groups associated with the Roles linked to the mailable that the template belongs to.
     * Mailable roles are stored in the $fromRoleIds property of a mailable
     */
    private function markTemplateAsUnrestricted(EmailTemplate $emailTemplate, bool $isUnrestricted, int $contextId): void
    {
        // Unrestricted emails are represented by an entry with a `null` value for the user group ID
        if ($isUnrestricted) {
            EmailTemplateAccessGroup::updateOrCreate(
                [
                    // The where conditions (keys that should match)
                    'email_key' => $emailTemplate->getData('key'),
                    'user_group_id' => null,
                    'context_id' => $contextId,
                ],
                [
                    // The data to insert or update (values to set)
                    'emailKey' => $emailTemplate->getData('key'),
                    'userGroupId' => null,
                    'contextId' => $contextId,
                ]
            );

        } else {
            // Remove entry with a `null` value for the user group ID to reflect that it is no longer unrestricted
            EmailTemplateAccessGroup::withEmailKey([$emailTemplate->getData('key')])
                ->withContextId($contextId)
                ->withGroupIds([null])
                ->delete();
        }
    }

}
