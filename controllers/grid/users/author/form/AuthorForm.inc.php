<?php

/**
 * @file controllers/grid/users/author/form/AuthorForm.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class AuthorForm
 * @ingroup controllers_grid_users_author_form
 *
 * @brief Form for adding/editing a author
 */

import('lib.pkp.classes.form.Form');

class AuthorForm extends Form {
	/** The submission associated with the submission contributor being edited **/
	var $_submission;

	/** Author the author being edited **/
	var $_author;

	/** The type of submission Id **/
	var $_submissionIdFieldName;

	/**
	 * Constructor.
	 */
	function __construct($submission, $author, $submissionIdFieldName) {
		parent::__construct('controllers/grid/users/author/form/authorForm.tpl');
		$this->setSubmission($submission);
		$this->setAuthor($author);
		$this->setSubmissionIdFieldName($submissionIdFieldName);

		// the submission locale should be the default/required locale
		$this->setDefaultFormLocale($submission->getLocale());

		// Validation checks for this form
		$form = $this;
		$this->addCheck(new FormValidatorLocale($this, 'givenName', 'required', 'user.profile.form.givenNameRequired', $this->defaultLocale));
		$this->addCheck(new FormValidatorCustom($this, 'familyName', 'optional', 'user.profile.form.givenNameRequired.locale', function($familyName) use ($form) {
			$givenNames = $form->getData('givenName');
			foreach ($familyName as $locale => $value) {
				if (!empty($value) && empty($givenNames[$locale])) {
					return false;
				}
			}
			return true;
		}));
		$this->addCheck(new FormValidatorEmail($this, 'email', 'required', 'form.emailRequired'));
		$this->addCheck(new FormValidatorUrl($this, 'userUrl', 'optional', 'user.profile.form.urlInvalid'));
		$this->addCheck(new FormValidator($this, 'userGroupId', 'required', 'submission.submit.form.contributorRoleRequired'));
		$this->addCheck(new FormValidatorORCID($this, 'orcid', 'optional', 'user.orcid.orcidInvalid'));
		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));
	}

	//
	// Getters and Setters
	//
	/**
	 * Get the author
	 * @return Author
	 */
	function getAuthor() {
		return $this->_author;
	}

	/**
	 * Set the author
	 * @param @author Author
	 */
	function setAuthor($author) {
		$this->_author = $author;
	}

	/**
	 * Get the Submission
	 * @return Submission
	 */
	function getSubmission() {
		return $this->_submission;
	}

	/**
	 * Set the Submission
	 * @param Submission
	 */
	function setSubmission($submission) {
		$this->_submission = $submission;
	}

	/**
	 * Get the Submission Id field name
	 * @return String
	 */
	function getSubmissionIdFieldName() {
		return $this->_submissionIdFieldName;
	}

	/**
	 * Set the Submission Id field name
	 * @param String
	 */
	function setSubmissionIdFieldName($submissionIdFieldName) {
		$this->_submissionIdFieldName = $submissionIdFieldName;
	}


	//
	// Overridden template methods
	//
	/**
	 * Initialize form data from the associated author.
	 */
	function initData() {
		$author = $this->getAuthor();

		if ($author) {
			$this->_data = array(
				'authorId' => $author->getId(),
				'givenName' => $author->getGivenName(null),
				'familyName' => $author->getFamilyName(null),
				'preferredPublicName' => $author->getPreferredPublicName(null),
				'affiliation' => $author->getAffiliation(null),
				'country' => $author->getCountry(),
				'email' => $author->getEmail(),
				'userUrl' => $author->getUrl(),
				'orcid' => $author->getOrcid(),
				'userGroupId' => $author->getUserGroupId(),
				'biography' => $author->getBiography(null),
				'primaryContact' => $author->getPrimaryContact(),
				'includeInBrowse' => $author->getIncludeInBrowse(),
			);
		} else {
			// assume authors should be listed unless otherwise specified.
			$this->_data = array('includeInBrowse' => true);
		}
		// in order to be able to use the hook
		return parent::initData();
	}

	/**
	 * @copydoc Form::fetch()
	 */
	function fetch($request, $template = null, $display = false) {
		$author = $this->getAuthor();

		$templateMgr = TemplateManager::getManager($request);
		$countryDao = DAORegistry::getDAO('CountryDAO');
		$countries = $countryDao->getCountries();
		$templateMgr->assign('countries', $countries);

		$router = $request->getRouter();
		$context = $router->getContext($request);

		$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
		$authorUserGroups = $userGroupDao->getByRoleId($context->getId(), ROLE_ID_AUTHOR);
		$templateMgr->assign('authorUserGroups', $authorUserGroups);

		$submission = $this->getSubmission();
		$templateMgr->assign('submissionIdFieldName', $this->getSubmissionIdFieldName());
		$templateMgr->assign('submissionId', $submission->getId());

		return parent::fetch($request, $template, $display);
	}

	/**
	 * Assign form data to user-submitted data.
	 * @see Form::readInputData()
	 */
	function readInputData() {
		$this->readUserVars(array(
			'authorId',
			'givenName',
			'familyName',
			'preferredPublicName',
			'affiliation',
			'country',
			'email',
			'userUrl',
			'orcid',
			'userGroupId',
			'biography',
			'primaryContact',
			'includeInBrowse',
		));
	}

	/**
	 * Save author
	 * @see Form::execute()
	 * @see Form::execute()
	 */
	function execute() {
		$authorDao = DAORegistry::getDAO('AuthorDAO');
		$submission = $this->getSubmission();

		$author = $this->getAuthor();
		if (!$author) {
			// this is a new submission contributor
			$this->_author = $authorDao->newDataObject();
			$author = $this->getAuthor();
			$author->setSubmissionId($submission->getId());
			$existingAuthor = false;
		} else {
			$existingAuthor = true;
			if ($submission->getId() !== $author->getSubmissionId()) fatalError('Invalid author!');
		}

		$author->setGivenName($this->getData('givenName'), null);
		$author->setFamilyName($this->getData('familyName'), null);
		$author->setPreferredPublicName($this->getData('preferredPublicName'), null);
		$author->setAffiliation($this->getData('affiliation'), null); // localized
		$author->setCountry($this->getData('country'));
		$author->setEmail($this->getData('email'));
		$author->setUrl($this->getData('userUrl'));
		$author->setOrcid($this->getData('orcid'));
		$author->setUserGroupId($this->getData('userGroupId'));
		$author->setBiography($this->getData('biography'), null); // localized
		$author->setPrimaryContact(($this->getData('primaryContact') ? true : false));
		$author->setIncludeInBrowse(($this->getData('includeInBrowse') ? true : false));

		// in order to be able to use the hook
		parent::execute();

		if ($existingAuthor) {
			$authorDao->updateObject($author);
			$authorId = $author->getId();
		} else {
			$authorId = $authorDao->insertObject($author);
		}

		return $authorId;
	}
}

?>
