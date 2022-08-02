<?php

/**
 * @file classes/user/form/UserFormHelper.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserFormHelper
 * @ingroup user_form
 *
 * @brief Helper functions for shared user form concerns.
 */

namespace PKP\user\form;

use APP\core\Application;
use PKP\db\DAORegistry;

use PKP\security\Role;

class UserFormHelper
{
    /**
     * Constructor
     */
    public function __construct()
    {
    }

    /**
     * Assign role selection content to the template manager.
     *
     * @param PKPTemplateManager $templateMgr
     * @param PKPRequest $request
     */
    public function assignRoleContent($templateMgr, $request)
    {
        // Need the count in order to determine whether to display
        // extras-on-demand for role selection in other contexts.
        $contextDao = Application::getContextDAO();
        $contexts = $contextDao->getAll(true)->toArray();
        $contextsWithUserRegistration = [];
        foreach ($contexts as $context) {
            if (!$context->getData('disableUserReg')) {
                $contextsWithUserRegistration[] = $context;
            }
        }
        $templateMgr->assign([
            'contexts' => $contexts,
            'showOtherContexts' => !$request->getContext() || count($contextsWithUserRegistration) > 1,
        ]);

        // Expose potential self-registration user groups to template
        $authorUserGroups = $reviewerUserGroups = $readerUserGroups = [];
        $userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /** @var UserGroupDAO $userGroupDao */
        foreach ($contexts as $context) {
            if ($context->getData('disableUserReg')) {
                continue;
            }
            $reviewerUserGroups[$context->getId()] = $userGroupDao->getByRoleId($context->getId(), Role::ROLE_ID_REVIEWER)->toArray();
            $authorUserGroups[$context->getId()] = $userGroupDao->getByRoleId($context->getId(), Role::ROLE_ID_AUTHOR)->toArray();
            $readerUserGroups[$context->getId()] = $userGroupDao->getByRoleId($context->getId(), Role::ROLE_ID_READER)->toArray();
        }
        $templateMgr->assign([
            'reviewerUserGroups' => $reviewerUserGroups,
            'authorUserGroups' => $authorUserGroups,
            'readerUserGroups' => $readerUserGroups,
        ]);
    }

    /**
     * Save role elements of an executed user form.
     *
     * @param Form $form The form from which to fetch elements
     * @param User $user The current user
     */
    public function saveRoleContent($form, $user)
    {
        $userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /** @var UserGroupDAO $userGroupDao */
        $contextDao = Application::getContextDAO();
        $contexts = $contextDao->getAll(true);
        while ($context = $contexts->next()) {
            if ($context->getData('disableUserReg')) {
                continue;
            }

            foreach ([
                [
                    'roleId' => Role::ROLE_ID_REVIEWER,
                    'formElement' => 'reviewerGroup'
                ],
                [
                    'roleId' => Role::ROLE_ID_AUTHOR,
                    'formElement' => 'authorGroup'
                ],
                [
                    'roleId' => Role::ROLE_ID_READER,
                    'formElement' => 'readerGroup'
                ],
            ] as $groupData) {
                $groupFormData = (array) $form->getData($groupData['formElement']);
                $userGroups = $userGroupDao->getByRoleId($context->getId(), $groupData['roleId']);
                while ($userGroup = $userGroups->next()) {
                    if (!$userGroup->getPermitSelfRegistration()) {
                        continue;
                    }

                    $groupId = $userGroup->getId();
                    $inGroup = $userGroupDao->userInGroup($user->getId(), $groupId);
                    if (!$inGroup && array_key_exists($groupId, $groupFormData)) {
                        $userGroupDao->assignUserToGroup($user->getId(), $groupId, $context->getId());
                    } elseif ($inGroup && !array_key_exists($groupId, $groupFormData)) {
                        $userGroupDao->removeUserFromGroup($user->getId(), $groupId, $context->getId());
                    }
                }
            }
        }
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\user\form\UserFormHelper', '\UserFormHelper');
}
