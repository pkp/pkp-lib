<?php

/**
 * @defgroup payment Payment
 * Payment handling and processing code.
 */

/**
 * @file classes/payment/Payment.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2000-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Payment
 *
 * @brief Abstract class for payments.
 *
 */

namespace PKP\payment;

/** DOES NOT inherit from DataObject for the sake of concise serialization */
class Payment
{
    /** payment id */
    public int $paymentId;

    /** context id */
    public int $contextId;

    /** amount of payment in $currencyCode units */
    public ?float $amount;

    /** ISO 4217 alpha currency code */
    public ?string $currencyCode;

    /** user id of customer making payment */
    public ?int $userId;

    /** association id for payment */
    public ?int $assocId;

    /** payment type as PaymentManager::PAYMENT_TYPE_... */
    public int $type;

    /**
     * Constructor
     */
    public function __construct(?float $amount = null, ?string $currencyCode = null, ?int $userId = null, ?int $assocId = null)
    {
        $this->amount = $amount;
        $this->currencyCode = $currencyCode;
        $this->userId = $userId;
        $this->assocId = $assocId;
    }

    /**
     * Get the row id of the payment.
     */
    public function getId(): int
    {
        return $this->paymentId;
    }

    /**
     * Set the id of payment
     */
    public function setId(int $paymentId): int
    {
        return $this->paymentId = $paymentId;
    }

    /**
     * Set the payment amount
     */
    public function setAmount(float $amount): float
    {
        return $this->amount = $amount;
    }

    /**
     * Get the payment amount
     */
    public function getAmount(): float
    {
        return $this->amount;
    }

    /**
     * Set the currency code for the transaction (ISO 4217)
     */
    public function setCurrencyCode(string $currencyCode): string
    {
        return $this->currencyCode = $currencyCode;
    }

    /**
     * Get the currency code for the transaction (ISO 4217)
     */
    public function getCurrencyCode(): string
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
     */
    public function setType(int $type): int
    {
        return $this->type = $type;
    }

    /**
     * Get the type of this payment (PaymentManager::PAYMENT_TYPE_...)
     */
    public function getType(): int
    {
        return $this->type;
    }

    /**
     * Set the user ID of the customer.
     */
    public function setUserId(int $userId): int
    {
        return $this->userId = $userId;
    }

    /**
     * Get the user ID of the customer.
     */
    public function getUserId(): int
    {
        return $this->userId;
    }

    /**
     * Set the association ID for the payment.
     *
     */
    public function setAssocId(int $assocId): int
    {
        return $this->assocId = $assocId;
    }

    /**
     * Get the association ID for the payment.
     */
    public function getAssocId(): int
    {
        return $this->assocId;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\payment\Payment', '\Payment');
}
