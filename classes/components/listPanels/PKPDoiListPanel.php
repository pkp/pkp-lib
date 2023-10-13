<?php

/**
 * @file classes/components/listPanels/PKPDoiListPanel.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPDoiListPanel
 *
 * @ingroup classes_components_list
 *
 * @brief A ListPanel component for viewing and editing DOIs
 */

namespace PKP\components\listPanels;

use APP\core\Application;
use APP\template\TemplateManager;
use PKP\doi\Doi;
use PKP\plugins\Hook;
use PKP\submission\PKPSubmission;

abstract class PKPDoiListPanel extends ListPanel
{
    /** @var string URL to the API endpoint where items can be retrieved */
    public $apiUrl = '';

    /** @var int How many items to display on one page in this list */
    public $count = 30;

    /** @var array Query parameters to pass if this list executes GET requests */
    public $getParams = [];

    /** @var int Max number of items available to display in this list panel */
    public $itemsMax = 0;

    /** @var boolean Should items be loaded after the component is mounted? */
    public $lazyLoad = false;

    /** @var string DOI prefix set in DOI settings */
    public $doiPrefix = '';

    public ?\stdClass $registrationAgencyInfo = null;

    /** @var array Which publishing objects have DOIs enabled */
    public array $enabledDoiTypes = [];

    /** @var bool Whether to include a DOI per publication version */
    public bool $versionDois = false;

    /** @var string DOI API url for handling DOI operations */
    public $doiApiUrl = '';

    /** @var string PubObject type, e.g. 'submission' */
    public string $itemType = '';

    /**
     * Convert the object into an assoc array ready to be json_encoded
     * and passed to the UI component
     *
     * @return array Configuration data
     *
     * @hook DoiListPanel::setConfig [[&$config]]
     */
    public function getConfig()
    {
        $config = parent::getConfig();
        $config['apiUrl'] = $this->apiUrl;
        $config['doiApiUrl'] = $this->doiApiUrl;
        $config['count'] = $this->count;
        $config['lazyLoad'] = $this->lazyLoad;
        $config['itemsMax'] = $this->itemsMax;
        $config['itemType'] = $this->itemType;
        $config['enabledDoiTypes'] = $this->enabledDoiTypes;
        $config['versionDois'] = $this->versionDois;
        $config['registrationAgencyInfo'] = $this->registrationAgencyInfo;
        $config['doiPrefix'] = $this->doiPrefix;
        $config['filters'][] = [
            'heading' => __('common.status'),
            'filters' => [
                [
                    'title' => __('manager.dois.status.needsDoi'),
                    'param' => 'hasDois',
                    'value' => '0'
                ],
                [
                    'title' => __('manager.dois.filters.doiAssigned'),
                    'param' => 'hasDois',
                    'value' => '1',
                ],
            ],
        ];
        $config['filters'][] = [
            'heading' => __('manager.setup.dois.registration'),
            'filters' => [
                [
                    'title' => __('manager.dois.status.unregistered'),
                    'param' => 'unregistered',
                    'value' => 'true'
                ],
                [
                    'title' => __('manager.dois.status.submitted'),
                    'param' => 'doiStatus',
                    'value' => Doi::STATUS_SUBMITTED
                ],
                [
                    'title' => __('manager.dois.status.registered'),
                    'param' => 'doiStatus',
                    'value' => Doi::STATUS_REGISTERED
                ],
                [
                    'title' => __('manager.dois.status.error.filterTitle'),
                    'param' => 'doiStatus',
                    'value' => Doi::STATUS_ERROR
                ],
                [
                    'title' => __('manager.dois.status.stale'),
                    'param' => 'doiStatus',
                    'value' => Doi::STATUS_STALE
                ],
            ],
        ];
        $config['publishedStatuses'] = [
            'name' => 'status',
            'published' => [PKPSubmission::STATUS_PUBLISHED],
            'unpublished' => [PKPSubmission::STATUS_QUEUED, PKPSubmission::STATUS_SCHEDULED],
        ];

        // Provide required locale keys
        $request = Application::get()->getRequest();
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->setConstants([
            'DOI_STATUS_SUBMITTED' => Doi::STATUS_SUBMITTED,
            'DOI_STATUS_UNREGISTERED' => Doi::STATUS_UNREGISTERED,
            'DOI_STATUS_REGISTERED' => Doi::STATUS_REGISTERED,
            'DOI_STATUS_ERROR' => Doi::STATUS_ERROR,
            'DOI_STATUS_STALE' => Doi::STATUS_STALE,
        ]);

        $this->setAppConfig($config);

        Hook::call('DoiListPanel::setConfig', [&$config]);

        // Check added here in case hook adds additional getParams
        $config['getParams'] = empty($config['getParams']) ? new \stdClass() : $config['getParams'];

        return $config;
    }

    /**
     * Add any application-specific config to the list panel setup
     */
    abstract protected function setAppConfig(array &$config): void;
}
