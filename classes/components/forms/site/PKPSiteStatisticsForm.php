<?php
/**
 * @file classes/components/forms/site/PKPSiteStatisticsForm.inc.php
 *
 * Copyright (c) 2022 Simon Fraser University
 * Copyright (c) 2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPSiteStatisticsForm
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for the site statistics settings.
 */

namespace PKP\components\forms\site;

use PKP\components\forms\FieldOptions;
use PKP\components\forms\FieldText;
use PKP\components\forms\FormComponent;
use PKP\site\Site;

define('FORM_SITE_STATISTICS', 'siteStatistics');

class PKPSiteStatisticsForm extends FormComponent
{
    /** @copydoc FormComponent::$id */
    public $id = FORM_SITE_STATISTICS;

    /** @copydoc FormComponent::$method */
    public $method = 'PUT';

    /**
     * Constructor
     *
     * @param string $action URL to submit the form to
     * @param array $locales Supported locales
     */
    public function __construct(string $action, array $locales, Site $site)
    {
        $this->action = $action;
        $this->locales = $locales;

        $this->addField(new FieldOptions('compressStatsLogs', [
            'label' => __('archive.archives'),
            'description' => __('manager.settings.statistics.compressStatsLogs.description'),
            'type' => 'radio',
            'options' => [
                [
                    'value' => false,
                    'label' => __('manager.settings.statistics.compressStatsLogs.default'),
                ],
                [
                    'value' => true,
                    'label' => __('manager.settings.statistics.compressStatsLogs.compress'),
                ],
            ],
            'value' => $site->getData('compressStatsLogs') ? $site->getData('compressStatsLogs') : false,
        ]))
            ->addField(new FieldOptions('enableGeoUsageStats', [
                'label' => __('manager.settings.statistics.geoUsageStats'),
                'description' => __('manager.settings.statistics.geoUsageStats.description'),
                'type' => 'radio',
                'options' => [
                    [
                        'value' => 'disabled',
                        'label' => __('manager.settings.statistics.geoUsageStats.disabled'),
                    ],
                    [
                        'value' => 'country',
                        'label' => __('manager.settings.statistics.geoUsageStats.countryLevel'),
                    ],
                    [
                        'value' => 'country+region',
                        'label' => __('manager.settings.statistics.geoUsageStats.regionLevel'),
                    ],
                    [
                        'value' => 'country+region+city',
                        'label' => __('manager.settings.statistics.geoUsageStats.cityLevel'),
                    ],
                ],
                'value' => $site->getData('enableGeoUsageStats') ? $site->getData('enableGeoUsageStats') : 'disabled',
            ]))
            ->addField(new FieldOptions('enableInstitutionUsageStats', [
                'label' => __('manager.settings.statistics.institutionUsageStats'),
                'description' => __('manager.settings.statistics.institutionUsageStats.description'),
                'options' => [
                    [
                        'value' => true,
                        'label' => __('manager.settings.statistics.institutionUsageStats.enable'),
                    ],
                ],
                'default' => false,
                'value' => $site->getData('enableInstitutionUsageStats'),
            ]))
            ->addField(new FieldOptions('keepDailyUsageStats', [
                'label' => __('manager.settings.statistics.keepDaily'),
                'description' => __('manager.settings.statistics.keepDaily.description'),
                'options' => [
                    [
                        'value' => true,
                        'label' => __('manager.settings.statistics.keepDaily.option'),
                    ],
                ],
                'default' => false,
                'value' => $site->getData('keepDailyUsageStats'),
            ]))
            ->addField(new FieldOptions('isSiteSushiPlatform', [
                'label' => __('manager.settings.statistics.sushiPlatform'),
                'description' => __('manager.settings.statistics.sushiPlatform.description'),
                'groupId' => 'sushi',
                'options' => [
                    [
                        'value' => true,
                        'label' => __('manager.settings.statistics.sushiPlatform.isSiteSushiPlatform'),
                    ],
                ],
                'default' => false,
                'value' => $site->getData('isSiteSushiPlatform'),
            ]))
            ->addField(new FieldText('sushiPlatformID', [
                'label' => __('manager.settings.statistics.sushiPlatform.sushiPlatformID'),
                'description' => __('manager.settings.statistics.sushiPlatform.sushiPlatformID.description'),
                'value' => $site->getData('sushiPlatformID'),
                'showWhen' => 'isSiteSushiPlatform',
            ]));
    }
}
