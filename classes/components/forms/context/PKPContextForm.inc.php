<?php
/**
 * @file classes/components/form/context/PKPContextForm.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPContextForm
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for adding and editing a context from the admin area.
 */

namespace PKP\components\forms\context;

use PKP\components\forms\FieldOptions;
use PKP\components\forms\FieldRichTextarea;
use PKP\components\forms\FieldSelect;
use PKP\components\forms\FieldText;
use PKP\components\forms\FormComponent;
use PKP\facades\Locale;

define('FORM_CONTEXT', 'context');

class PKPContextForm extends FormComponent
{
    /** @copydoc FormComponent::$id */
    public $id = FORM_CONTEXT;

    /** @copydoc FormComponent::$method */
    public $method = 'POST';

    /**
     * Constructor
     *
     * @param string $action URL to submit the form to
     * @param array $locales Supported locales
     * @param string $baseUrl Base URL for the site
     * @param Context $context Journal or Press to change settings for
     */
    public function __construct($action, $locales, $baseUrl, $context)
    {
        $this->action = $action;
        $this->locales = $locales;
        $this->method = $context ? 'PUT' : 'POST';

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

        $this->addField(new FieldText('name', [
            'label' => __('manager.setup.contextTitle'),
            'isRequired' => true,
            'isMultilingual' => true,
            'value' => $context ? $context->getData('name') : null,
        ]))
            ->addField(new FieldText('acronym', [
                'label' => __('manager.setup.contextInitials'),
                'size' => 'small',
                'isRequired' => true,
                'isMultilingual' => true,
                'groupId' => 'identity',
                'value' => $context ? $context->getData('acronym') : null,
            ]))
            ->addField(new FieldSelect('country', [
                'label' => __('common.country'),
                'description' => __('manager.setup.selectCountry'),
                'options' => $countries,
                'value' => $context ? $context->getData('country') : null,
            ]))
            ->addField(new FieldRichTextarea('description', [
                'label' => __('admin.contexts.contextDescription'),
                'isMultilingual' => true,
                'value' => $context ? $context->getData('description') : null,
            ]))
            ->addField(new FieldText('urlPath', [
                'label' => __('context.path'),
                'isRequired' => true,
                'value' => $context ? $context->getData('urlPath') : null,
                'prefix' => $baseUrl . '/',
                'size' => 'large',
            ]));

        if (!$context && count($locales) > 1) {
            $localeOptions = [];
            foreach ($locales as $locale) {
                $localeOptions[] = [
                    'value' => $locale['key'],
                    'label' => $locale['label'],
                ];
            }
            $this->addField(new FieldOptions('supportedLocales', [
                'label' => __('common.languages'),
                'isRequired' => true,
                'value' => [],
                'options' => $localeOptions,
            ]))
                ->addField(new FieldOptions('primaryLocale', [
                    'label' => __('locale.primary'),
                    'type' => 'radio',
                    'isRequired' => true,
                    'value' => null,
                    'options' => $localeOptions,
                ]));
        }
    }
}
