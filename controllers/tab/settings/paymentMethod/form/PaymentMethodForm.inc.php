<?php

/**
 * @file controllers/tab/settings/paymentMethod/form/PaymentMethodForm.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PaymentMethodForm
 * @ingroup controllers_tab_settings_paymentMethod_form
 *
 * @brief Form to edit payment method settings.
 */

import('lib.pkp.classes.controllers.tab.settings.form.ContextSettingsForm');

class PaymentMethodForm extends ContextSettingsForm {
	/** @var array */
	var $paymentPlugins;

	/** @var Form Payment plugin settings form */
	var $settingsForm;

	/**
	 * Constructor.
	 * @param $wizardMode boolean Whether to open the form in wizard mode
	 */
	function __construct($wizardMode = false) {
		$settings = array(
			'paymentsEnabled' => 'bool',
			'paymentPluginName' => 'string',
			'currency' => 'string',
		);

		parent::__construct($settings, 'controllers/tab/settings/paymentMethod/form/paymentMethodForm.tpl', $wizardMode);
		$this->paymentPlugins = PluginRegistry::loadCategory('paymethod');
	}

	/**
	 * @copydoc ContextSettingsForm::initData()
	 */
	function initData() {
		parent::initData();
		$request = Application::getRequest();
		$paymentPluginName = $this->getData('paymentPluginName');
		if (!isset($this->paymentPlugins[$paymentPluginName])) return;
		$plugin = $this->paymentPlugins[$paymentPluginName];
		$this->settingsForm = $plugin->getSettingsForm($request->getContext());
		$this->settingsForm->initData();
	}

	/**
	 * @copydoc ContextSettingsForm::fetch()
	 */
	function fetch($request, $template = null, $display = false, $params = null) {
		$templateMgr = TemplateManager::getManager($request);
		$currencyDao = DAORegistry::getDAO('CurrencyDAO');
		$currencies = array();
		foreach ($currencyDao->getCurrencies() as $currency) {
			$currencies[$currency->getCodeAlpha()] = $currency->getName();
		}
		$templateMgr->assign('currencies', $currencies);
		return parent::fetch($request, $template, $display, $params);
	}

	/**
	 * @copydoc ContextSettingsForm::readInputData()
	 */
	function readInputData() {
		parent::readInputData();

		$request = Application::getRequest();
		$paymentPluginName = $this->getData('paymentPluginName');
		if (!isset($this->paymentPlugins[$paymentPluginName])) return false;
		$plugin = $this->paymentPlugins[$paymentPluginName];
		$this->settingsForm = $plugin->getSettingsForm($request->getContext());
		$this->settingsForm->readInputData();
	}

	/**
	 * @copydoc ContextSettingsForm::execute()
	 */
	function execute($request) {
		$context = $request->getContext();

		// Get the selected payment plugin
		$paymentPluginName = $this->getData('paymentPluginName');
		if (isset($this->paymentPlugins[$paymentPluginName])) {
			$plugin = $this->paymentPlugins[$paymentPluginName];
			$this->settingsForm->execute();

			// Remove notification.
			$notificationDao = DAORegistry::getDAO('NotificationDAO');
			$notificationDao->deleteByAssoc($context->getAssocType(), $context->getId(), null, NOTIFICATION_TYPE_CONFIGURE_PAYMENT_METHOD, $context->getId());
		} else {
			// Create notification.
			$notificationMgr = new NotificationManager();
			$notificationMgr->createNotification($request, null, NOTIFICATION_TYPE_CONFIGURE_PAYMENT_METHOD,
				$context->getId(), $context->getAssocType(), $context->getId(), NOTIFICATION_LEVEL_NORMAL);
		}

		return parent::execute($request);
	}

	/**
	 * Validate the form.
	 * @copydoc Form::validate
	 */
	function validate($callHooks = true) {
		if (!$this->settingsForm->validate()) {
			foreach ($this->settingsForm->getErrorsArray() as $field => $message) {
				$this->addError($field, $message);
			}
		}
		return parent::validate($callHooks);
	}
}

?>
