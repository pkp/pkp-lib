<?php
/**
 * @file controllers/list/SelectListHandler.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2000-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SelectListHandler
 * @ingroup classes_controllers_list
 *
 * @brief A base class for list selection handlers. This defines the structure
 *  that will be used by handlers which need to select from a list of items.
 */
import('lib.pkp.controllers.list.SelectListHandler');
import('classes.core.ServicesContainer');

class SelectUserListHandler extends SelectListHandler {
	/** @var array Params to populate list of users with UserService */
	public $_getParams = null;

	/**
	 * @copydoc SelectListHandler::init()
	 */
	public function init($args = array()) {
		parent::init($args);
		$this->_getParams = !empty($args['getParams']) ? $args['getParams'] : $this->_getParams;
	}


	/**
	 * @copydoc SelectListHandler::getItemms()
	 */
	public function getItems() {
		$request = Application::getRequest();
		$context = $request->getContext();
		$contextId = $context ? $context->getId() : CONTEXT_ID_NONE;

		$userService = ServicesContainer::instance()->get('user');
		$allSubEditors = $userService->getUsers($contextId, $this->_getParams);

		$items = array();
		if (!empty($allSubEditors)) {
			foreach ($allSubEditors as $subEditor) {
				$subEditorProps = $userService->getSummaryProperties($subEditor, array(
					'request' => $request,
				));
				// Assign the fullName to the title, so SelectListPanel can find it
				$subEditorProps['title'] = $subEditorProps['fullName'];
				$items[] = $subEditorProps;
			}
		}

		return $items;
	}
}
