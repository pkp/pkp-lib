<?php

/**
 * @file pages/preprints/PreprintsHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PreprintsHandler
 *
 * @ingroup pages_preprints
 *
 * @brief Handle requests for preprints archive functions.
 */

namespace APP\pages\preprints;

use APP\core\Request;
use APP\facades\Repo;
use APP\handler\Handler;
use APP\security\authorization\OpsServerMustPublishPolicy;
use APP\submission\Collector;
use APP\submission\Submission;
use APP\template\TemplateManager;
use PKP\config\Config;
use PKP\plugins\PluginRegistry;
use PKP\security\authorization\ContextRequiredPolicy;

class PreprintsHandler extends Handler
{
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
     * Display the preprint archive listings
     *
     * @param array $args
     * @param Request $request
     *
     * @return null|\PKP\core\JSONMessage
     */
    public function index($args, $request)
    {
        $this->setupTemplate($request);
        $page = isset($args[0]) ? (int) $args[0] : 1;
        $templateMgr = TemplateManager::getManager($request);
        $context = $request->getContext();

        // OPS: sections
        $sections = Repo::section()
            ->getCollector()
            ->filterByContextIds([$context->getId()])
            ->getMany();

        // OPS: categories
        $categories = Repo::category()->getCollector()
            ->filterByContextIds([$context->getId()])
            ->getMany();

        $count = $context->getData('itemsPerPage') ? $context->getData('itemsPerPage') : (int) Config::getVar('interface', 'items_per_page');
        $offset = $page > 1 ? ($page - 1) * $count : 0;

        $collector = Repo::submission()->getCollector()
            ->filterByContextIds([$context->getId()])
            ->filterByStatus([Submission::STATUS_PUBLISHED])
            ->orderBy(Collector::ORDERBY_DATE_PUBLISHED);
        $total = $collector->getCount();
        $publishedSubmissions = $collector->limit($count)->getMany();

        $showingStart = $offset + 1;
        $showingEnd = min($offset + $count, $offset + count($publishedSubmissions));
        $nextPage = $total > $showingEnd ? $page + 1 : null;
        $prevPage = $showingStart > 1 ? $page - 1 : null;

        $templateMgr->assign([
            'sections' => $sections,
            'categories' => iterator_to_array($categories),
            'publishedSubmissions' => $publishedSubmissions,
            'pubIdPlugins' => PluginRegistry::loadCategory('pubIds', true),
            'authorUserGroups' => Repo::userGroup()->getCollector()->filterByRoleIds([\PKP\security\Role::ROLE_ID_AUTHOR])->filterByContextIds([$context->getId()])->getMany()->remember(),
            'showingStart' => $showingStart,
            'showingEnd' => $showingEnd,
            'total' => $total,
            'nextPage' => $nextPage,
            'prevPage' => $prevPage,
        ]);

        $templateMgr->display('frontend/pages/preprints.tpl');
    }
}
