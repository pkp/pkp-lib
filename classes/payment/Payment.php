<?php

/**
 * @defgroup payment Payment
 * Payment handling and processing code.
 */

/**
 * @file classes/payment/Payment.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Payment
 *
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

    /** @var float amount of payment in $currencyCode units */
    public $amount;

    /** @var string ISO 4217 alpha currency code */
    public $currencyCode;

    /** @var int user ID of customer making payment */
    public $userId;

    /** @var int association ID for payment */
    public $assocId;

    /** @var int PaymentManager::PAYMENT_TYPE_... */
    public $type;

    /**
     * Constructor
     *
     * @param float $amount
     * @param string $currencyCode
     * @param int $userId
     * @param int $assocId optional
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
     * @param int $paymentId
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
     * @param float $amount
     *
     * @return float new amount
     */
    public function setAmount($amount)
    {
        return $this->amount = $amount;
    }

    /**
     * Get the payment amount
     *
     * @return float
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * Set the currency code for the transaction (ISO 4217)
     *
     * @param string $currencyCode
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
     */
    public function getContextId(): int
    {
        return $this->contextId;
    }

    /**
     * Set the context ID for the payment.
     */
    public function setContextId(int $contextId): void
    {
        $this->contextId = $contextId;
    }

    /**
     * Set the type for this payment (PaymentManager::PAYMENT_TYPE_...)
     *
     * @param int $type PaymentManager::PAYMENT_TYPE_...
     *
     * @return int New payment type
     */
    public function setType($type)
    {
        return $this->type = $type;
    }

    /**
     * Get the type of this payment (PaymentManager::PAYMENT_TYPE_...)
     *
     * @return int PaymentManager::PAYMENT_TYPE_...
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Set the user ID of the customer.
     *
     * @param int $userId
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
     * @param int $assocId
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
