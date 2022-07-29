<?php

/**
 * @file controllers/grid/settings/user/form/UserForm.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserForm
 * @ingroup controllers_grid_settings_user_form
 *
 * @brief Base class for user forms.
 */

namespace PKP\controllers\grid\settings\user\form;

use APP\core\Application;
use APP\template\TemplateManager;
use PKP\db\DAORegistry;
use PKP\form\Form;

class UserForm extends Form
{
    /** @var int Id of the user being edited */
    public $userId;

    /**
     * Constructor.
     *
     * @param int $userId optional
     */
    public function __construct($template, $userId = null)
    {
        parent::__construct($template);

        $this->userId = isset($userId) ? (int) $userId : null;

        if (!is_null($userId)) {
            $this->addCheck(new \PKP\form\validation\FormValidator($this, 'userGroupIds', 'required', 'manager.users.roleRequired'));
        }
    }

    /**
     * Initialize form data from current user profile.
     */
    public function initData()
    {
        $userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /** @var UserGroupDAO $userGroupDao */
        $userGroups = $userGroupDao->getByUserId($this->userId);
        $userGroupIds = [];
        while ($userGroup = $userGroups->next()) {
            $userGroupIds[] = $userGroup->getId();
        }
        $this->setData('userGroupIds', $userGroupIds);

        parent::initData();
    }

    /**
     * @copydoc Form::readInputData()
     */
    public function readInputData()
    {
        $this->readUserVars(['userGroupIds']);
        parent::readInputData();
    }

    /**
     * @copydoc Form::display
     *
     * @param null|mixed $request
     * @param null|mixed $template
     */
    public function display($request = null, $template = null)
    {
        $context = $request->getContext();
        $contextId = $context ? $context->getId() : \PKP\core\PKPApplication::CONTEXT_ID_NONE;
        $templateMgr = TemplateManager::getManager($request);

        $allUserGroups = [];
        $userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /** @var UserGroupDAO $userGroupDao */
        $userGroups = $userGroupDao->getByContextId($contextId);
        while ($userGroup = $userGroups->next()) {
            $allUserGroups[(int) $userGroup->getId()] = $userGroup->getLocalizedName();
        }

        $templateMgr->assign([
            'allUserGroups' => $allUserGroups,
            'assignedUserGroups' => array_map('intval', $this->getData('userGroupIds')),
        ]);

        return $this->fetch($request);
    }

    /**
     * @copydoc Form::execute()
     */
    public function execute(...$functionArgs)
    {
        if (isset($this->userId)) {
            $userGroupAssignmentDao = DAORegistry::getDAO('UserGroupAssignmentDAO'); /** @var UserGroupAssignmentDAO $userGroupAssignmentDao */
            $userGroupAssignmentDao->deleteAssignmentsByContextId(Application::get()->getRequest()->getContext()->getId(), $this->userId);
            if ($this->getData('userGroupIds')) {
                $userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /** @var UserGroupDAO $userGroupDao */
                foreach ($this->getData('userGroupIds') as $userGroupId) {
                    $userGroupDao->assignUserToGroup($this->userId, $userGroupId);
                }
            }
        }

        parent::execute(...$functionArgs);
    }
}
