<?php
/**
 * @file classes/components/form/context/PKPAppearanceSetupForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPAppearanceSetupForm
 *
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for general website appearance setup, such as uploading
 *  a logo.
 */

namespace PKP\components\forms\context;

use PKP\components\forms\FieldOptions;
use PKP\components\forms\FieldRichTextarea;
use PKP\components\forms\FieldUploadImage;
use PKP\components\forms\FormComponent;
use PKP\plugins\PluginRegistry;

class PKPAppearanceSetupForm extends FormComponent
{
    public const FORM_APPEARANCE_SETUP = 'appearanceSetup';
    public $id = self::FORM_APPEARANCE_SETUP;
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
        $sidebarOptions = [];

        $currentBlocks = (array) $context->getData('sidebar');

        $plugins = PluginRegistry::loadCategory('blocks', true);

        foreach ($plugins as $pluginName => $plugin) {
            $sidebarOptions[] = [
                'value' => $pluginName,
                'label' => htmlspecialchars($plugin->getDisplayName()),
            ];
        }

        $this->addField(new FieldUploadImage('pageHeaderLogoImage', [
            'label' => __('manager.setup.logo'),
            'value' => $context->getData('pageHeaderLogoImage'),
            'isMultilingual' => true,
            'baseUrl' => $baseUrl,
            'options' => [
                'url' => $temporaryFileApiUrl,
            ],
        ]))
            ->addField(new FieldUploadImage('homepageImage', [
                'label' => __('manager.setup.homepageImage'),
                'tooltip' => __('manager.setup.homepageImage.description'),
                'value' => $context->getData('homepageImage'),
                'isMultilingual' => true,
                'baseUrl' => $baseUrl,
                'options' => [
                    'url' => $temporaryFileApiUrl,
                ],
            ]))
            ->addField(new FieldRichTextarea('pageFooter', [
                'label' => __('manager.setup.pageFooter'),
                'tooltip' => __('manager.setup.pageFooter.description'),
                'isMultilingual' => true,
                'value' => $context->getData('pageFooter'),
                'toolbar' => 'bold italic superscript subscript | link | blockquote bullist numlist | image | code',
                'plugins' => ['link','lists','image','code'],
                'uploadUrl' => $imageUploadUrl,
            ]))
            ->addField(new FieldOptions('sidebar', [
                'label' => __('manager.setup.layout.sidebar'),
                'isOrderable' => true,
                'value' => $currentBlocks,
                'options' => $sidebarOptions,
            ]));
    }
}
