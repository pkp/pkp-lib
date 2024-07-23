<?php
/**
 * @file classes/components/forms/context/PKPContextStatisticsForm.php
 *
 * Copyright (c) 2022 Simon Fraser University
 * Copyright (c) 2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPContextStatisticsForm
 *
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for the context specific statistics settings.
 */

namespace PKP\components\forms\context;

use PKP\components\forms\FieldOptions;
use PKP\components\forms\FormComponent;
use PKP\context\Context;
use PKP\site\Site;
use PKP\statistics\PKPStatisticsHelper;

class PKPContextStatisticsForm extends FormComponent
{
    public const FORM_CONTEXT_STATISTICS = 'contextStatistics';
    public $id = self::FORM_CONTEXT_STATISTICS;
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
            PKPStatisticsHelper::STATISTICS_SETTING_COUNTRY => __('manager.settings.statistics.geoUsageStats.countryLevel'),
            PKPStatisticsHelper::STATISTICS_SETTING_REGION => __('manager.settings.statistics.geoUsageStats.regionLevel'),
            PKPStatisticsHelper::STATISTICS_SETTING_CITY => __('manager.settings.statistics.geoUsageStats.cityLevel'),
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
        $selectedGeoOption = $site->getData('enableGeoUsageStats');
        if ($context->getData('enableGeoUsageStats') != null &&
            str_starts_with($selectedGeoOption, $context->getData('enableGeoUsageStats'))) {
            $selectedGeoOption = $context->getData('enableGeoUsageStats');
        }

        if ($site->getData('enableGeoUsageStats') && $site->getData('enableGeoUsageStats') !== 'disabled') {
            $this->addField(new FieldOptions('enableGeoUsageStats', [
                'label' => __('manager.settings.statistics.geoUsageStats'),
                'description' => __('manager.settings.statistics.geoUsageStats.description'),
                'type' => 'radio',
                'options' => $geoOptions,
                'value' => $selectedGeoOption,
            ]));
        }
        if ($site->getData('enableInstitutionUsageStats')) {
            $this->addField(new FieldOptions('enableInstitutionUsageStats', [
                'label' => __('manager.settings.statistics.institutionUsageStats'),
                'description' => __('manager.settings.statistics.institutionUsageStats.description'),
                'options' => [
                    [
                        'value' => true,
                        'label' => __('manager.settings.statistics.institutionUsageStats.enable'),
                    ],
                ],
                'value' => $context->getData('enableInstitutionUsageStats') !== null ? $context->getData('enableInstitutionUsageStats') : $site->getData('enableInstitutionUsageStats'),
            ]));
        }
        if ($site->getData('isSushiApiPublic') !== null && $site->getData('isSushiApiPublic')) {
            $this->addField(new FieldOptions('isSushiApiPublic', [
                'label' => __('manager.settings.statistics.publicSushiApi'),
                'description' => __('manager.settings.statistics.publicSushiApi.description'),
                'options' => [
                    [
                        'value' => true,
                        'label' => __('manager.settings.statistics.publicSushiApi.public'),
                    ],
                ],
                'value' => $context->getData('isSushiApiPublic') !== null ? $context->getData('isSushiApiPublic') : $site->getData('isSushiApiPublic'),
            ]));
        }
    }
}
