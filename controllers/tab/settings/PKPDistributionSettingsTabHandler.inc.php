<?php

/**
 * @file controllers/tab/settings/PKPDistributionSettingsTabHandler.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPDistributionSettingsTabHandler
 * @ingroup controllers_tab_settings
 *
 * @brief Handle AJAX operations for tabs on Distribution Process page.
 */

// Import the base Handler.
import('lib.pkp.controllers.tab.settings.ManagerSettingsTabHandler');

class PKPDistributionSettingsTabHandler extends ManagerSettingsTabHandler {
	/**
	 * Constructor
	 */
	function PKPDistributionSettingsTabHandler() {
		parent::ManagerSettingsTabHandler();
		// In addition to the operations permitted by the parent
		// class, allow Payment AJAX extras.
		$this->addRoleAssignment(
			ROLE_ID_MANAGER,
			array('getPaymentMethods', 'getPaymentFormContents')
		);
		$this->setPageTabs(array(
			'indexing' => 'lib.pkp.controllers.tab.settings.contextIndexing.form.ContextIndexingForm',
			'paymentMethod' => 'lib.pkp.controllers.tab.settings.paymentMethod.form.PaymentMethodForm',
		));
	}

	/**
	 * Expose payment methods via AHAX for selection on the payment tab.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function getPaymentMethods($args, $request) {
		// Expose names of payment plugins to template.
		$pluginNames = array(__('manager.paymentMethod.none'));
		$pluginNames += array_map(
			create_function('$a', 'return $a->getDisplayName();'),
			PluginRegistry::loadCategory('paymethod')
		);
		$jsonMessage = new JSONMessage(true, $pluginNames);
		return $jsonMessage->getString();
	}

	/**
	 * Get the form contents for the given payment method.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function getPaymentFormContents($args, $request) {
		$paymentPluginName = $request->getUserVar('paymentPluginName');
		$plugins =& PluginRegistry::loadCategory('paymethod');
		if (!isset($plugins[$paymentPluginName])) {
			// Invalid plugin name
			$jsonMessage = new JSONMessage(false);
		} else {
			// Fetch and return the JSON-encoded form contents
			$plugin =& $plugins[$paymentPluginName];
			$params = array(); // Blank -- OJS compatibility. Need to supply by reference.
			$templateMgr = TemplateManager::getManager($request);

			// Expose current settings to the template
			$context = $request->getContext();
			foreach ($plugin->getSettingsFormFieldNames() as $fieldName) {
				$templateMgr->assign($fieldName, $plugin->getSetting($context->getId(), $fieldName));
			}

			$jsonMessage = new JSONMessage(true, $plugin->displayPaymentSettingsForm($params, $templateMgr));
		}
		return $jsonMessage->getString();
	}
}

?>
