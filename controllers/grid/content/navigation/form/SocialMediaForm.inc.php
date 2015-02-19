<?php
/**
 * @file controllers/grid/content/navigation/form/SocialMediaForm.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SocialMediaForm
 * @ingroup controllers_grid_content_navigation_form
 *
 * @brief Form for reading/creating/editing social media navigation items.
 */


import('lib.pkp.classes.form.Form');

class SocialMediaForm extends Form {
	/**
	 * @var SocialMedia
	 */
	var $_socialMedia;

	/**
	 * @var int
	 */
	var $_contextId;

	/**
	 * Constructor
	 * @param $contextId int
	 * @param $socialMediaId int
	 */
	function SocialMediaForm($contextId, $socialMedia = null) {
		parent::Form('controllers/grid/content/navigation/form/socialMediaForm.tpl');

		$this->_socialMedia = $socialMedia;
		$this->_contextId = $contextId;

		$this->addCheck(new FormValidator($this, 'platform', 'required', 'grid.content.navigation.socialMedia.platformRequired'));
		$this->addCheck(new FormValidator($this, 'code', 'required', 'grid.content.navigation.socialMedia.codeRequired'));
		$this->addCheck(new FormValidatorPost($this));
	}


	//
	// Extended methods from Form
	//
	/**
	 * @copydoc Form::fetch()
	 */
	function fetch($request) {
		$templateMgr = TemplateManager::getManager($request);

		$socialMediaDao = DAORegistry::getDAO('SocialMediaDAO');
		$socialMedia = $this->getSocialMedia();
		$templateMgr->assign('socialMedia', $socialMedia);
		$templateMgr->assign('contextId', $this->getContextId());

		if (isset($socialMedia)) {
			$templateMgr->assign('platform', $socialMedia->getPlatform(null));
			$templateMgr->assign('code', $socialMedia->getCode());
			$templateMgr->assign('includeInCatalog', $socialMedia->getIncludeInCatalog());
		}

		return parent::fetch($request);
	}

	//
	// Extended methods from Form
	//
	/**
	 * @copydoc Form::readInputData()
	 */
	function readInputData() {
		$this->readUserVars(array('platform', 'code', 'includeInCatalog'));
	}

	/**
	 * @copydoc Form::execute()
	 */
	function execute($request) {

		$socialMediaDao = DAORegistry::getDAO('SocialMediaDAO');
		$socialMedia = $this->getSocialMedia();

		if (!$socialMedia) {
			// this is a new socialMedia object
			$socialMedia = $socialMediaDao->newDataObject();
			$socialMedia->setContextId($this->getContextId());
			$existingSocialMedia = false;
		} else {
			$existingSocialMedia = true;
		}

		$socialMedia->setPlatform($this->getData('platform'), null); // localized
		$socialMedia->setCode($this->getData('code'));
		$socialMedia->setIncludeInCatalog($this->getData('includeInCatalog')!=''?1:0);

		if ($existingSocialMedia) {
			$socialMediaDao->updateObject($socialMedia);
			$socialMediaId = $socialMedia->getId();
		} else {
			$socialMediaId = $socialMediaDao->insertObject($socialMedia);
		}

		return $socialMediaId;
	}


	//
	// helper methods.
	//

	/**
	 * Fetch the SocialMedia object for this form.
	 * @return SocialMedia $socialMedia
	 */
	function &getSocialMedia() {
		return $this->_socialMedia;
	}

	/**
	 * Fetch the context Id for this form.
	 * @return int $contextId
	 */
	function getContextId() {
		return $this->_contextId;
	}
}
?>
