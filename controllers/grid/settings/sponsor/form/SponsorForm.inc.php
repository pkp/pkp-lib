<?php

/**
 * @file controllers/grid/settings/sponsor/form/SponsorForm.inc.php
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SponsorForm
 * @ingroup controllers_grid_settings_sponsor_form
 *
 * @brief Form for adding/edditing a sponsor
 * stores/retrieves from an associative array
 */

import('lib.pkp.classes.form.Form');

class SponsorForm extends Form {
	/** the id for the sponsor being edited **/
	var $sponsorId;

	/**
	 * Constructor.
	 */
	function SponsorForm($sponsorId = null) {
		$this->sponsorId = $sponsorId;
		parent::Form('controllers/grid/settings/sponsor/form/sponsorForm.tpl');

		// Validation checks for this form
		$this->addCheck(new FormValidator($this, 'institution', 'required', 'manager.setup.form.sponsors.institutionRequired'));
		$this->addCheck(new FormValidatorUrl($this, 'url', 'required', 'manager.emails.form.sponsors.urlRequired'));
		$this->addCheck(new FormValidatorPost($this));
	}

	/**
	 * Initialize form data from current settings.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function initData($args, $request) {
		$context = $request->getContext();

		$sponsors = $context->getSetting('sponsors');
		if ( $this->sponsorId && isset($sponsors[$this->sponsorId]) ) {
			$this->_data = array(
				'sponsorId' => $this->sponsorId,
				'institution' => $sponsors[$this->sponsorId]['institution'],
				'url' => $sponsors[$this->sponsorId]['url']
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
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('institution', $this->_data['institution']);
		$templateMgr->assign('url', $this->_data['url']);
		return parent::fetch($request);
	}

	/**
	 * Assign form data to user-submitted data.
	 * @see Form::readInputData()
	 */
	function readInputData() {
		$this->readUserVars(array('sponsorId', 'institution', 'url'));
		$this->readUserVars(array('gridId', 'rowId'));
	}

	/**
	 * Save email template.
	 * @see Form::execute()
	 */
	function execute($request) {
		$context = $request->getContext();
		$sponsors = $context->getSetting('sponsors');
		if (empty($sponsors)) {
			$sponsors = array();
			$this->sponsorId = 1;
		} else {
			//FIXME: a bit of kludge to get unique sponsor id's
			$this->sponsorId = ($this->sponsorId?$this->sponsorId:(max(array_keys($sponsors)) + 1));
		}
		$sponsors[$this->sponsorId] = array(
			'institution' => $this->getData('institution'),
			'url' => $this->getData('url')
		);

		$context->updateSetting('sponsors', $sponsors, 'object', false);
		return true;
	}
}

?>
