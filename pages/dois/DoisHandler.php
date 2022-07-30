<?php

/**
 * @file /pages/dois/DoiManagementHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DoisHandler
 * @ingroup pages_doi
 *
 * @brief Handle requests for DOI management functions.
 */

namespace APP\pages\dois;

use PKP\core\PKPApplication;
use APP\components\listPanels\DoiListPanel;
use PKP\pages\dois\PKPDoisHandler;

class DoisHandler extends PKPDoisHandler
{
    /**
     * Set app-specific state components to appear on DOI management page
     */
    protected function getAppStateComponents(\APP\core\Request $request, array $enabledDoiTypes, array $commonArgs): array
    {
        $context = $request->getContext();

        $stateComponents = [];

        if (!empty($enabledDoiTypes)) {
            $submissionDoiListPanel = new DoiListPanel(
                'submissionDoiListPanel',
                __('doi.manager.submissionDois'),
                array_merge(
                    $commonArgs,
                    [
                        'apiUrl' => $request->getDispatcher()->url($request, PKPApplication::ROUTE_API, $context->getPath(), 'submissions'),
                        'getParams' => [
                            'stageIds' => [WORKFLOW_STAGE_ID_PUBLISHED, WORKFLOW_STAGE_ID_PRODUCTION],
                        ],
                        'itemType' => 'submission'
                    ]
                )
            );
            $stateComponents[$submissionDoiListPanel->id] = $submissionDoiListPanel->getConfig();
        }

        return $stateComponents;
    }

    /**
     * Set Smarty template variables. Which tabs to display are set by the APP.
     */
    protected function getTemplateVariables(array $enabledDoiTypes): array
    {
        $templateVariables = parent::getTemplateVariables($enabledDoiTypes);
        $templateVariables['displaySubmissionsTab'] = !empty($enabledDoiTypes);

        return $templateVariables;
    }
}
