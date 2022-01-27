<?php
/**
 * @file classes/components/forms/site/PKPSiteStatisticsForm.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
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
     * @param Site $site
     */
    public function __construct(string $action, array $locales, \PKP\site\Site $site)
    {
        $this->action = $action;
        $this->locales = $locales;

        $this->addGroup([
            'id' => 'archive',
        ])
            ->addField(new FieldOptions('archivedUsageStatsLogFiles', [
                'label' => __('manager.settings.statistics.archivedUsageStatsLogFiles'),
                'description' => __('manager.settings.statistics.archivedUsageStatsLogFiles.description'),
                'groupId' => 'archive',
                'type' => 'radio',
                'options' => [
                    [
                        'value' => 0,
                        'label' => __('manager.settings.statistics.archivedUsageStatsLogFiles.default'),
                    ],
                    [
                        'value' => 1,
                        'label' => __('manager.settings.statistics.archivedUsageStatsLogFiles.compress'),
                    ],
                ],
                'value' => $site->getData('archivedUsageStatsLogFiles') ? $site->getData('archivedUsageStatsLogFiles') : 0,
            ]))
            ->addGroup([
                'id' => 'geo',
            ])
            ->addField(new FieldOptions('enableGeoUsageStats', [
                'label' => __('manager.settings.statistics.geoUsageStats'),
                'description' => __('manager.settings.statistics.geoUsageStats.description'),
                'groupId' => 'geo',
                'type' => 'radio',
                'options' => [
                    [
                        'value' => 0,
                        'label' => __('manager.settings.statistics.geoUsageStats.disabled'),
                    ],
                    [
                        'value' => 1,
                        'label' => __('manager.settings.statistics.geoUsageStats.countryLevel'),
                    ],
                    [
                        'value' => 2,
                        'label' => __('manager.settings.statistics.geoUsageStats.regionLevel'),
                    ],
                    [
                        'value' => 3,
                        'label' => __('manager.settings.statistics.geoUsageStats.cityLevel'),
                    ],
                ],
                'value' => $site->getData('enableGeoUsageStats') ? $site->getData('enableGeoUsageStats') : 0,
            ]))
            ->addGroup([
                'id' => 'institution',
            ])
            ->addField(new FieldOptions('enableInstitutionUsageStats', [
                'label' => __('manager.settings.statistics.institutionUsageStats'),
                'description' => __('manager.settings.statistics.institutionUsageStats.description'),
                'groupId' => 'institution',
                'options' => [
                    [
                        'value' => true,
                        'label' => __('manager.settings.statistics.institutionUsageStats.enable'),
                    ],
                ],
                'default' => false,
                'value' => $site->getData('enableInstitutionUsageStats'),
            ]))
            ->addGroup([
                'id' => 'keepDaily',
            ])
            ->addField(new FieldOptions('usageStatsKeepDaily', [
                'label' => __('manager.settings.statistics.keepDaily'),
                'description' => __('manager.settings.statistics.keepDaily.description'),
                'groupId' => 'keepDaily',
                'options' => [
                    [
                        'value' => true,
                        'label' => __('manager.settings.statistics.keepDaily.option'),
                    ],
                ],
                'default' => false,
                'value' => $site->getData('usageStatsKeepDaily'),
            ]))
            ->addGroup([
                'id' => 'sushi',
            ])
            ->addField(new FieldOptions('siteSushiPlatform', [
                'label' => __('manager.settings.statistics.sushiPlatform'),
                'description' => __('manager.settings.statistics.sushiPlatform.description'),
                'groupId' => 'sushi',
                'options' => [
                    [
                        'value' => true,
                        'label' => __('manager.settings.statistics.sushiPlatform.siteSushiPlatform'),
                    ],
                ],
                'default' => false,
                'value' => $site->getData('siteSushiPlatform'),
            ]))
            ->addField(new FieldText('siteSushiPlatformID', [
                'label' => __('manager.settings.statistics.sushiPlatform.siteSushiPlatformID'),
                'description' => __('manager.settings.statistics.sushiPlatform.siteSushiPlatformID.description'),
                'groupId' => 'sushi',
                'value' => $site->getData('siteSushiPlatformID'),
                'isRequired' => true,
                'showWhen' => 'siteSushiPlatform',
            ]));
    }
}
