<?php

/**
 * @file classes/mail/variables/QueuedPaymentEmailVariable.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class QueuedPaymentEmailVariable
 * @ingroup mail_variables
 *
 * @brief Represents email template variables that are associated with payments
 */

namespace PKP\mail\variables;

use APP\core\Application;
use PKP\core\PKPServices;
use PKP\payment\QueuedPayment;

class QueuedPaymentEmailVariable extends Variable
{
    public const ITEM_NAME = 'itemName';
    public const ITEM_COST = 'itemCost';
    public const ITEM_CURRENCY_CODE = 'itemCurrencyCode';

    protected QueuedPayment $queuedPayment;

    public function __construct(QueuedPayment $queuedPayment)
    {
        $this->queuedPayment = $queuedPayment;
    }

    /**
     * @copydoc Validation::descriptions()
     */
    public static function descriptions(): array
    {
        return
        [
            self::ITEM_NAME => __('emailTemplate.variable.queuedPayment.itemName'),
            self::ITEM_COST => __('emailTemplate.variable.queuedPayment.itemCost'),
            self::ITEM_CURRENCY_CODE => __('emailTemplate.variable.queuedPayment.itemCurrencyCode'),
        ];
    }

    /**
     * @copydoc Validation::values()
     */
    public function values(string $locale): array
    {
        return
        [
            self::ITEM_NAME => $this->getItemName(),
            self::ITEM_COST => (string) $this->getItemCost(),
            self::ITEM_CURRENCY_CODE => (string) $this->getItemCurrencyCode(),
        ];
    }

    protected function getItemName(): string
    {
        $context = PKPServices::get('context')->get($this->queuedPayment->getContextId());
        $paymentManager = Application::getPaymentManager($context);
        return $paymentManager->getPaymentName($this->queuedPayment);
    }

    /**
     * @return float|int|string|null
     */
    protected function getItemCost()
    {
        return $this->queuedPayment->getAmount();
    }

    protected function getItemCurrencyCode(): ?string
    {
        return $this->queuedPayment->getCurrencyCode();
    }
}
