<?php
/**
 * @file classes/components/form/context/PKPAppearanceAdvancedForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPAppearanceAdvancedForm
 *
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for advanced settings under the website appearance tab.
 */

namespace PKP\components\forms\context;

use PKP\components\forms\FieldRichTextarea;
use PKP\components\forms\FieldUpload;
use PKP\components\forms\FieldUploadImage;
use PKP\components\forms\FormComponent;

class PKPAppearanceAdvancedForm extends FormComponent
{
    public const FORM_APPEARANCE_ADVANCED = 'appearanceAdvanced';
    public $id = self::FORM_APPEARANCE_ADVANCED;
    public $method = 'PUT';

    /**
     * Constructor
     *
     * @param string $action URL to submit the form to
     * @param array $locales Supported locales
     * @param \PKP\context\Context $context Journal or Press to change settings for
     * @param string $baseUrl Site's base URL. Used for image previews.
     * @param string $temporaryFileApiUrl URL to upload files to
     * @param string $imageUploadUrl The API endpoint for images uploaded through the rich text field
     */
    public function __construct($action, $locales, $context, $baseUrl, $temporaryFileApiUrl, $imageUploadUrl)
    {
        $this->action = $action;
        $this->locales = $locales;

        $this->addField(new FieldUpload('styleSheet', [
            'label' => __('manager.setup.useStyleSheet'),
            'value' => $context->getData('styleSheet'),
            'options' => [
                'url' => $temporaryFileApiUrl,
                'acceptedFiles' => '.css',
            ],
        ]))
            ->addField(new FieldUploadImage('favicon', [
                'label' => __('manager.setup.favicon'),
                'value' => $context->getData('favicon'),
                'isMultilingual' => true,
                'baseUrl' => $baseUrl,
                'options' => [
                    'url' => $temporaryFileApiUrl,
                    'acceptedFiles' => 'image/x-icon,image/png,image/gif',
                ],
            ]))
            ->addField(new FieldRichTextarea('additionalHomeContent', [
                'label' => __('manager.setup.additionalContent'),
                'description' => __('manager.setup.additionalContent.description'),
                'isMultilingual' => true,
                'value' => $context->getData('additionalHomeContent'),
                'toolbar' => 'bold italic superscript subscript | link | blockquote bullist numlist | image | code',
                'plugins' => ['link','lists','image','code'],
                'uploadUrl' => $imageUploadUrl,
            ]));
    }
}
