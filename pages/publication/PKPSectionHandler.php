<?php

/**
 * @file pages/publication/PKPSectionHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPSectionHandler
 *
 * @ingroup pages_publication
 *
 * @brief Handle requests for the public-facing section or series listing.
 */

namespace PKP\pages\publication;

use APP\facades\Repo;
use APP\handler\Handler;
use APP\submission\Collector;
use APP\submission\Submission;
use APP\template\TemplateManager;
use PKP\config\Config;
use PKP\context\Context;
use PKP\core\PKPRequest;
use PKP\db\DAO;
use PKP\file\ContextFileManager;
use PKP\security\authorization\ContextRequiredPolicy;
use PKP\security\Role;
use PKP\userGroup\UserGroup;
use Illuminate\Support\LazyCollection;


abstract class PKPSectionHandler extends Handler
{

    /**
     * @copydoc PKPHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        $this->addPolicy(new ContextRequiredPolicy($request));
        return parent::authorize($request, $args, $roleAssignments);
    }

   /**
     * View a section
     *
     * @param $args array [
     *		@option string Section ID
     *		@option string page number
     * ]
     *
     * @param $request PKPRequest
     *
     * @return null|JSONMessage
     */
    public function section($args, $request)
    {
        $sectionUrlPath = $args[0] ?? null;
        $page = isset($args[1]) && ctype_digit((string) $args[1]) ? (int) $args[1] : 1;
        $context = $request->getContext();
        $contextId = $context ? $context->getId() : Application::CONTEXT_ID_NONE;

        // The page $arg can only contain an integer that's not 1. The first page
        // URL does not include page $arg
        if (isset($args[1]) && (!ctype_digit((string) $args[1]) || $args[1] == 1)) {
            $request->getDispatcher()->handle404();
            exit;
        }

        if (!$sectionUrlPath || !$contextId) {
            $request->getDispatcher()->handle404();
            exit;
        }

        $section = Repo::section()->getCollector()
            ->filterByUrlPaths([$sectionUrlPath])
            ->filterByContextIds([$contextId])
            ->getMany()
            ->first();

        if (!$section || $section->getNotBrowsable()) {
            $request->getDispatcher()->handle404();
            exit;
        }

        $limit = $context->getData('itemsPerPage') ? $context->getData('itemsPerPage') : Config::getVar('interface', 'items_per_page');
        $offset = $page > 1 ? ($page - 1) * $limit : 0;

        $collector = $this->getCollector($section->getId(), $contextId);

        $total = $collector->getCount();
        $submissions = $collector->limit($limit)->offset($offset)->getMany();

        if ($page > 1 && !$submissions->count()) {
            $request->getDispatcher()->handle404();
            exit;
        }

        $showingStart = $collector->offset + 1;
        $showingEnd = min($collector->offset + $collector->count, $collector->offset + $submissions->count());
        $nextPage = $total > $showingEnd ? $page + 1 : null;
        $prevPage = $showingStart > 1 ? $page - 1 : null;

        $authorUserGroups = UserGroup::withRoleIds([\PKP\security\Role::ROLE_ID_AUTHOR])
                                ->withContextIds([$contextId])
                                ->get();

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'authorUserGroups' => $authorUserGroups,
            'section' => $section,
            'sectionUrlPath' => $sectionUrlPath,
            'submissions' => $submissions,
            'showingStart' => $showingStart,
            'showingEnd' => $showingEnd,
            'total' => $total,
            'nextPage' => $nextPage,
            'prevPage' => $prevPage,
        ]);

        $collector = $this->assignTemplateVars($submissions, $context);
    }

    abstract protected function getCollector(int $sectionId, int $contextId): Collector;

    abstract protected function assignTemplateVars(LazyCollection $submissions, Context $context);

}
