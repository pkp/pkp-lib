<?php

/**
 * @file classes/plugins/GenericPlugin.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class GenericPlugin
 * @ingroup plugins
 *
 * @brief Abstract class for generic plugins
 */

namespace PKP\plugins;

abstract class PaymethodPlugin extends LazyLoadPlugin
{
    /**
     * Get the payment form for this plugin.
     *
     * @param Context $context
     * @param QueuedPayment $queuedPayment
     *
     * @return Form
     */
    abstract public function getPaymentForm($context, $queuedPayment);

    /**
     * Check whether this plugin is fully configured and ready for use.
     *
     * @param Context $context
     *
     * @return bool
     */
    public function isConfigured($context)
    {
        return true;
    }

    /**
     * Save settings for this payment method
     *
     * @param array $params Params that have already been
     * @param Request $slimRequest Slim request object
     * @param Request $request
     *
     * @return array List of errors
     */
    public function saveSettings($params, $slimRequest, $request)
    {
        assert(false); // implement in child classes
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\plugins\PaymethodPlugin', '\PaymethodPlugin');
}
