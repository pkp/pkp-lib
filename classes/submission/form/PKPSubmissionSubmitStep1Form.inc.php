<?php

/**
 * @file classes/submission/form/PKPSubmissionSubmitStep1Form.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPSubmissionSubmitStep1Form
 * @ingroup submission_form
 *
 * @brief Form for Step 1 of author submission: terms, conditions, etc.
 */

namespace PKP\submission\form;

use APP\core\Application;
use APP\facades\Repo;
use PKP\facades\Locale;
use APP\publication\Publication;
use APP\submission\Submission;

use APP\template\TemplateManager;

use PKP\config\Config;
use PKP\core\Core;
use PKP\db\DAORegistry;
use PKP\security\Role;

class PKPSubmissionSubmitStep1Form extends SubmissionSubmitForm
{
    /** @var bool Is there a privacy statement to be confirmed? */
    public $hasPrivacyStatement = true;

    /**
     * Constructor.
     *
     * @param Context $context
     * @param Submission $submission (optional)
     */
    public function __construct($context, $submission = null)
    {
        parent::__construct($context, $submission, 1);

        $enableSiteWidePrivacyStatement = Config::getVar('general', 'sitewide_privacy_statement');
        if (!$enableSiteWidePrivacyStatement && $context) {
            $this->hasPrivacyStatement = (bool) $context->getData('privacyStatement');
        } else {
            $this->hasPrivacyStatement = (bool) Application::get()->getRequest()->getSite()->getData('privacyStatement');
        }

        // Validation checks for this form
        $supportedSubmissionLocales = $context->getSupportedSubmissionLocales();
        if (!is_array($supportedSubmissionLocales) || count($supportedSubmissionLocales) < 1) {
            $supportedSubmissionLocales = [$context->getPrimaryLocale()];
        }
        $this->addCheck(new \PKP\form\validation\FormValidatorInSet($this, 'locale', 'required', 'submission.submit.form.localeRequired', $supportedSubmissionLocales));
        if ((bool) $context->getData('copyrightNotice')) {
            $this->addCheck(new \PKP\form\validation\FormValidator($this, 'copyrightNoticeAgree', 'required', 'submission.submit.copyrightNoticeAgreeRequired'));
        }
        $this->addCheck(new \PKP\form\validation\FormValidator($this, 'userGroupId', 'required', 'submission.submit.availableUserGroupsDescription'));
        if ($this->hasPrivacyStatement) {
            $this->addCheck(new \PKP\form\validation\FormValidator($this, 'privacyConsent', 'required', 'user.profile.form.privacyConsentRequired'));
        }

        foreach ((array) $context->getLocalizedData('submissionChecklist') as $key => $checklistItem) {
            $this->addCheck(new \PKP\form\validation\FormValidator($this, "checklist-${key}", 'required', 'submission.submit.checklistErrors'));
        }
    }

    /**
     * Perform additional validation checks
     *
     * @copydoc Form::validate
     */
    public function validate($callHooks = true)
    {
        if (!parent::validate($callHooks)) {
            return false;
        }

        // Ensure that the user is in the specified userGroupId or trying to enroll an allowed role
        $userGroupId = (int) $this->getData('userGroupId');
        $userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /** @var UserGroupDAO $userGroupDao */
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        $user = $request->getUser();
        if (!$user) {
            return false;
        }

        if ($userGroupDao->userInGroup($user->getId(), $userGroupId)) {
            return true;
        }
        $userGroup = $userGroupDao->getById($userGroupId, $context->getId());
        if ($userGroup->getPermitSelfRegistration()) {
            return true;
        }

        return false;
    }

    /**
     * @copydoc SubmissionSubmitForm::fetch
     *
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false)
    {
        $user = $request->getUser();
        $templateMgr = TemplateManager::getManager($request);

        $templateMgr->assign(
            'supportedSubmissionLocaleNames',
            $this->context->getSupportedSubmissionLocaleNames()
        );

        // if this context has a copyright notice that the author must agree to, present the form items.
        if ((bool) $this->context->getData('copyrightNotice')) {
            $templateMgr->assign('copyrightNotice', $this->context->getLocalizedData('copyrightNotice'));
            $templateMgr->assign('copyrightNoticeAgree', true);
        }

        $userGroupAssignmentDao = DAORegistry::getDAO('UserGroupAssignmentDAO'); /** @var UserGroupAssignmentDAO $userGroupAssignmentDao */
        $userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /** @var UserGroupDAO $userGroupDao */
        $userGroupNames = [];

        // List existing user roles
        $managerUserGroupAssignments = $userGroupAssignmentDao->getByUserId($user->getId(), $this->context->getId(), Role::ROLE_ID_MANAGER)->toArray();
        $authorUserGroupAssignments = $userGroupAssignmentDao->getByUserId($user->getId(), $this->context->getId(), Role::ROLE_ID_AUTHOR)->toArray();

        // List available author roles
        $availableAuthorUserGroups = $userGroupDao->getUserGroupsByStage($this->context->getId(), WORKFLOW_STAGE_ID_SUBMISSION, Role::ROLE_ID_AUTHOR);
        $availableUserGroupNames = [];
        while ($authorUserGroup = $availableAuthorUserGroups->next()) {
            if ($authorUserGroup->getPermitSelfRegistration()) {
                $availableUserGroupNames[$authorUserGroup->getId()] = $authorUserGroup->getLocalizedName();
            }
        }

        // Set default group to default author group
        $defaultGroup = $userGroupDao->getDefaultByRoleId($this->context->getId(), Role::ROLE_ID_AUTHOR);
        $noExistingRoles = false;
        $managerGroups = false;

        // If the user has manager roles, add manager roles and available author roles to selection
        if (!empty($managerUserGroupAssignments)) {
            foreach ($managerUserGroupAssignments as $managerUserGroupAssignment) {
                $managerUserGroup = $userGroupDao->getById($managerUserGroupAssignment->getUserGroupId());
                $userGroupNames[$managerUserGroup->getId()] = $managerUserGroup->getLocalizedName();
            }
            $managerGroups = join(__('common.commaListSeparator'), $userGroupNames);
            $userGroupNames = array_replace($userGroupNames, $availableUserGroupNames);

            // Set default group to default manager group
            $defaultGroup = $userGroupDao->getDefaultByRoleId($this->context->getId(), Role::ROLE_ID_MANAGER);

        // else if the user only has existing author roles, add to selection
        } elseif (!empty($authorUserGroupAssignments)) {
            foreach ($authorUserGroupAssignments as $authorUserGroupAssignment) {
                $authorUserGroup = $userGroupDao->getById($authorUserGroupAssignment->getUserGroupId());
                $userGroupNames[$authorUserGroup->getId()] = $authorUserGroup->getLocalizedName();
            }
            // else the user has no roles, only add available author roles to selection
        } else {
            $userGroupNames = $availableUserGroupNames;
            $noExistingRoles = true;
        }

        $templateMgr->assign([
            'managerGroups' => $managerGroups,
            'userGroupOptions' => $userGroupNames,
            'defaultGroup' => $defaultGroup,
            'noExistingRoles' => $noExistingRoles,
            'hasPrivacyStatement' => $this->hasPrivacyStatement,
        ]);

        // Categories list
        $assignedCategories = [];

        if (isset($this->submission)) {
            $assignedCategories = iterator_to_array(Repo::category()->getIds(
                Repo::category()->getCollector()
                    ->filterByPublicationIds([$this->submission->getCurrentPublication()->getId()])
            ));
        }

        $items = [];
        $categories = iterator_to_array(Repo::category()->getMany(
            Repo::category()->getCollector()
                ->filterByContextIds([$this->context->getId()])
        ));
        foreach ($categories as $category) {
            $title = $category->getLocalizedTitle();
            if ($category->getParentId()) {
                $title = $categories[$category->getParentId()]->getLocalizedTitle() . ' > ' . $title;
            }
            $items[(int) $category->getId()] = $title;
        }
        $templateMgr->assign([
            'assignedCategories' => $assignedCategories,
            'categories' => $items,
        ]);

        return parent::fetch($request, $template, $display);
    }

    /**
     * Initialize form data from current submission.
     *
     * @see SubmissionSubmitForm::initData
     *
     * @param array $data
     */
    public function initData($data = [])
    {
        if (isset($this->submission)) {
            $query = $this->getCommentsToEditor($this->submissionId);
            $this->_data = array_merge($data, [
                'locale' => $this->submission->getLocale(),
                'commentsToEditor' => $query ? $query->getHeadNote()->getContents() : '',
            ]);
        } else {
            $supportedSubmissionLocales = $this->context->getSupportedSubmissionLocales();
            // Try these locales in order until we find one that's
            // supported to use as a default.
            $keys = array_keys($supportedSubmissionLocales);
            $tryLocales = [
                Locale::getLocale(), // Current UI locale
                $this->context->getPrimaryLocale(), // Context locale
                $supportedSubmissionLocales[array_shift($keys)] // Fallback: first one on the list
            ];
            $this->_data = $data;
            foreach ($tryLocales as $locale) {
                if (in_array($locale, $supportedSubmissionLocales)) {
                    // Found a default to use
                    $this->_data['locale'] = $locale;
                    break;
                }
            }
        }
    }

    /**
     * Assign form data to user-submitted data.
     */
    public function readInputData()
    {
        $vars = [
            'userGroupId', 'locale', 'copyrightNoticeAgree', 'commentsToEditor','privacyConsent', 'categories'
        ];
        foreach ((array) $this->context->getLocalizedData('submissionChecklist') as $key => $checklistItem) {
            $vars[] = "checklist-${key}";
        }

        $this->readUserVars($vars);
    }

    /**
     * Set the submission data from the form.
     *
     * @param Submission $submission
     */
    public function setSubmissionData($submission)
    {
        $submission->setData('locale', $this->getData('locale'));
    }

    /**
     * Set the publication data from the form.
     *
     * @param Publication $publication
     * @param Submission $submission
     */
    public function setPublicationData($publication, $submission)
    {
        // Nothing shared between all applications
    }

    /**
     * Add or update comments to editor
     *
     * @param int $submissionId
     * @param string $commentsToEditor
     * @param int $userId
     * @param Query $query optional
     */
    public function setCommentsToEditor($submissionId, $commentsToEditor, $userId, $query = null)
    {
        $queryDao = DAORegistry::getDAO('QueryDAO'); /** @var QueryDAO $queryDao */
        $noteDao = DAORegistry::getDAO('NoteDAO'); /** @var NoteDAO $noteDao */

        if (!isset($query)) {
            if ($commentsToEditor) {
                $subEditorsDAO = DAORegistry::getDAO('SubEditorsDAO');

                $query = $queryDao->newDataObject();
                $query->setAssocType(ASSOC_TYPE_SUBMISSION);
                $query->setAssocId($submissionId);
                $query->setStageId(WORKFLOW_STAGE_ID_SUBMISSION);
                $query->setSequence(REALLY_BIG_NUMBER);
                $queryDao->insertObject($query);
                $queryDao->resequence(ASSOC_TYPE_SUBMISSION, $submissionId);
                $queryId = $query->getId();

                $userIds = array_keys([$userId => null] + $subEditorsDAO->getBySubmissionGroupId($this->submission->getSectionId(), ASSOC_TYPE_SECTION, $this->submission->getContextId()));
                foreach (array_unique($userIds) as $id) {
                    $queryDao->insertParticipant($queryId, $id);
                }

                $note = $noteDao->newDataObject();
                $note->setUserId($userId);
                $note->setAssocType(ASSOC_TYPE_QUERY);
                $note->setTitle(__('submission.submit.coverNote'));
                $note->setContents($commentsToEditor);
                $note->setDateCreated(Core::getCurrentDate());
                $note->setDateModified(Core::getCurrentDate());
                $note->setAssocId($queryId);
                $noteDao->insertObject($note);
            }
        } else {
            $queryId = $query->getId();
            $notes = $noteDao->getByAssoc(ASSOC_TYPE_QUERY, $queryId);
            if ($note = $notes->next()) {
                if ($commentsToEditor) {
                    $note->setContents($commentsToEditor);
                    $note->setDateModified(Core::getCurrentDate());
                    $noteDao->updateObject($note);
                } else {
                    $noteDao->deleteObject($note);
                    $queryDao->deleteObject($query);
                }
            }
        }
    }

    /**
     * Get comments to editor
     *
     * @param int $submissionId
     *
     * @return null|Query
     */
    public function getCommentsToEditor($submissionId)
    {
        $query = null;
        $queryDao = DAORegistry::getDAO('QueryDAO'); /** @var QueryDAO $queryDao */
        $queries = $queryDao->getByAssoc(ASSOC_TYPE_SUBMISSION, $submissionId);
        if ($queries) {
            $query = $queries->next();
        }
        return $query;
    }

    /**
     * Save changes to submission.
     *
     * @return int the submission ID
     */
    public function execute(...$functionArgs)
    {
        parent::execute(...$functionArgs);

        $request = Application::get()->getRequest();
        $user = $request->getUser();
        $userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /** @var UserGroupDAO $userGroupDao */

        // Enroll user if needed
        $userGroupId = (int) $this->getData('userGroupId');
        if (!$userGroupDao->userInGroup($user->getId(), $userGroupId)) {
            $userGroupDao->assignUserToGroup($user->getId(), $userGroupId);
        }

        if (isset($this->submission)) {
            $oldLocale = $this->submission->getData('locale');
            // Update existing submission
            $this->setSubmissionData($this->submission);
            if ($this->submission->getSubmissionProgress() <= $this->step) {
                $this->submission->stampLastActivity();
                $this->submission->stampModified();
                $this->submission->setSubmissionProgress($this->step + 1);
            }
            // Add, remove or update comments to editor
            $query = $this->getCommentsToEditor($this->submissionId);
            $this->setCommentsToEditor($this->submissionId, $this->getData('commentsToEditor'), $user->getId(), $query);

            // TODO
            // Instead of calling the DAO directly, this should use \APP\submission\Repo::edit()
            Repo::submission()->dao->update($this->submission);

            $publication = $this->submission->getCurrentPublication();
            $this->setPublicationData($publication, $this->submission);
            Repo::publication()->edit($publication, $publication->_data);
            $publication = Repo::publication()->get($publication->getId());

            // Update author name data when submission locale is changed
            if ($oldLocale !== $this->submission->getData('locale')) {
                Repo::author()->changePublicationLocale($publication->getId(), $oldLocale, $this->getData('locale'));
            }
        } else {
            // Create new submission
            $this->submission = Repo::submission()->newDataObject();
            $this->submission->setContextId($this->context->getId());
            $this->setSubmissionData($this->submission);
            $this->submission->setSubmissionProgress($this->step + 1);
            $this->submission->setStageId(WORKFLOW_STAGE_ID_SUBMISSION);

            $publication = new Publication();
            $this->setPublicationData($publication, $this->submission);

            // Insert the submission
            $this->submissionId = Repo::submission()->add($this->submission, $publication);
            $this->submission = Repo::submission()->get($this->submissionId);

            // if no user names exist for this submission locale,
            // copy the names in default site primary locale for this locale as well
            $authorUserGroups = $userGroupDao->getByRoleId($this->context->getId(), Role::ROLE_ID_AUTHOR)->toArray();

            $userInAuthorGroup = array_filter($authorUserGroups, function ($item) use ($userGroupId) {
                return $item->getId() === $userGroupId;
            });

            if (!empty($userInAuthorGroup)) {
                $userGivenNames = $user->getGivenName(null);
                $userFamilyNames = $user->getFamilyName(null);
                if (is_null($userFamilyNames)) {
                    $userFamilyNames = [];
                }
                if (empty($userGivenNames[$this->submission->getData('locale')])) {
                    $site = Application::get()->getRequest()->getSite();
                    $userGivenNames[$this->submission->getData('locale')] = $userGivenNames[$site->getPrimaryLocale()];
                    // then there should also be no family name for the submission locale
                    $userFamilyNames[$this->submission->getData('locale')] = !empty($userFamilyNames[$site->getPrimaryLocale()]) ? $userFamilyNames[$site->getPrimaryLocale()] : '';
                }

                // Set user to initial author
                $author = Repo::author()->newDataObject();
                $author->setGivenName($userGivenNames, null);
                $author->setFamilyName($userFamilyNames, null);
                $author->setAffiliation($user->getAffiliation(null), null);
                $author->setCountry($user->getCountry());
                $author->setEmail($user->getEmail());
                $author->setUrl($user->getUrl());
                $author->setBiography($user->getBiography(null), null);
                $author->setIncludeInBrowse(1);
                $author->setOrcid($user->getOrcid());
                $author->setData('publicationId', $publication->getId());

                // Get the user group to display the submitter as
                $author->setUserGroupId($userGroupId);

                $authorId = Repo::author()->add($author);
                Repo::publication()->edit($publication, ['primaryContactId' => $authorId]);
            }

            
            $publication = Repo::publication()->get($publication->getId());

            // Assign the user author to the stage
            $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /** @var StageAssignmentDAO $stageAssignmentDao */
            $stageAssignmentDao->build($this->submissionId, $userGroupId, $user->getId());

            // Add comments to editor
            if ($this->getData('commentsToEditor')) {
                $this->setCommentsToEditor($this->submissionId, $this->getData('commentsToEditor'), $user->getId());
            }
        }

        // Save the submission categories
        Repo::category()->dao->deletePublicationAssignments($publication->getId());
        if ($categories = $this->getData('categories')) {
            foreach ((array) $categories as $categoryId) {
                Repo::category()->dao->insertPublicationAssignment($categoryId, $publication->getId());
            }
        }

        return $this->submissionId;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\submission\form\PKPSubmissionSubmitStep1Form', '\PKPSubmissionSubmitStep1Form');
}
