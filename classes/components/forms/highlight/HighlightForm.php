<?php
/**
 * @file classes/components/form/highlight/HighlightForm.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class HighlightForm
 *
 * @ingroup classes_controllers_form
 *
 * @brief A form for adding or editing a highlight
 */

namespace PKP\components\forms\highlight;

use APP\core\Application;
use PKP\components\forms\FieldRichText;
use PKP\components\forms\FieldText;
use PKP\components\forms\FieldUploadImage;
use PKP\components\forms\FormComponent;
use PKP\context\Context;

class HighlightForm extends FormComponent
{
    public const FORM_HIGHLIGHT = 'highlight';
    public $id = self::FORM_HIGHLIGHT;
    public $method = 'POST';
    public ?Context $context;

    /**
     * Constructor
     *
     * @param string $action URL to submit the form to
     */
    public function __construct(string $action, string $baseUrl, string $temporaryFileApiUrl, ?Context $context = null)
    {
        $this->action = $action;
        $this->context = $context;
        $this->locales = $this->getLocales($context);

        $this->addField(new FieldRichText('title', [
            'label' => __('common.title'),
            'isMultilingual' => true,
        ]))
            ->addField(new FieldRichText('description', [
                'label' => __('common.description'),
                'isMultilingual' => true,
            ]))
            ->addField(new FieldText('url', [
                'label' => __('common.url'),
                'description' => __('manager.highlights.url.description'),
                'size' => 'large',
            ]))
            ->addField(new FieldText('urlText', [
                'label' => __('manager.highlights.urlText'),
                'description' => __('manager.highlights.urlText.description'),
                'size' => 'small',
                'isMultilingual' => true,
            ]))
            ->addField(new FieldUploadImage('image', [
                'label' => __('manager.highlights.image'),
                'baseUrl' => $baseUrl,
                'options' => [
                    'url' => $temporaryFileApiUrl,
                ],
            ]));
    }

    /**
     * Get the locales formatted for display in the form
     */
    protected function getLocales(?Context $context = null): array
    {
        $localeNames = $this?->context?->getSupportedFormLocaleNames()
            ?? Application::get()->getRequest()->getSite()->getSupportedLocaleNames();

        return array_map(fn (string $locale, string $name) => ['key' => $locale, 'label' => $name], array_keys($localeNames), $localeNames);
    }
}
