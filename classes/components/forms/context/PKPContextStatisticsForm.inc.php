<?php
/**
 * @file classes/components/forms/context/PKPContextStatisticsForm.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
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
     * @param Site $site
     * @param Context $context
     */
    public function __construct(string $action, array $locales, \PKP\site\Site $site, \PKP\context\Context $context)
    {
        $this->action = $action;
        $this->locales = $locales;

        $geoOptionsValues = [
            __('manager.settings.statistics.geoUsageStats.disabled'),
            __('manager.settings.statistics.geoUsageStats.countryLevel'),
            __('manager.settings.statistics.geoUsageStats.regionLevel'),
            __('manager.settings.statistics.geoUsageStats.cityLevel'),
        ];
        $geoOptions = [
            [
                'value' => 0,
                'label' => $geoOptionsValues[0],
            ],
        ];
        for ($i = 1; $i <= $site->getData('enableGeoUsageStats'); $i++) {
            $geoOptions[] = [
                'value' => $i,
                'label' => $geoOptionsValues[$i],
            ];
        }

        $this->addGroup([
            'id' => 'geo',
        ])
            ->addField(new FieldOptions('enableGeoUsageStats', [
                'label' => __('manager.settings.statistics.geoUsageStats'),
                'groupId' => 'geo',
                'type' => 'radio',
                'options' => $geoOptions,
                'value' => $context->getData('enableGeoUsageStats') !== null ? $context->getData('enableGeoUsageStats') : $site->getData('enableGeoUsageStats'),
            ]));
        if ($site->getData('enableInstitutionUsageStats')) {
            $this->addGroup([
                'id' => 'institution',
            ])
                ->addField(new FieldOptions('enableInstitutionUsageStats', [
                    'label' => __('manager.settings.statistics.institutionUsageStats'),
                    'groupId' => 'institution',
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
