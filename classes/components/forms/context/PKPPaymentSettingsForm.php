<?php
/**
 * @file classes/components/form/context/PKPPaymentSettingsForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPPaymentSettingsForm
 *
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for configuring the general payment settings.
 */

namespace PKP\components\forms\context;

use PKP\components\forms\FieldOptions;
use PKP\components\forms\FieldSelect;
use PKP\components\forms\FormComponent;
use PKP\facades\Locale;
use PKP\plugins\PluginRegistry;

class PKPPaymentSettingsForm extends FormComponent
{
    public const FORM_PAYMENT_SETTINGS = 'paymentSettings';
    public $id = self::FORM_PAYMENT_SETTINGS;
    public $method = 'PUT';

    /**
     * Constructor
     *
     * @param string $action URL to submit the form to
     * @param array $locales Supported locales
     * @param \PKP\context\Context $context Journal or Press to change settings for
     */
    public function __construct($action, $locales, $context)
    {
        $this->action = $action;
        $this->locales = $locales;

        $currencies = [];
        foreach (Locale::getCurrencies() as $currency) {
            $currencies[] = [
                'value' => $currency->getLetterCode(),
                'label' => htmlspecialchars($currency->getLocalName()),
            ];
        }

        // Ensure payment method plugins can hook in
        $paymentPlugins = PluginRegistry::loadCategory('paymethod', true);
        $pluginList = [];
        foreach ($paymentPlugins as $plugin) {
            $pluginList[] = [
                'value' => $plugin->getName(),
                'label' => htmlspecialchars($plugin->getDisplayName()),
            ];
        }

        $this->addGroup([
            'id' => 'setup',
            'label' => __('navigation.setup'),
        ])
            ->addField(new FieldOptions('paymentsEnabled', [
                'label' => __('common.enable'),
                'options' => [
                    ['value' => true, 'label' => __('manager.payment.options.enablePayments')]
                ],
                'value' => (bool) $context->getData('paymentsEnabled'),
                'groupId' => 'setup',
            ]))
            ->addField(new FieldSelect('currency', [
                'label' => __('manager.paymentMethod.currency'),
                'options' => $currencies,
                'showWhen' => 'paymentsEnabled',
                'value' => $context->getData('currency'),
                'groupId' => 'setup',
            ]))
            ->addField(new FieldSelect('paymentPluginName', [
                'label' => __('plugins.categories.paymethod'),
                'options' => $pluginList,
                'showWhen' => 'paymentsEnabled',
                'value' => $context->getData('paymentPluginName'),
                'groupId' => 'setup',
            ]));
    }
}
