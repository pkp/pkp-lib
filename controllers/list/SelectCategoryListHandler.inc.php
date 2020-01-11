<?php
/**
 * @file controllers/list/SelectCategoryListHandler.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SelectCategoryListHandler
 * @ingroup classes_controllers_list
 *
 * @brief A base class for building SelectListHandler components for categories.
 */
import('lib.pkp.controllers.list.SelectListHandler');

class SelectCategoryListHandler extends SelectListHandler {
	/**
	 * Params to populate list of categories
	 *
	 * @var array {
	 *		@option contextId string
 	 * }
	 */
	public $_getParams = array();

	/**
	 * @copydoc SelectListHandler::init()
	 */
	public function init($args = array()) {
		parent::init($args);
		$this->_getParams = !empty($args['getParams']) ? $args['getParams'] : $this->_getParams;
	}

	/**
	 * @copydoc SelectListHandler::getItems()
	 */
	public function getItems() {

		if (isset($this->_getParams['contextId'])) {
			$contextId = $this->_getParams['contextId'];
		} else {
			$request = Application::get()->getRequest();
			$context = $request->getContext();
			$contextId = $context ? $context->getId() : CONTEXT_ID_NONE;
		}

		$items = array();

		$categoryDao = DAORegistry::getDAO('CategoryDAO');
		$categories = $categoryDao->getByContextId($contextId);
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
