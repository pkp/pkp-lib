<?php

/**
 * @file controllers/grid/users/reviewer/form/EnrollExistingReviewerForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EnrollExistingReviewerForm
 *
 * @ingroup controllers_grid_users_reviewer_form
 *
 * @brief Form for enrolling an existing reviewer and adding them to a submission.
 */

namespace PKP\controllers\grid\users\reviewer\form;

use APP\core\Application;
use APP\facades\Repo;
use Illuminate\Support\Facades\Mail;
use PKP\db\DAORegistry;
use PKP\security\Role;
use PKP\security\RoleDAO;

class EnrollExistingReviewerForm extends ReviewerForm
{
    /**
     * Constructor.
     */
    public function __construct($submission, $reviewRound)
    {
        parent::__construct($submission, $reviewRound);
        $this->setTemplate('controllers/grid/users/reviewer/form/enrollExistingReviewerForm.tpl');

        $this->addCheck(new \PKP\form\validation\FormValidator($this, 'userGroupId', 'required', 'user.profile.form.usergroupRequired'));
        $this->addCheck(new \PKP\form\validation\FormValidator($this, 'userId', 'required', 'manager.people.existingUserRequired'));
    }

    /**
     * @copydoc Form::init()
     */
    public function initData()
    {
        parent::initData();

        $mailable = $this->getMailable();
        $context = Application::get()->getRequest()->getContext();
        $template = Repo::emailTemplate()->getByKey($context->getId(), $mailable::getEmailTemplateKey());
        $this->setData('personalMessage', Mail::compileParams($template->getLocalizedData('body'), $mailable->viewData));
    }

    /**
     * @copydoc Form::fetch()
     *
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false)
    {
        $advancedSearchAction = $this->getAdvancedSearchAction($request);

        $this->setReviewerFormAction($advancedSearchAction);
        return parent::fetch($request, $template, $display);
    }

    /**
     * Assign form data to user-submitted data.
     *
     * @see Form::readInputData()
     */
    public function readInputData()
    {
        parent::readInputData();

        $this->readUserVars(['userId', 'userGroupId']);
    }

    /**
     * @copydoc Form::execute()
     */
    public function execute(...$functionArgs)
    {
        // Assign a reviewer user group to an existing non-reviewer
        $userId = (int) $this->getData('userId');

        $userGroupId = (int) $this->getData('userGroupId');

        if (!$this->isValidUserAndGroup($userId, $userGroupId)) {
            throw new \Exception('invalid user or userGroup ID');
        }

        Repo::userGroup()->assignUserToGroup($userId, $userGroupId);

        // Set the reviewerId in the Form for the parent class to use
        $this->setData('reviewerId', $userId);

        return parent::execute(...$functionArgs);
    }

    /**
     * Checks if the user group is valid and the user exists and doesn't already have a reviewer role
     */
    protected function isValidUserAndGroup(int $userId, int $userGroupId): bool
    {
        $context = Application::get()->getRequest()->getContext();

        // User exists
        $user = Repo::user()->get($userId);
        if (!$user) {
            return false;
        }

        // Ensure user doesn't have a reviewer role
        $roleDao = DAORegistry::getDAO('RoleDAO'); /** @var RoleDAO $roleDao */
        if ($roleDao->userHasRole($context->getId(), $user->getId(), Role::ROLE_ID_REVIEWER)) {
            return false;
        }

        // User Group exists
        $userGroup = Repo::userGroup()->get($userGroupId);
        if (!$userGroup) {
            return false;
        }

        // User Group refers to the reviewer role
        if ($userGroup->getData('roleId') != Role::ROLE_ID_REVIEWER) {
            return false;
        }

        // User group refers to the right context
        if (intval($userGroup->getData('contextId')) != $context->getId()) {
            return false;
        }

        return true;
    }
}
