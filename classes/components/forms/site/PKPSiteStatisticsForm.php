<?php
/**
 * @file classes/components/forms/site/PKPSiteStatisticsForm.php
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
use PKP\statistics\PKPStatisticsHelper;
use PKP\task\FileLoader;

define('FORM_SITE_STATISTICS', 'siteStatistics');

class PKPSiteStatisticsForm extends FormComponent
{
    public const COLLECTION_GROUP = 'collection';
    public const STORAGE_GROUP = 'storage';
    public const SUSHI_GROUP = 'sushi';

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

        $usageStatsFileDir = PKPStatisticsHelper::getUsageStatsDirPath() . '/' . FileLoader::FILE_LOADER_PATH_ARCHIVE;

        $this->addGroup([
            'id' => self::COLLECTION_GROUP,
            'label' => __('admin.settings.statistics.collection'),
            'description' => __('admin.settings.statistics.collection.description'),
        ])
            ->addField(new FieldOptions('enableGeoUsageStats', [
                'label' => __('manager.settings.statistics.geoUsageStats'),
                'description' => __('admin.settings.statistics.geo.description'),
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
                'groupId' => self::COLLECTION_GROUP,
            ]))
            ->addField(new FieldOptions('enableInstitutionUsageStats', [
                'label' => __('manager.settings.statistics.institutionUsageStats'),
                'description' => __('admin.settings.statistics.institutions.description'),
                'options' => [
                    [
                        'value' => true,
                        'label' => __('manager.settings.statistics.institutionUsageStats.enable'),
                    ],
                ],
                'value' => $site->getData('enableInstitutionUsageStats'),
                'groupId' => self::COLLECTION_GROUP,
            ]))
            ->addGroup([
                'id' => self::STORAGE_GROUP,
                'label' => __('admin.settings.statistics.storage'),
                'description' => __('admin.settings.statistics.storage.description'),
            ])
            ->addField(new FieldOptions('keepDailyUsageStats', [
                'label' => __('admin.settings.statistics.keepDaily'),
                'description' => __('admin.settings.statistics.keepDaily.description'),
                'type' => 'radio',
                'options' => [
                    [
                        'value' => false,
                        'label' => __('admin.settings.statistics.keepDaily.discard'),
                    ],
                    [
                        'value' => true,
                        'label' => __('admin.settings.statistics.keepDaily.keep'),
                    ],
                ],
                'value' => $site->getData('keepDailyUsageStats'),
                'groupId' => self::STORAGE_GROUP,
            ]))
            ->addField(new FieldOptions('compressStatsLogs', [
                'label' => __('admin.settings.statistics.compressStatsLogs.label'),
                'description' => __('admin.settings.statistics.compressStatsLogs.description', ['path' => $usageStatsFileDir]),
                'type' => 'radio',
                'options' => [
                    [
                        'value' => false,
                        'label' => __('admin.settings.statistics.compressStatsLogs.default'),
                    ],
                    [
                        'value' => true,
                        'label' => __('admin.settings.statistics.compressStatsLogs.compress'),
                    ],
                ],
                'value' => $site->getData('compressStatsLogs') ? $site->getData('compressStatsLogs') : false,
                'groupId' => self::STORAGE_GROUP,
            ]))
            ->addGroup([
                'id' => self::SUSHI_GROUP,
                'label' => __('admin.settings.statistics.sushi'),
                'description' => __('admin.settings.statistics.sushi.description'),
            ])
            ->addField(new FieldOptions('isSushiApiPublic', [
                'label' => __('manager.settings.statistics.publicSushiApi'),
                'description' => __('admin.settings.statistics.sushi.public.description'),
                'type' => 'radio',
                'options' => [
                    [
                        'value' => true,
                        'label' => __('manager.settings.statistics.publicSushiApi.public'),
                    ],
                    [
                        'value' => false,
                        'label' => __('admin.settings.statistics.publicSushiApi.private'),
                    ],
                ],
                'value' => $site->getData('isSushiApiPublic') ? $site->getData('isSushiApiPublic') : true,
                'groupId' => self::SUSHI_GROUP,
            ]))
            ->addField(new FieldOptions('isSiteSushiPlatform', [
                'label' => __('admin.settings.statistics.sushiPlatform'),
                'description' => __('admin.settings.statistics.sushiPlatform.description'),
                'groupId' => 'sushi',
                'options' => [
                    [
                        'value' => true,
                        'label' => __('admin.settings.statistics.sushiPlatform.isSiteSushiPlatform'),
                    ],
                ],
                'value' => $site->getData('isSiteSushiPlatform'),
                'groupId' => self::SUSHI_GROUP,
            ]))
            ->addField(new FieldText('sushiPlatformID', [
                'label' => __('admin.settings.statistics.sushiPlatform.sushiPlatformID'),
                'description' => __('admin.settings.statistics.sushiPlatform.sushiPlatformID.description'),
                'value' => $site->getData('sushiPlatformID'),
                'showWhen' => 'isSiteSushiPlatform',
                'groupId' => self::SUSHI_GROUP,
            ]));
    }
}
