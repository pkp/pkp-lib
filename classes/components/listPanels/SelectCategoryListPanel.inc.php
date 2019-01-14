<?php
/**
 * @file classes/components/listPanels/SelectCategoryListPanel.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SelectCategoryListPanel
 * @ingroup classes_controllers_list
 *
 * @brief A base class for building SelectListPanel components for categories.
 */
import('lib.pkp.classes.components.listPanels.SelectListPanel');

class SelectCategoryListPanel extends SelectListPanel {
	/**
	 * Params to populate list of categories
	 *
	 * @var array {
	 *		@option contextId string
 	 * }
	 */
	public $_getParams = array();

	/**
	 * @copydoc SelectListPanel::init()
	 */
	public function init($args = array()) {
		parent::init($args);
		$this->_getParams = !empty($args['getParams']) ? $args['getParams'] : $this->_getParams;
	}

	/**
	 * @copydoc SelectListPanel::getItems()
	 */
	public function getItems() {

		if (isset($this->_getParams['contextId'])) {
			$contextId = $this->_getParams['contextId'];
		} else {
			$request = Application::getRequest();
			$context = $request->getContext();
			$contextId = $context ? $context->getId() : CONTEXT_ID_NONE;
		}

		$items = array();

		$categoryDao = DAORegistry::getDAO('CategoryDAO');
		$categories = $categoryDao->getByPressId($contextId);
		if (!$categories->wasEmpty) {
			while ($category = $categories->next()) {
				$items[] = array(
					'id' => $category->getId(),
					'title' => $category->getLocalizedTitle(),
				);
			}
		}

		return $items;
	}
}
