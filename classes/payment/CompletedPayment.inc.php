<?php

/**
 * @file classes/payment/CompletedPayment.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class CompletedPayment
 * @ingroup classes_payment
 * @see CompletedPaymentDAO
 *
 * @brief Class describing a completed payment.
 */

import('lib.pkp.classes.payment.Payment');

class CompletedPayment extends Payment {
	/** @var int Context ID */
	var $_contextId;

	/** @var string Payment completion timestamp */
	var $_timestamp;

	/** @var int PAYMENT_TYPE_... */
	var $_type;

	/** @var string Payment plugin name */
	var $_paymentPluginName;

	/**
	 * Get the context ID for the payment.
	 * @return int
	 */
	function getContextId() {
		return $this->_contextId;
	}

	/**
	 * Set the context ID for the payment.
	 * @param $contextId int
	 */
	function setContextId($contextId) {
		$this->_contextId = $contextId;
	}

	/**
	 * Get the payment completion timestamp.
	 * @return string
	 */
	function getTimestamp() {
		return $this->_timestamp;
	}

	/**
	 * Set the payment completion timestamp.
	 * @param $timestamp string Timestamp
	 */
	function setTimestamp($timestamp) {
		$this->_timestamp = $timestamp;
	}

	/**
	 * Set the payment type.
	 * @param $type int PAYMENT_TYPE_...
	 */
	function setType($type) {
		$this->_type = $type;
	}

	/**
	 * Set the payment type.
	 * @return $type int PAYMENT_TYPE_...
	 */
	function getType() {
		return $this->_type;
	}

	/**
	 * Get the payment plugin name.
	 * @return string
	 */
	function getPayMethodPluginName() {
		return $this->_paymentPluginName;
	}

	/**
	 * Set the payment plugin name.
	 * @param $paymentPluginName string
	 */
	function setPayMethodPluginName($paymentPluginName) {
		$this->_paymentPluginName = $paymentPluginName;
	}
}

?>
