<?php

/**
 * @file classes/plugins/GenericPlugin.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2017 John Willinsky
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
	 * Get the settings form for this plugin.
	 * @param $context Context
	 * @return Form
	 */
	abstract function getSettingsForm($context);

	/**
	 * Get the payment form for this plugin.
	 * @param $context Context
	 * @param $queuedPayment QueuedPayment
	 * @return Form
	 */
	abstract function getPaymentForm($context, $queuedPayment);
}

?>
