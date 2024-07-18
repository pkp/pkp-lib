<?php
/**
 * @file classes/components/form/context/PKPPrivacyForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPPrivacyForm
 *
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for configuring a context's privacy statement.
 */

namespace PKP\components\forms\context;

use PKP\components\forms\FieldRichTextarea;
use PKP\components\forms\FormComponent;

class PKPPrivacyForm extends FormComponent
{
    public const FORM_PRIVACY = 'privacy';
    public $id = self::FORM_PRIVACY;
    public $method = 'PUT';

    /**
     * Constructor
     *
     * @param string $action URL to submit the form to
     * @param array $locales Supported locales
     * @param \PKP\context\Context $context Journal or Press to change settings for
     * @param string $imageUploadUrl The API endpoint for images uploaded through the rich text field
     */
    public function __construct($action, $locales, $context, $imageUploadUrl)
    {
        $this->action = $action;
        $this->locales = $locales;

        $this->addField(new FieldRichTextArea('privacyStatement', [
            'label' => __('manager.setup.privacyStatement'),
            'description' => __('manager.setup.privacyStatement.description'),
            'isMultilingual' => true,
            'value' => $context->getData('privacyStatement'),
            'toolbar' => 'bold italic superscript subscript | link | blockquote bullist numlist | image | code',
            'plugins' => 'paste,link,lists,image,code',
            'uploadUrl' => $imageUploadUrl,
        ]));
    }
}
