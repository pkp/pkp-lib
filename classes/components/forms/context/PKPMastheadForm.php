<?php
/**
 * @file classes/components/form/context/PKPMastheadForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPMastheadForm
 *
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for configuring a context's masthead details.
 */

namespace PKP\components\forms\context;

use PKP\components\forms\FieldRichTextarea;
use PKP\components\forms\FieldSelect;
use PKP\components\forms\FieldText;
use PKP\components\forms\FormComponent;
use PKP\facades\Locale;

class PKPMastheadForm extends FormComponent
{
    public const FORM_MASTHEAD = 'masthead';
    public $id = self::FORM_MASTHEAD;
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

        $countries = [];
        foreach (Locale::getCountries() as $country) {
            $countries[] = [
                'value' => $country->getAlpha2(),
                'label' => $country->getLocalName()
            ];
        }
        usort($countries, function ($a, $b) {
            return strcmp($a['label'], $b['label']);
        });

        $this->addGroup([
            'id' => 'identity',
            'label' => __('manager.setup.identity'),
        ])
            ->addField(new FieldText('name', [
                'label' => __('manager.setup.contextTitle'),
                'size' => 'large',
                'isRequired' => true,
                'isMultilingual' => true,
                'groupId' => 'identity',
                'value' => $context->getData('name'),
            ]))
            ->addField(new FieldText('acronym', [
                'label' => __('manager.setup.contextInitials'),
                'size' => 'small',
                'isRequired' => true,
                'isMultilingual' => true,
                'groupId' => 'identity',
                'value' => $context->getData('acronym'),
            ]))
            ->addGroup([
                'id' => 'publishing',
                'label' => __('manager.setup.publishing'),
                'description' => __('manager.setup.publishingDescription'),
            ])
            ->addField(new FieldSelect('country', [
                'groupId' => 'publishing',
                'label' => __('common.country'),
                'description' => __('manager.setup.selectCountry'),
                'options' => $countries,
                'isRequired' => true,
                'value' => $context ? $context->getData('country') : null,
            ]))
            ->addGroup([
                'id' => 'editorialMasthead',
                'label' => __('common.editorialMasthead'),
            ])
            ->addField(new FieldRichTextarea('editorialHistory', [
                'label' => __('common.editorialHistory'),
                'description' => __('manager.setup.editorialMasthead.editorialHistory.description'),
                'isMultilingual' => true,
                'groupId' => 'editorialMasthead',
                'toolbar' => 'bold italic superscript subscript | link | blockquote bullist numlist | image | code',
                'plugins' => 'paste,link,lists,image,code',
                'uploadUrl' => $imageUploadUrl,
                'value' => $context->getData('editorialHistory'),
            ]))
            ->addGroup([
                'id' => 'about',
                'label' => __('common.description'),
            ])
            ->addField(new FieldRichTextarea('description', [
                'label' => __('manager.setup.contextSummary'),
                'description' => __('manager.setup.contextSummary.description'),
                'isMultilingual' => true,
                'groupId' => 'about',
                'value' => $context->getData('description'),
            ]))
            ->addField(new FieldRichTextarea('about', [
                'label' => __('manager.setup.contextAbout'),
                'description' => __('manager.setup.contextAbout.description'),
                'isMultilingual' => true,
                'size' => 'large',
                'groupId' => 'about',
                'toolbar' => 'bold italic superscript subscript | link | blockquote bullist numlist | image | code',
                'plugins' => 'paste,link,lists,image,code',
                'uploadUrl' => $imageUploadUrl,
                'value' => $context->getData('about'),
            ]));
    }
}
