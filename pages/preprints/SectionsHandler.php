<?php

/**
 * @file pages/sections/SeriesHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SectionsHandler
 * @ingroup pages_preprints
 *
 * @brief Handle requests for sections functions.
 *
 */

namespace APP\pages\preprints;

use APP\core\Application;
use APP\facades\Repo;
use APP\handler\Handler;
use APP\security\authorization\OpsServerMustPublishPolicy;
use APP\submission\Submission;
use APP\template\TemplateManager;
use PKP\db\DAORegistry;
use PKP\security\authorization\ContextRequiredPolicy;

class SectionsHandler extends Handler
{
    /** @var array sections associated with the request */
    public $sections;

    /**
     * @copydoc PKPHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        $this->addPolicy(new ContextRequiredPolicy($request));
        $this->addPolicy(new OpsServerMustPublishPolicy($request));
        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * View a section
     *
     * @param array $args [
     *		@option string Section ID
     *		@option string page number
     * ]
     *
     * @param \PKP\core\PKPRequest $request
     *
     * @return null|\PKP\core\JSONMessage
     */
    public function section($args, $request)
    {
        $sectionPath = $args[0] ?? null;
        $page = isset($args[1]) && ctype_digit((string) $args[1]) ? (int) $args[1] : 1;
        $context = $request->getContext();
        $contextId = $context ? $context->getId() : Application::CONTEXT_ID_NONE;

        // The page $arg can only contain an integer that's not 1. The first page
        // URL does not include page $arg
        if (isset($args[1]) && (!ctype_digit((string) $args[1]) || $args[1] == 1)) {
            $request->getDispatcher()->handle404();
            exit;
        }

        if (!$sectionPath || !$contextId) {
            $request->getDispatcher()->handle404();
            exit;
        }

        $sectionDao = DAORegistry::getDAO('SectionDAO');
        $sections = $sectionDao->getByContextId($contextId);

        $sectionExists = false;
        while ($section = $sections->next()) {
            if ($section->getData('path') === $sectionPath) {
                $sectionExists = true;
                break;
            }
        }

        if (!$sectionExists) {
            $request->getDispatcher()->handle404();
            exit;
        }

        $collector = Repo::submission()->getCollector()
            ->filterByContextIds([$contextId])
            ->filterByStatus([Submission::STATUS_PUBLISHED])
            ->filterBySectionIds([(int) $section->getId()])
            ->orderBy($collector::ORDERBY_DATE_PUBLISHED);
        $total = $collector->getCount();
        $submissions = $collector
                ->limit($context->getData('itemsPerPage'))
                ->offset($page ? ($page - 1) * $context->getData('itemsPerPage') : 0)
                ->getMany();

        if ($page > 1 && !$submissions->count()) {
            $request->getDispatcher()->handle404();
            exit;
        }

        $submissions = [];
        foreach ($submissions as $submission) {
            $submissions[] = $submission;
        }

        $showingStart = $collector->offset + 1;
        $showingEnd = min($collector->offset + $collector->count, $collector->offset + count($submissions));
        $nextPage = $total > $showingEnd ? $page + 1 : null;
        $prevPage = $showingStart > 1 ? $page - 1 : null;

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'section' => $section,
            'sectionPath' => $sectionPath,
            'preprints' => $submissions,
            'showingStart' => $showingStart,
            'showingEnd' => $showingEnd,
            'total' => $total,
            'nextPage' => $nextPage,
            'prevPage' => $prevPage,
        ]);

        $templateMgr->display('frontend/pages/sections.tpl');
    }
}
