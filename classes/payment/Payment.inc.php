<?php

/**
 * @defgroup payment Payment
 * Payment handling and processing code.
 */

/**
 * @file classes/payment/Payment.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Payment
 * @ingroup payment
 *
 * @brief Abstract class for payments.
 *
 */

namespace PKP\payment;

/** DOES NOT inherit from DataObject for the sake of concise serialization */
class Payment
{
    /** @var int payment id */
    public $paymentId;

    /** @var int Context ID */
    public $contextId;

    /** @var numeric amount of payment in $currencyCode units */
    public $amount;

    /** @var string ISO 4217 alpha currency code */
    public $currencyCode;

    /** @var int user ID of customer making payment */
    public $userId;

    /** @var int association ID for payment */
    public $assocId;

    /** @var int PAYMENT_TYPE_... */
    public $_type;

    /**
     * Constructor
     *
     * @param $amount number
     * @param $currencyCode string
     * @param $userId int
     * @param $assocId int optional
     */
    public function __construct($amount = null, $currencyCode = null, $userId = null, $assocId = null)
    {
        $this->amount = $amount;
        $this->currencyCode = $currencyCode;
        $this->userId = $userId;
        $this->assocId = $assocId;
    }

    /**
     * Get the row id of the payment.
     *
     * @return int
     */
    public function getId()
    {
        return $this->paymentId;
    }

    /**
     * Set the id of payment
     *
     * @param $paymentId int
     *
     * @return int new payment id
     */
    public function setId($paymentId)
    {
        return $this->paymentId = $paymentId;
    }

    /**
     * Set the payment amount
     *
     * @param $amount numeric
     *
     * @return numeric new amount
     */
    public function setAmount($amount)
    {
        return $this->amount = $amount;
    }

    /**
     * Get the payment amount
     *
     * @return numeric
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * Set the currency code for the transaction (ISO 4217)
     *
     * @param $currencyCode string
     *
     * @return string new currency code
     */
    public function setCurrencyCode($currencyCode)
    {
        return $this->currencyCode = $currencyCode;
    }

    /**
     * Get the currency code for the transaction (ISO 4217)
     *
     * @return string
     */
    public function getCurrencyCode()
    {
        return $this->currencyCode;
    }

    /**
     * Get the context ID for the payment.
     *
     * @return int
     */
    public function getContextId()
    {
        return $this->contextId;
    }

    /**
     * Set the context ID for the payment.
     *
     * @param $contextId int
     */
    public function setContextId($contextId)
    {
        $this->contextId = $contextId;
    }

    /**
     * Set the type for this payment (PAYMENT_TYPE_...)
     *
     * @param $type int PAYMENT_TYPE_...
     *
     * @return int New payment type
     */
    public function setType($type)
    {
        return $this->type = $type;
    }

    /**
     * Get the type of this payment (PAYMENT_TYPE_...)
     *
     * @return int PAYMENT_TYPE_...
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Set the user ID of the customer.
     *
     * @param $userId int
     *
     * @return int New user ID
     */
    public function setUserId($userId)
    {
        return $this->userId = $userId;
    }

    /**
     * Get the user ID of the customer.
     *
     * @return int
     */
    public function getUserId()
    {
        return $this->userId;
    }

    /**
     * Set the association ID for the payment.
     *
     * @param $assocId int
     *
     * @return int New association ID
     */
    public function setAssocId($assocId)
    {
        return $this->assocId = $assocId;
    }

    /**
     * Get the association ID for the payment.
     *
     * @return int
     */
    public function getAssocId()
    {
        return $this->assocId;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\payment\Payment', '\Payment');
}
