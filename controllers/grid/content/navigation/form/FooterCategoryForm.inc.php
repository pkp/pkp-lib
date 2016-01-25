<?php
/**
 * @file controllers/grid/content/navigation/form/FooterCategoryForm.inc.php
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FooterCategoryForm
 * @ingroup controllers_grid_content_navigation_form
 *
 * @brief Form for reading/creating/editing footer category items.
 */


import('lib.pkp.classes.form.Form');

class FooterCategoryForm extends Form {
	/**
	 * @var FooterCategory
	 */
	var $_footerCategory;

	/**
	 * @var int
	 */
	var $_contextId;

	/**
	 * Constructor
	 * @param $contextId int
	 * @param $footerCategory FooterCategory
	 */
	function FooterCategoryForm($contextId, $footerCategory = null) {
		parent::Form('controllers/grid/content/navigation/form/footerCategoryForm.tpl');

		$this->setFooterCategory($footerCategory);
		$this->_contextId = $contextId;

		$this->addCheck(new FormValidator($this, 'title', 'required', 'grid.content.navigation.footer.titleRequired'));
		$this->addCheck(new FormValidator($this, 'description', 'required', 'grid.content.navigation.footer.descriptionRequired'));
		$this->addCheck(new FormValidator($this, 'path', 'required', 'grid.content.navigation.footer.pathRequired'));
		if (!$footerCategory) {
			$this->addCheck(new FormValidatorCustom(
				$this, 'path', 'required', 'grid.content.navigation.footer.pathRequired',
				create_function(
					'$path,$form,$footerCategoryDao,$contextId',
					'return !$footerCategoryDao->categoryExistsByPath($path,$contextId);'
				),
				array($this, DAORegistry::getDAO('FooterCategoryDAO'), $contextId)
			));
		} else {
			$this->addCheck(new FormValidatorCustom(
				$this, 'path', 'required', 'grid.content.navigation.footer.pathInUse',
				create_function(
					'$path,$form,$footerCategoryDao,$contextId',
					'$category = $footerCategoryDao->getByPath($path,$contextId); return (!isset($category) || $category->getId() == $form->getData(\'footerCategoryId\'));'
				),
				array($this, DAORegistry::getDAO('FooterCategoryDAO'), $contextId)
			));
		}
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

		$footerCategory = $this->getFooterCategory();
		$templateMgr->assign('footerCategory', $footerCategory);
		$templateMgr->assign('contextId', $this->getContextId());

		if (isset($footerCategory)) {
			$templateMgr->assign('title', $footerCategory->getTitle(null));
			$templateMgr->assign('path', $footerCategory->getPath());
			$templateMgr->assign('description', $footerCategory->getDescription(null));
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
		$this->readUserVars(array('title', 'description', 'path', 'footerLinks', 'footerCategoryId'));
	}

	/**
	 * @copydoc Form::execute()
	 */
	function execute($request) {

		$footerCategoryDao = DAORegistry::getDAO('FooterCategoryDAO');
		$footerCategory = $this->getFooterCategory();

		if (!$footerCategory) {
			// this is a new footerCategory
			$footerCategory = $footerCategoryDao->newDataObject();
			$footerCategory->setContextId($this->getContextId());
			$existingFooterCategory = false;
		} else {
			$existingFooterCategory = true;
		}

		$footerCategory->setTitle($this->getData('title'), null); // localized
		$footerCategory->setDescription($this->getData('description'), null); // localized
		$footerCategory->setPath($this->getData('path'));

		if ($existingFooterCategory) {
			$footerCategoryDao->updateObject($footerCategory);
			$footerCategoryId = $footerCategory->getId();
		} else {
			$footerCategoryId = $footerCategoryDao->insertObject($footerCategory);
			$this->setFooterCategory($footerCategory); // so insertEntry() has it for new FooterLinks
		}

		// for the footer links in the listbuilder.
		ListbuilderHandler::unpack($request, $this->getData('footerLinks'));

		return $footerCategoryId;
	}

	/**
	 * @copydoc ListbuilderHandler::insertEntry()
	 */
	function insertEntry($request, $newRowId) {
		$rowData = $newRowId;

		$footerLink = $this->getFooterLinkFromRowData($request, $rowData);
		$context = $request->getContext();
		$footerLinkDao = DAORegistry::getDAO('FooterLinkDAO');
		$footerLinkDao->insertObject($footerLink);
		return true;
	}

	/**
	 * @copydoc ListbuilderHandler::updateEntry()
	 */
	function updateEntry($request, $rowId, $newRowId) {
		$rowData = $newRowId;
		$footerLinkDao = DAORegistry::getDAO('FooterLinkDAO');
		$footerLink = $footerLinkDao->getById($rowId);
		if (!is_a($footerLink, 'FooterLink')) {
			assert(false);
			return false;
		}

		if ($rowData) {
			$footerLink = $this->_setLocaleData($footerLink, $rowData);
		}

		$footerLinkDao->updateObject($footerLink);
		return true;
	}

	/**
	 * @copydoc ListbuilderHandler::deleteEntry()
	 */
	function deleteEntry($request, $rowId) {
		if ($rowId) {
			$footerLinkDao = DAORegistry::getDAO('FooterLinkDAO');
			$footerLink = $footerLinkDao->getById($rowId);
			if (!is_a($footerLink, 'FooterLink')) {
				assert(false);
				return false;
			}
			$footerLinkDao->deleteObject($footerLink);
		}
	}

	/**
	 * Get a footer link object, with the rowData used
	 * to populate object fields.
	 * @param $rowData array
	 * @return FooterLink
	 */
	function &getFooterLinkFromRowData($request, $rowData) {
		$footerLinkDao = DAORegistry::getDAO('FooterLinkDAO');
		$footerLink = $footerLinkDao->newDataObject();

		if ($rowData) {
			$footerLink = $this->_setLocaleData($footerLink, $rowData);
		}

		$context = $request->getContext();
		$footerLink->setContextId($context->getId());
		$footerCategory = $this->getFooterCategory();
		if (isset($footerCategory)) {
			$footerLink->setCategoryId($footerCategory->getId());
		}

		return $footerLink;
	}


	//
	// Private helper methods.
	//
	/**
	 * Set the localized data on this footer link.
	 * @param $footerLink FooterLink
	 * @param $rowData array
	 * @return FooterLink
	 */
	function &_setLocaleData(&$footerLink, $rowData) {
		foreach($rowData['title'] as $locale => $data) {
			$footerLink->setTitle($data, $locale);
		}

		foreach($rowData['url'] as $locale => $data) {
			$footerLink->setUrl($data, $locale);
		}

		return $footerLink;
	}

	//
	// helper methods.
	//

	/**
	 * Fetch the footerCategory for this form.
	 * @return FooterCategory $footerCategory
	 */
	function &getFooterCategory() {
		return $this->_footerCategory;
	}

	/**
	 * Set the footerCategory for this form.
	 * @param int $footerCategoryId
	 */
	function setFooterCategory($footerCategory) {
		$this->_footerCategory = $footerCategory;
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
