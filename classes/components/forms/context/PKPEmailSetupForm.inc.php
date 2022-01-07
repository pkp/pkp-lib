<?php
/**
 * @file classes/components/form/context/PKPEmailSetupForm.inc.php
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

use PKP\components\forms\FieldHTML;
use PKP\components\forms\FieldRichTextarea;
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

        $this->addField(new FieldRichTextarea('emailSignature', [
            'label' => __('manager.setup.emailSignature'),
            'tooltip' => __('manager.setup.emailSignature.description'),
            'value' => $context->getData('emailSignature'),
            'preparedContent' => [
                'contextName' => $context->getLocalizedName(),
                'senderName' => __('email.senderName'),
                'senderEmail' => __('email.senderEmail'),
                'mailingAddress' => htmlspecialchars(nl2br($context->getData('mailingAddress'))),
                'contactEmail' => htmlspecialchars($context->getData('contactEmail')),
                'contactName' => htmlspecialchars($context->getData('contactName')),
            ]
        ]));

        $this->buildEnveloperSenderField($context);
    }

    /**
     * Build the enveloper sender field
     *
     * @param Context $context Journal or Press to change settings for
     *
     */
    protected function buildEnveloperSenderField($context)
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
