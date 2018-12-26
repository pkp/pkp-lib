<?php

/**
 * @file classes/submission/form/PKPSubmissionSubmitStep1Form.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPSubmissionSubmitStep1Form
 * @ingroup submission_form
 *
 * @brief Form for Step 1 of author submission: terms, conditions, etc.
 */

import('lib.pkp.classes.submission.form.SubmissionSubmitForm');

class PKPSubmissionSubmitStep1Form extends SubmissionSubmitForm {
	/**
	 * Constructor.
	 * @param $context Context
	 * @param $submission Submission (optional)
	 */
	function __construct($context, $submission = null) {
		parent::__construct($context, $submission, 1);

		// Validation checks for this form
		$supportedSubmissionLocales = $context->getSupportedSubmissionLocales();
		if (!is_array($supportedSubmissionLocales) || count($supportedSubmissionLocales) < 1) $supportedSubmissionLocales = array($context->getPrimaryLocale());
		$this->addCheck(new FormValidatorInSet($this, 'locale', 'required', 'submission.submit.form.localeRequired', $supportedSubmissionLocales));
		if ((boolean) $context->getSetting('copyrightNoticeAgree')) {
			$this->addCheck(new FormValidator($this, 'copyrightNoticeAgree', 'required', 'submission.submit.copyrightNoticeAgreeRequired'));
		}
		$this->addCheck(new FormValidator($this, 'userGroupId', 'required', 'submission.submit.availableUserGroupsDescription'));
		$this->addCheck(new FormValidator($this, 'privacyConsent', 'required', 'user.profile.form.privacyConsentRequired'));

		foreach ((array) $context->getLocalizedSetting('submissionChecklist') as $key => $checklistItem) {
			$this->addCheck(new FormValidator($this, "checklist-$key", 'required', 'submission.submit.checklistErrors'));
		}
	}

	/**
	 * Perform additional validation checks
	 * @copydoc Form::validate
	 */
	function validate($callHooks = true) {
		if (!parent::validate($callHooks)) return false;

		// Ensure that the user is in the specified userGroupId or trying to enroll an allowed role
		$userGroupId = (int) $this->getData('userGroupId');
		$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
		$request = Application::getRequest();
		$context = $request->getContext();
		$user = $request->getUser();
		if (!$user) return false;

		if ($userGroupDao->userInGroup($user->getId(), $userGroupId)) {
			return true;
		}
		$userGroup = $userGroupDao->getById($userGroupId, $context->getId());
		if ($userGroup->getPermitSelfRegistration()){
			return true;
		}

		return false;
	}

	/**
	 * @copydoc SubmissionSubmitForm::fetch
	 */
	function fetch($request, $template = null, $display = false) {
		$user = $request->getUser();
		$templateMgr = TemplateManager::getManager($request);

		$templateMgr->assign(
			'supportedSubmissionLocaleNames',
			$this->context->getSupportedSubmissionLocaleNames()
		);

		$this->setupTemplateSubmissionChecklist($templateMgr, $request);
		$this->setupTemplatePrivacyConsent($templateMgr);

		// if this context has a copyright notice that the author must agree to, present the form items.
		if ((boolean) $this->context->getSetting('copyrightNoticeAgree')) {
			$templateMgr->assign('copyrightNotice', $this->context->getLocalizedSetting('copyrightNotice'));
			$templateMgr->assign('copyrightNoticeAgree', true);
		}

		$userGroupAssignmentDao = DAORegistry::getDAO('UserGroupAssignmentDAO');
		$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
		$userGroupNames = array();

		// List existing user roles
		$managerUserGroupAssignments = $userGroupAssignmentDao->getByUserId($user->getId(), $this->context->getId(), ROLE_ID_MANAGER);
		$authorUserGroupAssignments = $userGroupAssignmentDao->getByUserId($user->getId(), $this->context->getId(), ROLE_ID_AUTHOR);

		// List available author roles
		$availableAuthorUserGroups = $userGroupDao->getUserGroupsByStage($this->context->getId(), WORKFLOW_STAGE_ID_SUBMISSION, ROLE_ID_AUTHOR);
		$availableUserGroupNames = array();
		while($authorUserGroup = $availableAuthorUserGroups->next()) {
			if ($authorUserGroup->getPermitSelfRegistration()){
				$availableUserGroupNames[$authorUserGroup->getId()] = $authorUserGroup->getLocalizedName();
			}
		}

		// Set default group to default author group
		$defaultGroup = $userGroupDao->getDefaultByRoleId($this->context->getId(), ROLE_ID_AUTHOR);
		$noExistingRoles = false;
		$managerGroups = false;

		// If the user has manager roles, add manager roles and available author roles to selection
		if (!$managerUserGroupAssignments->wasEmpty()) {
			while($managerUserGroupAssignment = $managerUserGroupAssignments->next()) {
				$managerUserGroup = $userGroupDao->getById($managerUserGroupAssignment->getUserGroupId());
				$userGroupNames[$managerUserGroup->getId()] = $managerUserGroup->getLocalizedName();
			}
			$managerGroups = join(__('common.commaListSeparator'), $userGroupNames);
			$userGroupNames = array_replace($userGroupNames, $availableUserGroupNames);

			// Set default group to default manager group
			$defaultGroup = $userGroupDao->getDefaultByRoleId($this->context->getId(), ROLE_ID_MANAGER);

		// else if the user only has existing author roles, add to selection
		} else if (!$authorUserGroupAssignments->wasEmpty()) {
			while($authorUserGroupAssignment = $authorUserGroupAssignments->next()) {
				$authorUserGroup = $userGroupDao->getById($authorUserGroupAssignment->getUserGroupId());
				$userGroupNames[$authorUserGroup->getId()] = $authorUserGroup->getLocalizedName();
			}

		// else the user has no roles, only add available author roles to selection
		} else {
			$userGroupNames = $availableUserGroupNames;
			$noExistingRoles = true;
		}

		$templateMgr->assign('managerGroups', $managerGroups);
		$templateMgr->assign('userGroupOptions', $userGroupNames);
		$templateMgr->assign('defaultGroup', $defaultGroup);
		$templateMgr->assign('noExistingRoles', $noExistingRoles);

		return parent::fetch($request, $template, $display);
	}

	/**
	 * Initialize form data from current submission.
	 * @see SubmissionSubmitForm::initData
	 * @param $data array
	 */
	function initData($data = array()) {
		if (isset($this->submission)) {
			$query = $this->getCommentsToEditor($this->submissionId);
			$this->_data = array_merge($data, array(
				'locale' => $this->submission->getLocale(),
				'commentsToEditor' => $query ? $query->getHeadNote()->getContents() : '',
			));
		} else {
			$supportedSubmissionLocales = $this->context->getSupportedSubmissionLocales();
			// Try these locales in order until we find one that's
			// supported to use as a default.
			$keys = array_keys($supportedSubmissionLocales);
			$tryLocales = array(
				$this->getFormLocale(), // Current form locale
				AppLocale::getLocale(), // Current UI locale
				$this->context->getPrimaryLocale(), // Context locale
				$supportedSubmissionLocales[array_shift($keys)] // Fallback: first one on the list
			);
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
	function readInputData() {
		$vars = array(
			'userGroupId', 'locale', 'copyrightNoticeAgree', 'commentsToEditor','privacyConsent'
		);

		foreach ((array) $this->context->getLocalizedSetting('submissionChecklist') as $key => $checklistItem) {
			$vars[] = "checklist-$key";
		}

		$this->readUserVars($vars);
	}

	/**
	 * Set the submission data from the form.
	 * @param $submission Submission
	 */
	function setSubmissionData($submission) {
		$this->submission->setLanguage(PKPString::substr($this->submission->getLocale(), 0, 2));
		$this->submission->setLocale($this->getData('locale'));

		// submission checklist
		foreach ((array) $this->context->getSetting('submissionChecklist') as $locale => $checklistItems) {
			foreach ($checklistItems as $key => $checklistItem) {
				$this->submission->setData($this->getSubmissionChecklistItemCheckedSettingName($key), $this->getData("checklist-$key") === '1');
				$this->submission->setData($this->getSubmissionChecklistItemContentSettingName($key), $checklistItem['content'], $locale);
			}
		}

		// privacy consent
		$this->submission->setData($this->getPrivacyConsentSettingName(), $this->getData('privacyConsent') === '1');

		$locales = $this->context->getSupportedSubmissionLocales();
		foreach ($locales as $locale) {
			$this->submission->setData($this->getPrivacyStatementPlainTextSettingName(), $this->getPrivacyStatementPlainText($locale), $locale);
		}
	}

	/**
	 * Add or update comments to editor
	 * @param $submissionId int
	 * @param $commentsToEditor string
	 * @param $userId int
	 * @param $query Query optional
	 */
	function setCommentsToEditor($submissionId, $commentsToEditor, $userId, $query = null) {
		$queryDao = DAORegistry::getDAO('QueryDAO');
		$noteDao = DAORegistry::getDAO('NoteDAO');

		if (!isset($query)){
			if ($commentsToEditor) {
				$query = $queryDao->newDataObject();
				$query->setAssocType(ASSOC_TYPE_SUBMISSION);
				$query->setAssocId($submissionId);
				$query->setStageId(WORKFLOW_STAGE_ID_SUBMISSION);
				$query->setSequence(REALLY_BIG_NUMBER);
				$queryDao->insertObject($query);
				$queryDao->resequence(ASSOC_TYPE_SUBMISSION, $submissionId);
				$queryDao->insertParticipant($query->getId(), $userId);
				$queryId = $query->getId();

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
		} else{
			$queryId = $query->getId();
			$notes = $noteDao->getByAssoc(ASSOC_TYPE_QUERY, $queryId);
			if (!$notes->wasEmpty()) {
				$note = $notes->next();
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
	 * @param $submissionId int
	 * @return null|Query
	 */
	function getCommentsToEditor($submissionId) {
		$query = null;
		$queryDao = DAORegistry::getDAO('QueryDAO');
		$queries = $queryDao->getByAssoc(ASSOC_TYPE_SUBMISSION, $submissionId);
		if ($queries) $query = $queries->next();
		return $query;
	}

	/**
	 * Save changes to submission.
	 * @return int the submission ID
	 */
	function execute() {
		$submissionDao = Application::getSubmissionDAO();
		$this->extendSubmissionDAOLocaleFieldNames($submissionDao);
		$this->extendSubmissionDAOAdditionalFieldNames($submissionDao);

		$request = Application::getRequest(); 
		$user = $request->getUser();
		$userGroupDao = DAORegistry::getDAO('UserGroupDAO');

		// Enroll user if needed
		$userGroupId = (int) $this->getData('userGroupId');
		if (!$userGroupDao->userInGroup($user->getId(), $userGroupId)) {
			$userGroupDao->assignUserToGroup($user->getId(), $userGroupId);
		}

		if (isset($this->submission)) {
			// Update existing submission
			$this->setSubmissionData($this->submission);
			if ($this->submission->getSubmissionProgress() <= $this->step) {
				$this->submission->stampStatusModified();
				$this->submission->setSubmissionProgress($this->step + 1);
			}
			// Add, remove or update comments to editor
			$query = $this->getCommentsToEditor($this->submissionId);
			$this->setCommentsToEditor($this->submissionId, $this->getData('commentsToEditor'), $user->getId(), $query);

			$submissionDao->updateObject($this->submission);
		} else {
			// Create new submission
			$this->submission = $submissionDao->newDataObject();
			$this->submission->setContextId($this->context->getId());

			$this->setSubmissionData($this->submission);

			$this->submission->stampStatusModified();
			$this->submission->setSubmissionProgress($this->step + 1);
			$this->submission->setStageId(WORKFLOW_STAGE_ID_SUBMISSION);
			$this->submission->setCopyrightNotice($this->context->getLocalizedSetting('copyrightNotice'), $this->getData('locale'));
			// Insert the submission
			$this->submissionId = $submissionDao->insertObject($this->submission);

			// Set user to initial author
			$authorDao = DAORegistry::getDAO('AuthorDAO');
			$author = $authorDao->newDataObject();
			// if no user names exist for this submission locale,
			// copy the names in default site primary locale for this locale as well
			$userGivenNames = $user->getGivenName(null);
			$userFamilyNames = $user->getFamilyName(null);
			if (is_null($userFamilyNames)) $userFamilyNames = array();
			if (empty($userGivenNames[$this->submission->getLocale()])) {
				$site = Application::getRequest()->getSite();
				$userGivenNames[$this->submission->getLocale()] = $userGivenNames[$site->getPrimaryLocale()];
				// then there should also be no family name for the submission locale
				$userFamilyNames[$this->submission->getLocale()] = !empty($userFamilyNames[$site->getPrimaryLocale()]) ? $userFamilyNames[$site->getPrimaryLocale()] : '';
			}
			$author->setGivenName($userGivenNames, null);
			$author->setFamilyName($userFamilyNames, null);
			$author->setAffiliation($user->getAffiliation(null), null);
			$author->setCountry($user->getCountry());
			$author->setEmail($user->getEmail());
			$author->setUrl($user->getUrl());
			$author->setBiography($user->getBiography(null), null);
			$author->setPrimaryContact(1);
			$author->setIncludeInBrowse(1);
			$author->setOrcid($user->getOrcid());

			// Get the user group to display the submitter as
			$author->setUserGroupId($userGroupId);

			$author->setSubmissionId($this->submissionId);
			$authorDao->insertObject($author);

			// Assign the user author to the stage
			$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
			$stageAssignmentDao->build($this->submissionId, $userGroupId, $user->getId());

			// Add comments to editor
			if ($this->getData('commentsToEditor')){
				$this->setCommentsToEditor($this->submissionId, $this->getData('commentsToEditor'), $user->getId());
			}

		}

		return $this->submissionId;
	}

	private function extendSubmissionDAOLocaleFieldNames($submissionDao) {
		$localeFieldNames = array();

		foreach ((array) $this->context->getLocalizedSetting('submissionChecklist') as $key => $checklistItem) {
			$localeFieldNames[] = $this->getSubmissionChecklistItemContentSettingName($key);
		}

		$localeFieldNames[] = $this->getPrivacyStatementPlainTextSettingName();

		$submissionDao->extendLocaleFieldNames($localeFieldNames);
	}

	private function extendSubmissionDAOAdditionalFieldNames($submissionDao) {
		$additionalFieldNames = array();

		foreach ((array) $this->context->getLocalizedSetting('submissionChecklist') as $key => $checklistItem) {
			$additionalFieldNames[] = $this->getSubmissionChecklistItemCheckedSettingName($key);
		}

		$additionalFieldNames[] = $this->getPrivacyConsentSettingName();

		$submissionDao->extendAdditionalFieldNames($additionalFieldNames);
	}

	private function getPrivacyStatementPlainText($locale = null) {
		return trim(PKPString::html2text($this->context->getSetting('privacyStatement', $locale)));
	}

	private function getSubmissionChecklistItemCheckedSettingName($key) {
		return "submissionChecklistItemChecked$key";
	}

	private function getSubmissionChecklistItemContentSettingName($key) {
		return "submissionChecklistItemContent$key";
	}

	private function setupTemplateSubmissionChecklist($templateMgr, $request) {
		$submissionChecklist = $this->context->getLocalizedSetting('submissionChecklist');

		$router = $request->getRouter();
		$isPostbackRequest = is_a($router, 'PKPPageRouter') && $router->getRequestedOp($request) === 'saveStep';

		if ($isPostbackRequest) {
			// on postback read checked states from form data
			foreach ((array) $submissionChecklist as $key => $checklistItem) {
				$submissionChecklist[$key]['checked'] = $this->getData("checklist-$key") === '1';
			}
		} else {
			// on other requests determine checked states from stored submission data
			if (isset($this->submission)) {
				$locale = $this->submission->getLocale();

				foreach ((array) $submissionChecklist as $key => $checklistItem) {
					// compare checklist item's value stored on previous submit with current configured value
					$submissionChecklistItemContentSettingName = $this->getSubmissionChecklistItemContentSettingName($key);

					$contentFromPreviousSubmit = $this->submission->getData($submissionChecklistItemContentSettingName, $locale);
					if ($contentFromPreviousSubmit === $checklistItem['content']) {
						// content of checklist item is still the same, render checklist item with the state stored
						$submissionChecklistItemCheckedSettingName = $this->getSubmissionChecklistItemCheckedSettingName($key);

						$checked = $this->submission->getData($submissionChecklistItemCheckedSettingName);
					} else {
						// content of checklist item has changed, render it as unchecked so that user needs to confirm again
						$checked = false;
					}

					$submissionChecklist[$key]['checked'] = $checked;
				}
			}
		}

		$templateMgr->assign('submissionChecklist', $submissionChecklist);
	}

	private function getPrivacyConsentSettingName() {
		return 'privacyConsent';
	}

	private function getPrivacyStatementPlainTextSettingName() {
		return 'privacyStatementPlainText';
	}

	private function setupTemplatePrivacyConsent($templateMgr) {
		if (isset($this->submission))
		{
			$locale = $this->submission->getLocale();

			// compare privacy statement's plain text stored on previous submit with current configured value
			$privacyStatementPlainTextSettingName = $this->getPrivacyStatementPlainTextSettingName();

			$privacyStatementPlainTextFromPreviousSubmit = $this->submission->getData($privacyStatementPlainTextSettingName, $locale);
			$currentPrivacyStatementPlainText = $this->getPrivacyStatementPlainText($locale);

			if ($privacyStatementPlainTextFromPreviousSubmit === $currentPrivacyStatementPlainText) {
				// privacy statement is still the same, render checkbox with the state stored
				$privacyConsentSettingName = $this->getPrivacyConsentSettingName();

				$checked = $this->submission->getData($privacyConsentSettingName);
			} else {
				// privacy statement has changed, render it as unchecked so that user needs to confirm again
				$checked = false;
			}

			$templateMgr->assign('privacyConsent', $checked);
		}
	}
}


