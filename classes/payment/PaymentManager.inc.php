<?php

/**
 * @file classes/payment/PaymentManager.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PaymentManager
 * @ingroup payment
 * @see Payment
 *
 * @brief Provides payment management functions.
 *
 */

abstract class PaymentManager {
	/** @var Context */
	var $_context;

	/**
	 * Constructor
	 * @param $context Context
	 */
	function __construct($context) {
		$this->_context = $context;
	}

	/**
	 * Queue a payment for receipt.
	 * @param $queuedPayment object
	 * @param $expiryDate date optional
	 * @return mixed Queued payment ID for new payment, or false if fails
	 */
	function queuePayment($queuedPayment, $expiryDate = null) {
		if (!$this->isConfigured()) return false;

		$queuedPaymentDao = DAORegistry::getDAO('QueuedPaymentDAO'); /* @var $queuedPaymentDao QueuedPaymentDAO */
		$queuedPaymentId = $queuedPaymentDao->insertObject($queuedPayment, $expiryDate);

		// Perform periodic cleanup
		if (time() % 100 == 0) $queuedPaymentDao->deleteExpired();

		return $queuedPaymentId;
	}

	/**
	 * Abstract method for fetching the payment plugin
	 * @return object
	 */
	abstract function getPaymentPlugin();

	/**
	 * Check if there is a payment plugin and if is configured
	 * @return bool
	 */
	function isConfigured() {
		$paymentPlugin = $this->getPaymentPlugin();
		if ($paymentPlugin !== null) return $paymentPlugin->isConfigured($this->_context);
		return false;
	}

	/**
	 * Get the payment form for the configured payment plugin and specified payment.
	 * @param $queuedPayment QueuedPayment
	 * @return Form
	 */
	function getPaymentForm($queuedPayment) {
		$paymentPlugin = $this->getPaymentPlugin();
		if ($paymentPlugin !== null && $paymentPlugin->isConfigured($this->_context)) return $paymentPlugin->getPaymentForm($this->_context, $queuedPayment);
		return false;
	}

	/**
	 * Call the payment plugin's settings display method
	 * @return boolean
	 */
	function displayConfigurationForm() {
		$paymentPlugin = $this->getPaymentPlugin();
		if ($paymentPlugin !== null && $paymentPlugin->isConfigured($this->_context)) return $paymentPlugin->displayConfigurationForm();
		return false;
	}

	/**
	 * Fetch a queued payment
	 * @param $queuedPaymentId int
	 * @return QueuedPayment
	 */
	function getQueuedPayment($queuedPaymentId) {
		$queuedPaymentDao = DAORegistry::getDAO('QueuedPaymentDAO'); /* @var $queuedPaymentDao QueuedPaymentDAO */
		$queuedPayment = $queuedPaymentDao->getById($queuedPaymentId);
		return $queuedPayment;
	}

	/**
	 * Fulfill a queued payment
	 * @param $request PKPRequest
	 * @param $queuedPayment QueuedPayment
	 * @return boolean success/failure
	 */
	abstract function fulfillQueuedPayment($request, $queuedPayment);
}


