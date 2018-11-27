<?php
/**
 * @file components/listPanels/users/SelectRoleListPanel.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SelectRoleListPanel
 * @ingroup classes_controllers_list
 *
 * @brief A class for selecting roles from a SelectListPanel.
 */
import('lib.pkp.classes.components.listPanels.SelectListPanel');

class SelectRoleListPanel extends SelectListPanel {
	/** @var int Which context's user roles to retrieve */
	public $_contextId = null;

	/**
	 * @copydoc SelectListPanel::init()
	 */
	public function init($args = array()) {
		parent::init($args);
		$this->_contextId = !empty($args['contextId']) ? $args['contextId'] : $this->_contextId;
	}


	/**
	 * @copydoc SelectListPanel::getItems()
	 */
	public function getItems() {

		$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
		$userGroups = $userGroupDao->getByContextId($this->_contextId);

		$items = array();
		while ($userGroup = $userGroups->next()) {
			$items[] = array(
				'id' => $userGroup->getId(),
				'title' => $userGroup->getLocalizedName(),
			);
		}

		return $items;
	}
}
