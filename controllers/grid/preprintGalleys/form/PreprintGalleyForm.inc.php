<?php

/**
 * @file controllers/grid/preprintGalleys/form/PreprintGalleyForm.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PreprintGalleyForm
 * @ingroup controllers_grid_preprintGalleys_form
 * @see PreprintGalley
 *
 * @brief Preprint galley editing form.
 */

import('lib.pkp.classes.form.Form');

class PreprintGalleyForm extends Form {
	/** @var Submission */
	var $_submission = null;

	/** @var Publication */
	var $_publication = null;

	/** @var PreprintGalley current galley */
	var $_preprintGalley = null;

	/**
	 * Constructor.
	 * @param $submission Submission
	 * @param $publication Publication
	 * @param $preprintGalley PreprintGalley (optional)
	 */
	function __construct($request, $submission, $publication, $preprintGalley = null) {
		parent::__construct('controllers/grid/preprintGalleys/form/preprintGalleyForm.tpl');
		$this->_submission = $submission;
		$this->_publication = $publication;
		$this->_preprintGalley = $preprintGalley;

		AppLocale::requireComponents(LOCALE_COMPONENT_APP_EDITOR, LOCALE_COMPONENT_PKP_SUBMISSION);

		$this->addCheck(new FormValidator($this, 'label', 'required', 'editor.submissions.galleyLabelRequired'));
		$this->addCheck(new FormValidatorRegExp($this, 'urlPath', 'optional', 'validator.alpha_dash', '/^[-_a-z0-9]*$/'));
		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));

		// Ensure a locale is provided and valid
		$server = $request->getServer();
		$this->addCheck(
			new FormValidator(
				$this,
				'galleyLocale',
				'required',
				'editor.submissions.galleyLocaleRequired'
			),
			function($galleyLocale) use ($server) {
				return in_array($galleyLocale, $server->getSupportedSubmissionLocaleNames());
			}
		);
	}

	/**
	 * @copydoc Form::fetch()
	 */
	function fetch($request, $template = null, $display = false) {
		$templateMgr = TemplateManager::getManager($request);
		if ($this->_preprintGalley) {
			$preprintGalleyFile = $this->_preprintGalley->getFile();
			$filepath = $preprintGalleyFile ? $preprintGalleyFile->getData('path') : null;
			$templateMgr->assign([
				'representationId' => $this->_preprintGalley->getId(),
				'preprintGalley' => $this->_preprintGalley,
				'preprintGalleyFile' => $preprintGalleyFile,
				'supportsDependentFiles' => $preprintGalleyFile ? Services::get('submissionFile')->supportsDependentFiles($preprintGalleyFile, $filepath) : null,
			]);
		}
		$context = $request->getContext();
		$templateMgr->assign(array(
			'supportedLocales' => $context->getSupportedSubmissionLocaleNames(),
			'submissionId' => $this->_submission->getId(),
			'publicationId' => $this->_publication->getId(),
		));

		return parent::fetch($request, $template, $display);
	}

	/**
	 * @copydoc Form::validate
	 */
	function validate($callHooks = true) {

		// Check if urlPath is already being used
		if ($this->getData('urlPath')) {
			if (ctype_digit((string) $this->getData('urlPath'))) {
				$this->addError('urlPath', __('publication.urlPath.numberInvalid'));
				$this->addErrorField('urlPath');
			} else {
				$preprintGalley = Application::get()->getRepresentationDAO()->getByBestGalleyId($this->getData('urlPath'), $this->_publication->getId());
				if ($preprintGalley &&
					(!$this->_preprintGalley || $this->_preprintGalley->getId() !== $preprintGalley->getId())
				) {
					$this->addError('urlPath', __('publication.urlPath.duplicate'));
					$this->addErrorField('urlPath');
				}
			}
		}

		return parent::validate($callHooks);
	}

	/**
	 * Initialize form data from current galley (if applicable).
	 */
	function initData() {
		if ($this->_preprintGalley) {
			$this->_data = array(
				'label' => $this->_preprintGalley->getLabel(),
				'galleyLocale' => $this->_preprintGalley->getLocale(),
				'urlPath' => $this->_preprintGalley->getData('urlPath'),
				'urlRemote' => $this->_preprintGalley->getData('urlRemote'),
			);
		} else {
			$this->_data = array();
		}
	}

	/**
	 * Assign form data to user-submitted data.
	 */
	function readInputData() {
		$this->readUserVars(
			array(
				'label',
				'galleyLocale',
				'urlPath',
				'urlRemote',
			)
		);
	}

	/**
	 * Save changes to the galley.
	 * @return PreprintGalley The resulting preprint galley.
	 */
	function execute(...$functionArgs) {

		$preprintGalley = $this->_preprintGalley;
		$preprintGalleyDao = DAORegistry::getDAO('PreprintGalleyDAO');

		if ($preprintGalley) {
			$preprintGalley->setLabel($this->getData('label'));
			$preprintGalley->setLocale($this->getData('galleyLocale'));
			$preprintGalley->setData('urlPath', $this->getData('urlPath'));
			$preprintGalley->setData('urlRemote', $this->getData('urlRemote'));

			// Update galley in the db
			$preprintGalleyDao->updateObject($preprintGalley);
		} else {
			// Create a new galley
			$preprintGalley = $preprintGalleyDao->newDataObject();
			$preprintGalley->setData('publicationId', $this->_publication->getId());
			$preprintGalley->setLabel($this->getData('label'));
			$preprintGalley->setLocale($this->getData('galleyLocale'));
			$preprintGalley->setData('urlPath', $this->getData('urlPath'));
			$preprintGalley->setData('urlRemote', $this->getData('urlRemote'));

			// Insert new galley into the db
			$preprintGalleyDao->insertObject($preprintGalley);
			$this->_preprintGalley = $preprintGalley;
		}

		parent::execute(...$functionArgs);

		return $preprintGalley;
	}
}


