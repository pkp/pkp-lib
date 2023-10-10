<?php

/**
 * @file classes/payment/QueuedPayment.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class QueuedPayment
 *
 * @ingroup payment
 *
 * @see QueuedPaymentDAO
 *
 * @brief Queued (unfulfilled) payment data structure
 *
 */

namespace PKP\payment;

class QueuedPayment extends Payment
{
    /** @var string URL associated with this payment */
    public $requestUrl;

    /**
     * Set the request URL.
     *
     * @param string $url
     *
     * @return string New URL
     */
    public function setRequestUrl($url)
    {
        return $this->requestUrl = $url;
    }

    /**
     * Get the request URL.
     *
     * @return string
     */
    public function getRequestUrl()
    {
        return $this->requestUrl;
    }
}

// (#6091 Class aliasing applied in PKPApplication for the sake of deserializing legacy content.)
