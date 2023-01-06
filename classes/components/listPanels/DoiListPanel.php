<?php

/**
 * @file classes/components/listPanels/DoiListPanel.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DoiListPanel
 * @ingroup classes_components_list
 *
 * @brief A ListPanel component for viewing and editing DOIs
 */

namespace APP\components\listPanels;

use APP\core\Application;
use APP\template\TemplateManager;
use PKP\components\listPanels\PKPDoiListPanel;
use PKP\submission\PKPSubmission;

class DoiListPanel extends PKPDoiListPanel
{
    /**
     * Add any application-specific config to the list panel setup
     */
    protected function setAppConfig(array &$config): void
    {
        $config['executeActionApiUrl'] = $this->doiApiUrl . '/submissions';
        $config['filters'][] = [
            'heading' => __('manager.dois.publicationStatus'),
            'filters' => [
                [
                    'title' => __('publication.status.published'),
                    'param' => 'status',
                    'value' => (string) PKPSubmission::STATUS_PUBLISHED
                ],
                [
                    'title' => __('publication.status.unpublished'),
                    'param' => 'status',
                    'value' => PKPSubmission::STATUS_QUEUED . ', ' . PKPSubmission::STATUS_SCHEDULED
                ]
            ]
        ];

        // Provide required locale keys
        $request = Application::get()->getRequest();
        $templateMgr = TemplateManager::getManager($request);

        $templateMgr->setLocaleKeys(['submission.publication']);
    }
}
