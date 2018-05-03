<?php
/**
 * @file controllers/list/SelectUserListHandler.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SelectUserListHandler
 * @ingroup classes_controllers_list
 *
 * @brief A class for building SelectListHandler components for users.
 */
import('lib.pkp.controllers.list.SelectListHandler');
import('classes.core.ServicesContainer');

class SelectUserListHandler extends SelectListHandler {
	/** @var array Params to populate list of users with UserService */
	public $_getParams = null;

	/** @var function A callback function to set the title key for each item */
	public $_setItemTitleCallback = null;

	/**
	 * @copydoc SelectListHandler::init()
	 */
	public function init($args = array()) {
		parent::init($args);
		$this->_getParams = !empty($args['getParams']) ? $args['getParams'] : $this->_getParams;
		$this->_setItemTitleCallback = !empty($args['setItemTitleCallback']) ? $args['setItemTitleCallback'] : $this->_setItemTitleCallback;
	}

	/**
	 * @copydoc SelectListHandler::getItems()
	 */
	public function getItems() {
		$request = Application::getRequest();
		$context = $request->getContext();
		$contextId = $context ? $context->getId() : CONTEXT_ID_NONE;

		$userService = ServicesContainer::instance()->get('user');
		$allUsers = $userService->getUsers($contextId, $this->_getParams);

		$items = array();
		if (!empty($allUsers)) {
			foreach ($allUsers as $user) {
				$userProps = $userService->getSummaryProperties($user, array(
					'request' => $request,
				));
				// Assign the item title so SelectListPanel can find it
				if (is_callable($this->_setItemTitleCallback)) {
					$userProps['title'] = call_user_func($this->_setItemTitleCallback, $user, $userProps);
				} else {
					$userProps['title'] = $userProps['fullName'];
				}
				$items[] = $userProps;
			}
		}

		return $items;
	}
}
