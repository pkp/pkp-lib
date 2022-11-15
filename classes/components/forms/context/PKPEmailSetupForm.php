<?php
/**
 * @file classes/components/form/context/PKPEmailSetupForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPEmailSetupForm
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for configuring a context's email settings.
 */

namespace PKP\components\forms\context;

use APP\mail\variables\ContextEmailVariable;
use Illuminate\Support\Arr;
use PKP\components\forms\FieldHTML;
use PKP\components\forms\FieldOptions;
use PKP\components\forms\FieldPreparedContent;
use PKP\components\forms\FieldText;
use PKP\components\forms\FormComponent;

define('FORM_EMAIL_SETUP', 'emailSetup');

class PKPEmailSetupForm extends FormComponent
{
    /** @copydoc FormComponent::$id */
    public $id = FORM_EMAIL_SETUP;

    /** @copydoc FormComponent::$method */
    public $method = 'PUT';

    /**
     * Constructor
     *
     * @param string $action URL to submit the form to
     * @param array $locales Supported locales
     * @param Context $context Journal or Press to change settings for
     */
    public function __construct($action, $locales, $context)
    {
        $this->action = $action;
        $this->locales = $locales;

        $this->addField(new FieldOptions('notifyAllAuthors', [
            'label' => __('manager.setup.notifyAllAuthors'),
            'description' => __('manager.setup.notifyAllAuthors.description'),
            'type' => 'radio',
            'options' => [
                ['value' => true, 'label' => __('manager.setup.notifyAllAuthors.allAuthors')],
                ['value' => false, 'label' => __('manager.setup.notifyAllAuthors.assignedAuthors')],
            ],
            'value' => $context->getData('notifyAllAuthors'),
        ]));

        $this->addField(new FieldPreparedContent('emailSignature', [
            'label' => __('manager.setup.emailSignature'),
            'tooltip' => __('manager.setup.emailSignature.description'),
            'value' => $context->getData('emailSignature'),
            'preparedContent' => array_values(Arr::map(ContextEmailVariable::descriptions(), function ($description, $key) {
                return [
                    'key' => $key,
                    'description' => $description,
                    'value' => '{$' . $key .'}'
                ];
            }))
        ]));

        $this->addEnveloperSenderField($context);
    }

    /**
     * Build the enveloper sender field
     *
     * @param Context $context Journal or Press to change settings for
     *
     */
    protected function addEnveloperSenderField($context)
    {
        $canEnvelopeSender = \Config::getVar('email', 'allow_envelope_sender');

        if ($canEnvelopeSender) {
            $this->addField(new FieldText('envelopeSender', [
                'label' => __('manager.setup.emailBounceAddress'),
                'tooltip' => __('manager.setup.emailBounceAddress.description'),
                'value' => $context->getData('envelopeSender'),
            ]));
            return;
        }

        $this->addField(new FieldHTML('envelopeSender', [
            'label' => __('manager.setup.emailBounceAddress'),
            'description' => __('manager.setup.emailBounceAddress.disabled'),
        ]));
    }
}
