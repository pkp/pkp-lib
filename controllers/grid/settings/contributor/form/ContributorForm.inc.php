<?php

/**
 * @file controllers/grid/settings/contributor/form/ContributorForm.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ContributorForm
 * @ingroup controllers_grid_settings_contributor_form
 *
 * @brief Form for adding/edditing a contributor
 * stores/retrieves from an associative array
 */

import('lib.pkp.classes.form.Form');

class ContributorForm extends Form {
	/** the id for the contributor being edited **/
	var $contributorId;

	/**
	 * Constructor.
	 */
	function ContributorForm($contributorId = null) {
		$this->contributorId = $contributorId;
		parent::Form('controllers/grid/settings/contributor/form/contributorForm.tpl');

		// Validation checks for this form
		$this->addCheck(new FormValidator($this, 'institution', 'required', 'manager.setup.form.contributors.institutionRequired'));
		$this->addCheck(new FormValidatorUrl($this, 'url', 'required', 'manager.emails.form.contributors.urlRequired'));
		$this->addCheck(new FormValidatorPost($this));
	}

	/**
	 * Initialize form data from current settings.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function initData($args, $request) {
		$context = $request->getContext();

		$contributors = $context->getSetting('contributors');
		if ( $this->contributorId && isset($contributors[$this->contributorId]) ) {
			$this->_data = array(
				'contributorId' => $this->contributorId,
				'institution' => $contributors[$this->contributorId]['institution'],
				'url' => $contributors[$this->contributorId]['url']
				);
		} else {
			$this->_data = array(
				'institution' => '',
				'url' => ''
			);
		}

		// grid related data
		$this->_data['gridId'] = $args['gridId'];
		$this->_data['rowId'] = isset($args['rowId']) ? $args['rowId'] : null;
	}

	/**
	 * Fetch
	 * @param $request PKPRequest
	 * @see Form::fetch()
	 */
	function fetch($request) {
		AppLocale::requireComponents(LOCALE_COMPONENT_APP_MANAGER);
		return parent::fetch($request);
	}

	/**
	 * Assign form data to user-submitted data.
	 * @see Form::readInputData()
	 */
	function readInputData() {
		$this->readUserVars(array('contributorId', 'institution', 'url'));
		$this->readUserVars(array('gridId', 'rowId'));
	}

	/**
	 * Save email template.
	 * @see Form::execute()
	 */
	function execute($request) {
		$context = $request->getContext();
		$contributors = $context->getSetting('contributors');
		if (empty($contributors)) {
			$contributors = array();
			$this->contributorId = 1;
		} else {
			//FIXME: a bit of kludge to get unique contributor id's
			$this->contributorId = ($this->contributorId?$this->contributorId:(max(array_keys($contributors)) + 1));
		}

		$contributors[$this->contributorId] = array('institution' => $this->getData('institution'),
								'url' => $this->getData('url'));
		$context->updateSetting('contributors', $contributors, 'object', false);
		return true;
	}
}

?>
