<?php

/**
 * @file classes/controllers/grid/plugins/PKPPluginGridRow.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPPluginGridRow
 * @ingroup classes_controllers_grid_plugins
 *
 * @brief Plugin grid row definition
 */

import('lib.pkp.classes.controllers.grid.GridRow');

class PKPPluginGridRow extends GridRow {

	/** @var Array */
	var $_userRoles;

	/** @var int */
	var $_contextLevel;

	/**
	 * Constructor
	 * @param $userRoles array
	 * @param $contextLevel int CONTEXT_...
	 */
	function PKPPluginGridRow($userRoles, $contextLevel) {
		$this->_userRoles = $userRoles;
		$this->_contextLevel = $contextLevel;

		parent::GridRow();
	}


	//
	// Overridden methods from GridRow
	//
	/**
	 * @copydoc GridRow::initialize()
	 */
	function initialize($request, $template = null) {
		parent::initialize($request, $template);

		// Is this a new row or an existing row?
		$plugin =& $this->getData(); /* @var $plugin Plugin */
		assert(is_a($plugin, 'Plugin'));

		$rowId = $this->getId();

		// Only add row actions if this is an existing row
		if (!is_null($rowId)) {
			$router = $request->getRouter(); /* @var $router PKPRouter */

			$actionArgs = array_merge(
				array('plugin' => $plugin->getName()),
				$this->getRequestArgs()
			);

			if ($this->_canEdit($plugin)) {

				$managementVerbs = $plugin->getManagementVerbs();

				// If plugin has not management verbs, we receive
				// null. Check for it before foreach.
				if (!is_null($managementVerbs)) {
					foreach ($managementVerbs as $verb) {
						list($verbName, $verbLocaleKey) = $verb;

						$linkAction = null;
						$actionRequest = null;
						$image = null;

						switch ($verbName) {
							case 'enable':
							case 'disable':
								// Do nothing. User interact with those verbs via enabled grid column.
								break;
							default:
								// Check if verb has a link action defined.
								$verbLinkAction = $plugin->getManagementVerbLinkAction($request, $verb);
								if (is_a($verbLinkAction, 'LinkAction')) {
									$linkAction = $verbLinkAction;
								} else {
									// Legacy plugin behavior. Define a default redirect request.
									import('lib.pkp.classes.linkAction.request.AjaxAction');
									$dispatcher = PKPApplication::getDispatcher();
									$context = $request->getContext();
									$actionRequest = new AjaxAction(
										$router->url(
											$request, null, null, 'plugin', null,
											array(
												'category' => $plugin->getCategory(),
												'plugin' => $plugin->getName(),
												'verb' => $verbName
											))
										);
								}
								break;
						}

						// Build link action for those verbs who don't define one.
						if (!$linkAction && $actionRequest) {
							$linkAction = new LinkAction(
								$verbName,
								$actionRequest,
								$verbLocaleKey,
								$image
							);
						}

						if ($linkAction) {
							// Insert row link action.
							$this->addAction($linkAction);

							unset($linkAction);
							unset($actionRequest);
						}
					}
				}
			}

			// Administrative functions.
			if (in_array(ROLE_ID_SITE_ADMIN, $this->_userRoles)) {
				import('lib.pkp.classes.linkAction.request.RemoteActionConfirmationModal');
				$this->addAction(new LinkAction(
						'delete',
						new RemoteActionConfirmationModal(
							__('manager.plugins.deleteConfirm'),
							__('common.delete'),
							$router->url($request, null, null, 'deletePlugin', null, $actionArgs), 'modal_delete'),
						__('common.delete'),
						'delete'));

				$this->addAction(new LinkAction(
						'upgrade',
						new AjaxModal(
							$router->url($request, null, null, 'upgradePlugin', null, $actionArgs),
							__('manager.plugins.upgrade'), 'modal_upgrade'),
						__('grid.action.upgrade'),
						'upgrade'));
			}
		}
	}


	//
	// Protected helper methods
	//
	/**
	 * Return if user can edit a plugin settings or not.
	 * @param $plugin Plugin
	 * @return boolean
	 */
	function canEdit(&$plugin) {
		assert(false); // Must be overridden by subclasses
	}
}

?>
