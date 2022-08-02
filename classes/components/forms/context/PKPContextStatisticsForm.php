<?php
/**
 * @file classes/components/forms/context/PKPContextStatisticsForm.php
 *
 * Copyright (c) 2022 Simon Fraser University
 * Copyright (c) 2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPContextStatisticsForm
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for the context specific statistics settings.
 */

namespace PKP\components\forms\context;

use PKP\components\forms\FieldOptions;
use PKP\components\forms\FormComponent;
use PKP\context\Context;
use PKP\site\Site;

define('FORM_CONTEXT_STATISTICS', 'contextStatistics');

class PKPContextStatisticsForm extends FormComponent
{
    /** @copydoc FormComponent::$id */
    public $id = FORM_CONTEXT_STATISTICS;

    /** @copydoc FormComponent::$method */
    public $method = 'PUT';

    /**
     * Constructor
     *
     * @param string $action URL to submit the form to
     * @param array $locales Supported locales
     */
    public function __construct(string $action, array $locales, Site $site, Context $context)
    {
        $this->action = $action;
        $this->locales = $locales;

        $possibleGeoOptions = [
            'disabled' => __('manager.settings.statistics.geoUsageStats.disabled'),
            'country' => __('manager.settings.statistics.geoUsageStats.countryLevel'),
            'country+region' => __('manager.settings.statistics.geoUsageStats.regionLevel'),
            'country+region+city' => __('manager.settings.statistics.geoUsageStats.cityLevel'),
        ];
        $geoOptions = [];
        foreach ($possibleGeoOptions as $value => $label) {
            $geoOptions[] = [
                'value' => $value,
                'label' => $label,
            ];
            if ($site->getData('enableGeoUsageStats') === $value) {
                break;
            }
        }

        if ($site->getData('enableGeoUsageStats') && $site->getData('enableGeoUsageStats') !== 'disabled') {
            $this->addField(new FieldOptions('enableGeoUsageStats', [
                'label' => __('manager.settings.statistics.geoUsageStats'),
                'type' => 'radio',
                'options' => $geoOptions,
                'value' => $context->getData('enableGeoUsageStats') !== null ? $context->getData('enableGeoUsageStats') : $site->getData('enableGeoUsageStats'),
            ]));
        }
        if ($site->getData('enableInstitutionUsageStats')) {
            $this->addField(new FieldOptions('enableInstitutionUsageStats', [
                'label' => __('manager.settings.statistics.institutionUsageStats'),
                'options' => [
                    [
                        'value' => true,
                        'label' => __('manager.settings.statistics.institutionUsageStats.enable'),
                    ],
                ],
                'default' => false,
                'value' => $context->getData('enableInstitutionUsageStats') !== null ? $context->getData('enableInstitutionUsageStats') : $site->getData('enableInstitutionUsageStats'),
            ]));
        }
    }
}
