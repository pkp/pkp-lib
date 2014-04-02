<?php

/**
 * @file classes/payment/PaymentManager.inc.php
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PaymentManager
 * @ingroup payment
 * @see Payment
 *
 * @brief Provides payment management functions.
 *
 */

class PaymentManager {
	/** @var $request PKPRequest */
	var $request;

	/**
	 * Constructor
	 * @param $request PKPRequest
	 */
	function PaymentManager(&$request) {
		$this->request =& $request;
	}

	/**
	 * Queue a payment for receipt.
	 * @param $queuedPayment object
	 * @param $expiryDate date optional
	 * @return mixed Queued payment ID for new payment, or false if fails
	 */
	function queuePayment(&$queuedPayment, $expiryDate = null) {
		if (!$this->isConfigured()) return false;

		$queuedPaymentDao =& DAORegistry::getDAO('QueuedPaymentDAO');
		$queuedPaymentId = $queuedPaymentDao->insertQueuedPayment($queuedPayment, $expiryDate);

		// Perform periodic cleanup
		if (time() % 100 == 0) $queuedPaymentDao->deleteExpiredQueuedPayments();

		return $queuedPaymentId;
	}

	/**
	 * Abstract method for fetching the payment plugin
	 * @return object
	 */
	function &getPaymentPlugin() {
		// Abstract method; subclasses should implement.
		assert(false);
	}

	/**
	 * Check if there is a payment plugin and if is configured
	 * @return bool
	 */
	function isConfigured() {
		$paymentPlugin =& $this->getPaymentPlugin();
		if ($paymentPlugin !== null) return $paymentPlugin->isConfigured(PKPApplication::getRequest());
		return false;
	}

	/**
	 * Call the payment plugin's display method
	 * @param $queuedPaymentId int
	 * @param $queuedPayment object
	 * @return boolean
	 */
	function displayPaymentForm($queuedPaymentId, &$queuedPayment) {
		$paymentPlugin =& $this->getPaymentPlugin();
		if ($paymentPlugin !== null && $paymentPlugin->isConfigured()) return $paymentPlugin->displayPaymentForm($queuedPaymentId, $queuedPayment, $this->request);
		return false;
	}

	/**
	 * Call the payment plugin's settings display method
	 * @return boolean
	 */
	function displayConfigurationForm() {
		$paymentPlugin =& $this->getPaymentPlugin();
		if ($paymentPlugin !== null && $paymentPlugin->isConfigured()) return $paymentPlugin->displayConfigurationForm();
		return false;
	}

	/**
	 * Fetch a queued payment
	 * @param $queuedPaymentId int
	 * @return QueuedPayment
	 */
	function &getQueuedPayment($queuedPaymentId) {
		$queuedPaymentDao =& DAORegistry::getDAO('QueuedPaymentDAO');
		$queuedPayment =& $queuedPaymentDao->getQueuedPayment($queuedPaymentId);
		return $queuedPayment;
	}

	/**
	 * Fulfill a queued payment
	 * @param $queuedPayment QueuedPayment
	 * @return boolean success/failure
	 */
	function fulfillQueuedPayment(&$queuedPayment) {
		// must be implemented by sub-classes
		assert(false);
	}
}

?>
