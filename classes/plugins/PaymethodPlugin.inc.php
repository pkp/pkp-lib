<?php

/**
 * @file classes/plugins/GenericPlugin.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class GenericPlugin
 * @ingroup plugins
 *
 * @brief Abstract class for generic plugins
 */

import('lib.pkp.classes.plugins.LazyLoadPlugin');

abstract class PaymethodPlugin extends LazyLoadPlugin {
	/**
	 * Get the payment form for this plugin.
	 * @param $context Context
	 * @param $queuedPayment QueuedPayment
	 * @return Form
	 */
	abstract function getPaymentForm($context, $queuedPayment);

	/**
	 * Check whether this plugin is fully configured and ready for use.
	 * @param $context Context
	 * @return boolean
	 */
	function isConfigured($context) {
		return true;
	}

	/**
	 * Save settings for this payment method
	 *
	 * @param $params array Params that have already been
	 * @param $slimRequest Request Slim request object
	 * @param $request Request
	 * @return array List of errors
	 */
	public function saveSettings($params, $slimRequest, $request) {
		assert(false); // implement in child classes
	}
}


