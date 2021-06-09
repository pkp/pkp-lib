<?php
/**
 * @file classes/components/form/context/PKPDoiSetupSettingsForm.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPDoiSetupSettingsForm
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for enabling and configuring DOI settings for a given context
 */

namespace PKP\components\forms\context;

use PKP\components\forms\FieldOptions;
use PKP\components\forms\FormComponent;
use PKP\context\Context;

class PKPDoiSetupSettingsForm extends FormComponent
{
    public const FORM_DOI_SETUP_SETTINGS = 'doiSetupSettings';

    /** @copydoc FormComponent::$id */
    public $id = self::FORM_DOI_SETUP_SETTINGS;

    /** @copydoc FormComponent::$method */
    public $method = 'PUT';

    /**
     * Constructor
     *
     * @param string $action URL to submit the form to
     * @param array $locales Supported locales
     * @param Context $context Journal or Press to change settings for
     */
    public function __construct(string $action, array $locales, Context $context)
    {
        $this->action = $action;
        $this->locales = $locales;

        $this->addField(new FieldOptions(Context::SETTING_ENABLE_DOIS, [
            'label' => __('manager.setup.dois'),
            'description' => __('manager.setup.enableDois.description'),
            'options' => [
                ['value' => true, 'label' => __('manager.setup.enableDois.enable')]
            ],
            'value' => (bool) $context->getData(Context::SETTING_ENABLE_DOIS),
        ]));
    }
}
