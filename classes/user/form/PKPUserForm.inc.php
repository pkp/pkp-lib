<?php

/**
 * @file classes/user/form/PKPUserForm.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPUserForm
 * @ingroup user_form
 *
 * @brief Abstract base form to work with user profile data (registration/profile).
 */

import('lib.pkp.classes.form.Form');

class PKPUserForm extends Form {
	/**
	 * Constructor
	 * @param $template string Template filename
	 */
	function PKPUserForm($template) {
		parent::Form($template);
	}

	/**
	 * Add basic user field checks.
	 */
	function _addBaseUserFieldChecks() {
		$this->addCheck(new FormValidator($this, 'firstName', 'required', 'user.profile.form.firstNameRequired'));
		$this->addCheck(new FormValidator($this, 'lastName', 'required', 'user.profile.form.lastNameRequired'));
		$this->addCheck(new FormValidatorUrl($this, 'userUrl', 'optional', 'user.profile.form.urlInvalid'));
		$this->addCheck(new FormValidator($this, 'country', 'required', 'user.profile.form.countryRequired'));
		$this->addCheck(new FormValidatorORCID($this, 'orcid', 'optional', 'user.orcid.orcidInvalid'));
		$this->addCheck(new FormValidatorPost($this));
	}

	/**
	 * Display the form.
	 * @param $request PKPRequest
	 */
	function display($request) {
		$templateMgr = TemplateManager::getManager($request);

		$countryDao = DAORegistry::getDAO('CountryDAO');
		$countries = $countryDao->getCountries();
		$templateMgr->assign('countries', $countries);

		$userDao = DAORegistry::getDAO('UserDAO');
		$templateMgr->assign('genderOptions', $userDao->getGenderOptions());

		$site = $request->getSite();
		$templateMgr->assign('availableLocales', $site->getSupportedLocaleNames());

		// Need the count in order to determine whether to display
		// extras-on-demand for role selection in other contexts.
		$contextDao = Application::getContextDAO();
		$contexts = $contextDao->getAll(true)->toArray();
		$templateMgr->assign('contexts', $contexts);
		if (!$request->getContext() || count($contexts)>1) {
			$templateMgr->assign('showOtherContexts', true);
		}

		// Expose potential self-registration user groups to template
		$authorUserGroups = $reviewerUserGroups = $readerUserGroups = array();
		$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
		foreach ($contexts as $context) {
			$reviewerUserGroups[$context->getId()] = $userGroupDao->getByRoleId($context->getId(), ROLE_ID_REVIEWER)->toArray();
			$authorUserGroups[$context->getId()] = $userGroupDao->getByRoleId($context->getId(), ROLE_ID_AUTHOR)->toArray();
			$readerUserGroups[$context->getId()] = $userGroupDao->getByRoleId($context->getId(), ROLE_ID_READER)->toArray();
		}
		$templateMgr->assign('reviewerUserGroups', $reviewerUserGroups);
		$templateMgr->assign('authorUserGroups', $authorUserGroups);
		$templateMgr->assign('readerUserGroups', $readerUserGroups);

		return parent::display($request);
	}

	/**
	 * Get the localized elements for this form.
	 * @return array
	 */
	function getLocaleFieldNames() {
		$userDao = DAORegistry::getDAO('UserDAO');
		return $userDao->getLocaleFieldNames();
	}

	/**
	 * Read input data
	 */
	function readInputData() {
		$this->readUserVars(array(
			'salutation',
			'firstName',
			'middleName',
			'lastName',
			'suffix',
			'gender',
			'initials',
			'affiliation',
			'signature',
			'email',
			'userUrl',
			'phone',
			'fax',
			'orcid',
			'mailingAddress',
			'country',
			'biography',
			'keywords',
			'userLocales',
			'authorGroup',
			'reviewerGroup',
			'readerGroup',
			'interests',
		));

		if ($this->getData('userLocales') == null || !is_array($this->getData('userLocales'))) {
			$this->setData('userLocales', array());
		}
	}

	/**
	 * Update a user object with the basic fields managed by this form.
	 * @param $user User
	 * @param $request PKPRequest
	 */
	function _setBaseUserFields($user, $request) {
		$user->setSalutation($this->getData('salutation'));
		$user->setFirstName($this->getData('firstName'));
		$user->setMiddleName($this->getData('middleName'));
		$user->setInitials($this->getData('initials'));
		$user->setLastName($this->getData('lastName'));
		$user->setSuffix($this->getData('suffix'));
		$user->setGender($this->getData('gender'));
		$user->setAffiliation($this->getData('affiliation'), null); // Localized
		$user->setSignature($this->getData('signature'), null); // Localized
		$user->setEmail($this->getData('email'));
		$user->setUrl($this->getData('userUrl'));
		$user->setPhone($this->getData('phone'));
		$user->setFax($this->getData('fax'));
		$user->setOrcid($this->getData('orcid'));
		$user->setMailingAddress($this->getData('mailingAddress'));
		$user->setBiography($this->getData('biography'), null); // Localized
		$user->setCountry($this->getData('country'));

		// Working locales
		$site = $request->getSite();
		$availableLocales = $site->getSupportedLocales();
		$locales = array();
		foreach ($this->getData('userLocales') as $locale) {
			if (AppLocale::isLocaleValid($locale) && in_array($locale, $availableLocales)) {
				array_push($locales, $locale);
			}
		}
		$user->setLocales($locales);
	}

	/**
	 * Update user interests
	 * @param $user
	 */
	function _updateUserInterests($user) {
		// Insert the user interests
		import('lib.pkp.classes.user.InterestManager');
		$interestManager = new InterestManager();
		$interestManager->setInterestsForUser($user, $this->getData('interests'));
	}

	/**
	 * Update user groups.
	 * @param $user PKPUser
	 */
	function _updateUserGroups($user) {
		$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
		$contextDao = Application::getContextDAO();
		$contexts = $contextDao->getAll(true);
		while ($context = $contexts->next()) {
			foreach (array(
				array(
					'roleId' => ROLE_ID_REVIEWER,
					'formElement' => 'reviewerGroup'
				),
				array(
					'roleId' => ROLE_ID_AUTHOR,
					'formElement' => 'authorGroup'
				),
				array(
					'roleId' => ROLE_ID_READER,
					'formElement' => 'readerGroup'
				),
			) as $groupData) {
				$groupFormData = (array) $this->getData($groupData['formElement']);
				$userGroups = $userGroupDao->getByRoleId($context->getId(), $groupData['roleId']);
				while ($userGroup = $userGroups->next()) {
					if (!$userGroup->getPermitSelfRegistration()) continue;

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
