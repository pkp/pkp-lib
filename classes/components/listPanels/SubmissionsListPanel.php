<?php
/**
 * @file components/listPanels/SubmissionsListPanel.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionsListPanel
 * @ingroup classes_components_listPanels
 *
 * @brief Instantiates and manages a UI component to list submissions.
 */

namespace APP\components\listPanels;

use APP\core\Application;
use APP\facades\Repo;
use PKP\components\listPanels\PKPSubmissionsListPanel;

class SubmissionsListPanel extends PKPSubmissionsListPanel
{
    /** @var bool Whether to show inactive section filters */
    public $includeActiveSectionFiltersOnly = false;

    /**
     * @copydoc PKPSubmissionsListPanel::getConfig()
     */
    public function getConfig()
    {
        $config = parent::getConfig();

        $request = \Application::get()->getRequest();
        if ($request->getContext()) {
            $config['filters'][] = [
                'heading' => __('section.sections'),
                'filters' => self::getSectionFilters($this->includeActiveSectionFiltersOnly),
            ];
        }

        return $config;
    }

    /**
     * Get an array of workflow stages supported by the current app
     *
     * @return array
     */
    public function getWorkflowStages()
    {
        return [
            [
                'param' => 'stageIds',
                'value' => WORKFLOW_STAGE_ID_PRODUCTION,
                'title' => __('manager.publication.productionStage'),
            ],
        ];
    }

    /**
     * Compile the sections for passing as filters
     *
     * @return array
     */
    public static function getSectionFilters($activeOnly = false)
    {
        $request = Application::get()->getRequest();
        $context = $request->getContext();

        if (!$context) {
            return [];
        }

        $sections = Repo::section()->getSectionList($context->getId(), $activeOnly);

        return array_map(function ($section) {
            return [
                'param' => 'sectionIds',
                'value' => (int) $section['id'],
                'title' => $section['title'],
            ];
        }, $sections);
    }
}
