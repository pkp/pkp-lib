<?php

/**
 * @file classes/payment/QueuedPayment.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
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
	/** @var string URL associated with this payment */
	var $requestUrl;

	/**
	 * @copydoc Payment::Payment
	 */
	function __construct($amount, $currencyCode, $userId = null, $assocId = null) {
		parent::__construct($amount, $currencyCode, $userId, $assocId);
	}

	/**
	 * Set the request URL.
	 * @param $url string
	 * @return string New URL
	 */
	function setRequestUrl($url) {
		return $this->requestUrl = $url;
	}

	/**
	 * Get the request URL.
	 * @return string
	 */
	function getRequestUrl() {
		return $this->requestUrl;
	}
}


