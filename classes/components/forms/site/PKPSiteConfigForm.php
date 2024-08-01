<?php
/**
 * @file classes/components/form/site/PKPSiteConfigForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPSiteConfigForm
 *
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for the site config settings.
 */

namespace PKP\components\forms\site;

use PKP\components\forms\FieldOptions;
use PKP\components\forms\FieldSelect;
use PKP\components\forms\FieldText;
use PKP\components\forms\FormComponent;

class PKPSiteConfigForm extends FormComponent
{
    public const FORM_SITE_CONFIG = 'siteConfig';
    /** @copydoc FormComponent::$id */
    public $id = self::FORM_SITE_CONFIG;

    /** @copydoc FormComponent::$method */
    public $method = 'PUT';

    /**
     * Constructor
     *
     * @param string $action URL to submit the form to
     * @param array $locales Supported locales
     * @param \PKP\site\Site $site
     */
    public function __construct($action, $locales, $site)
    {
        $this->action = $action;
        $this->locales = $locales;

        $contextsIterator = app()->get('context')->getMany(['isEnabled' => true]);

        $this->addField(new FieldText('title', [
            'label' => __('admin.settings.siteTitle'),
            'isRequired' => true,
            'isMultilingual' => true,
            'value' => $site->getData('title'),
        ]));

        $options = [['value' => '', 'label' => '']];
        foreach ($contextsIterator as $context) {
            $options[] = [
                'value' => $context->getId(),
                'label' => htmlspecialchars($context->getLocalizedData('name')),
            ];
        }
        if (count($options) > 1) {
            $this->addField(new FieldSelect('redirectContextId', [
                'label' => __('admin.settings.redirect'),
                'description' => __('admin.settings.redirectInstructions'),
                'options' => $options,
                'value' => $site->getRedirect(),
            ]));
        }

        $this->addField(new FieldText('minPasswordLength', [
            'label' => __('admin.settings.minPasswordLength'),
            'isRequired' => true,
            'size' => 'small',
            'value' => $site->getData('minPasswordLength'),
        ]));

        $this->addField(new FieldOptions('disableSharedReviewerStatistics', [
            'label' => __('admin.settings.sharedReviewerStatistics'),
            'description' => __('admin.settings.sharedReviewerStatistics.description'),
            'options' => [
                ['value' => true, 'label' => __('admin.settings.sharedReviewerStatistics.disable')]
            ],
            'value' => (bool) $site->getData('disableSharedReviewerStatistics'),
        ]));
    }
}
