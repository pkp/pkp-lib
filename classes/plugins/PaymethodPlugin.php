<?php

/**
 * @file classes/plugins/GenericPlugin.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PaymethodPlugin
 *
 * @ingroup plugins
 *
 * @brief Abstract class for generic plugins
 */

namespace PKP\plugins;

use PKP\context\Context;

abstract class PaymethodPlugin extends LazyLoadPlugin
{
    public function register($category, $path, $mainContextId = null)
    {
        if (!parent::register($category, $path, $mainContextId)) {
            return false;
        }

        Hook::add('API::payments::settings::edit', $this->saveSettings(...));

        return true;
    }

    /**
     * Get the payment form for this plugin.
     *
     * @param Context $context
     * @param \PKP\payment\QueuedPayment $queuedPayment
     *
     * @return \PKP\form\Form
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
     * Must be implemented in a child class to save payment settings and attach updated data to the response
     */
    abstract public function saveSettings(string $hookName, array $args);

    /**
     * Handle incoming requests/notifications
     *
     * @param array $args
     * @param \APP\core\Request $request
     */
    abstract public function handle($args, $request);
}
