<?php

/**
 * @file controllers/grid/plugins/PluginGridCellProvider.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PluginGridCellProvider
 * @ingroup controllers_grid_plugins
 *
 * @brief Cell provider for columns in a plugin grid.
 */

import('lib.pkp.classes.controllers.grid.GridCellProvider');

class PluginGridCellProvider extends GridCellProvider {

	/**
	 * Constructor
	 */
	function PluginGridCellProvider() {
		parent::GridCellProvider();
	}

	/**
	 * Extracts variables for a given column from a data element
	 * so that they may be assigned to template before rendering.
	 * @param $row GridRow
	 * @param $column GridColumn
	 * @return array
	 */
	function getTemplateVarsFromRowColumn($row, $column) {
		$plugin =& $row->getData();
		$columnId = $column->getId();
		assert(is_a($plugin, 'Plugin') && !empty($columnId));

		switch ($columnId) {
			case 'name':
				return array('label' => $plugin->getDisplayName());
				break;
			case 'category':
				return array('label' => $plugin->getCategory());
				break;
			case 'description':
				return array('label' => $plugin->getDescription());
				break;
			case 'enabled':
				// Assume that every plugin is enabled...
				$enabled = true;
				// ... and that it doesn't have enable or disable management verbs.
				$hasVerbs = false;

				// Check if plugin can be disabled.
				if (is_callable(array($plugin, 'getEnabled'))) {

					// Plugin can be disabled, so check its current state.
					if (!$plugin->getEnabled()) {
						$enabled = false;
					}

					// Check if plugin has management verbs to
					// disable or enable.
					$managementVerbs = $plugin->getManagementVerbs();
					if (!is_null($managementVerbs)) {
						foreach($managementVerbs as $verb) {
							list($verbName) = $verb;
							if ($verbName === 'enable' || $verbName === 'disable') {
								$hasVerbs = true;
								break;
							}
						}
					}
				} else {
					// Plugin cannot be disabled so it also doesn't
					// have management verbs to those actions.
					$hasVerbs = false;
				}

				// Set the state of the select element that will
				// be used to enable or disable the plugin.
				$selectDisabled = true;
				if ($hasVerbs) {
					// Plugin have management verbs.
					// Show an enabled select element.
					$selectDisabled = false;
				}

				return array('selected' => $enabled,
					'disabled' => $selectDisabled);
			default:
				break;
		}

		return parent::getTemplateVarsFromRowColumn($row, $column);
	}

	/**
	 * @copydoc GridCellProvider::getCellActions()
	 */
	function getCellActions($request, $row, $column, $position = GRID_ACTION_POSITION_DEFAULT) {
		if ($column->getId() == 'enabled') {
			$plugin = $row->getData(); /* @var $plugin Plugin */

			$router = $request->getRouter();
			$managementVerbs = $plugin->getManagementVerbs();

			if (!is_null($managementVerbs)) {
				foreach ($managementVerbs as $verb) {
					list($verbName, $verbLocalizedName) = $verb;

					$actionArgs = array_merge(array(
							'plugin' => $plugin->getName(),
							'verb' => $verbName),
						$row->getRequestArgs());

					$actionRequest = null;
					$defaultUrl = $router->url($request, null, null, 'plugin', null, $actionArgs);

					if ($verbName === 'enable') {
						import('lib.pkp.classes.linkAction.request.AjaxAction');
						$actionRequest = new AjaxAction($defaultUrl);
					} else if ($verbName === 'disable') {
						import('lib.pkp.classes.linkAction.request.RemoteActionConfirmationModal');
						$actionRequest = new RemoteActionConfirmationModal(__('grid.plugin.disable'),
							__('common.disable'), $defaultUrl);
					}

					if ($actionRequest) {
						$linkAction = new LinkAction(
							$verbName,
							$actionRequest,
							$verbLocalizedName,
							null
						);

						return array($linkAction);
					}
				}
			}
			// Plugin can't be disabled or don't have
			// management verbs for that.
			return array();
		}
		return parent::getCellActions($request, $row, $column, $position);
	}
}

?>
