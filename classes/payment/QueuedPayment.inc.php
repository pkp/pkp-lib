<?php

/**
 * @file classes/payment/QueuedPayment.inc.php
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class QueuedPayment
 * @ingroup payment
 * @see QueuedPaymentDAO
 *
 * @brief Queued (unfulfilled) payment data structure
 *
 */

import('lib.pkp.classes.payment.Payment');

class QueuedPayment extends Payment {
	/**
	 * Constructor
	 */
	function QueuedPayment($amount, $currencyCode, $userId = null, $assocId = null) {
		parent::Payment($amount, $currencyCode, $userId, $assocId);
	}

	/**
	 * Set the queued payment ID
	 * @param $queuedPaymentId int
	 */
	function setQueuedPaymentId($queuedPaymentId) {
		if (Config::getVar('debug', 'deprecation_warnings')) trigger_error('Deprecated function.');
		return parent::setPaymentId($queuedPaymentId);
	}

	/**
	 * Get the queued payment ID
	 * @return int
	 */
	function getQueuedPaymentId() {
		if (Config::getVar('debug', 'deprecation_warnings')) trigger_error('Deprecated function.');
		return parent::getPaymentId();
	}
}

?>
