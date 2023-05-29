<?php

/**
 * @file pages/search/SearchHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SearchHandler
 *
 * @ingroup pages_search
 *
 * @brief Handle site index requests.
 */

namespace APP\pages\search;

use APP\core\Request;
use APP\facades\Repo;
use APP\handler\Handler;
use APP\search\PreprintSearch;
use APP\security\authorization\OpsServerMustPublishPolicy;
use APP\template\TemplateManager;

class SearchHandler extends Handler
{
    /**
     * @copydoc PKPHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        if ($request->getContext()) {
            $this->addPolicy(new OpsServerMustPublishPolicy($request));
        }

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Show the search form
     *
     * @param array $args
     * @param Request $request
     */
    public function index($args, $request)
    {
        $this->validate(null, $request);
        $this->search($args, $request);
    }

    /**
     * Private function to transmit current filter values
     * to the template.
     *
     * @param Request $request
     * @param TemplateManager $templateMgr
     * @param array $searchFilters
     */
    public function _assignSearchFilters($request, &$templateMgr, $searchFilters)
    {
        // Get the server id (if any).
        $server = & $searchFilters['searchServer'];
        $serverId = ($server ? $server->getId() : null);
        $searchFilters['searchServer'] = $serverId;

        // Assign all filters except for dates which need special treatment.
        $templateSearchFilters = [];
        foreach ($searchFilters as $filterName => $filterValue) {
            if (in_array($filterName, ['fromDate', 'toDate'])) {
                continue;
            }
            $templateSearchFilters[$filterName] = $filterValue;
        }

        // Assign the filters to the template.
        $templateMgr->assign($templateSearchFilters);

        // Special case: publication date filters.
        foreach (['From', 'To'] as $fromTo) {
            $month = $request->getUserVar("date{$fromTo}Month");
            $day = $request->getUserVar("date{$fromTo}Day");
            $year = $request->getUserVar("date{$fromTo}Year");
            if (empty($year)) {
                $date = null;
                $hasEmptyFilters = true;
            } else {
                $defaultMonth = ($fromTo == 'From' ? 1 : 12);
                $defaultDay = ($fromTo == 'From' ? 1 : 31);
                $date = date(
                    'Y-m-d H:i:s',
                    mktime(
                        0,
                        0,
                        0,
                        empty($month) ? $defaultMonth : $month,
                        empty($day) ? $defaultDay : $day,
                        $year
                    )
                );
                $hasActiveFilters = true;
            }
            $templateMgr->assign([
                "date{$fromTo}Month" => $month,
                "date{$fromTo}Day" => $day,
                "date{$fromTo}Year" => $year,
                "date{$fromTo}" => $date
            ]);
        }

        // Assign the year range.
        $collector = Repo::publication()->getCollector();
        if ($serverId) {
            $collector->filterByContextIds([(int) $serverId]);
        }
        $yearRange = Repo::publication()->getDateBoundaries($collector);
        $yearStart = substr($yearRange->min_date_published, 0, 4);
        $yearEnd = substr($yearRange->max_date_published, 0, 4);
        $templateMgr->assign([
            'yearStart' => $yearStart,
            'yearEnd' => $yearEnd,
        ]);
    }

    /**
     * Show the search form
     *
     * @param array $args
     * @param Request $request
     */
    public function search($args, $request)
    {
        $this->validate(null, $request);

        // Get and transform active filters.
        $preprintSearch = new PreprintSearch();
        $searchFilters = $preprintSearch->getSearchFilters($request);
        $keywords = $preprintSearch->getKeywordsFromSearchFilters($searchFilters);

        // Get the range info.
        $rangeInfo = $this->getRangeInfo($request, 'search');

        // Retrieve results.
        $error = '';
        $results = $preprintSearch->retrieveResults(
            $request,
            $searchFilters['searchServer'],
            $keywords,
            $error,
            $searchFilters['fromDate'],
            $searchFilters['toDate'],
            $rangeInfo
        );

        // Prepare and display the search template.
        $this->setupTemplate($request);
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->setCacheability(TemplateManager::CACHEABILITY_NO_STORE);

        [$orderBy, $orderDir] = $preprintSearch->getResultSetOrdering($request);
        $this->_assignSearchFilters($request, $templateMgr, $searchFilters);
        $templateMgr->assign([
            'searchResultOrderOptions' => $preprintSearch->getResultSetOrderingOptions($request),
            'searchResultOrderDirOptions' => $preprintSearch->getResultSetOrderingDirectionOptions(),
            'orderBy' => $orderBy,
            'orderDir' => $orderDir,
            'simDocsEnabled' => true,
            'results' => $results,
            'error' => $error,
            'authorUserGroups' => Repo::userGroup()->getCollector()
                ->filterByRoleIds([\PKP\security\Role::ROLE_ID_AUTHOR])
                ->filterByContextIds($searchFilters['searchServer'] ? [$searchFilters['searchServer']->getId()] : null)
                ->getMany()->remember(),
        ]);
        $templateMgr->display('frontend/pages/search.tpl');
    }

    /**
     * Redirect to a search query that shows documents
     * similar to the one identified by an preprint id in the
     * request.
     *
     * @param array $args
     * @param \APP\core\Request $request
     */
    public function similarDocuments($args, &$request)
    {
        $this->validate(null, $request);

        // Retrieve the (mandatory) ID of the preprint that
        // we want similar documents for.
        $preprintId = $request->getUserVar('preprintId');
        if (!is_numeric($preprintId)) {
            $request->redirect(null, 'search');
        }

        // Check whether a search plugin provides terms for a similarity search.
        $preprintSearch = new PreprintSearch();
        $searchTerms = $preprintSearch->getSimilarityTerms($preprintId);

        // Redirect to a search query with the identified search terms (if any).
        if (empty($searchTerms)) {
            $searchParams = null;
        } else {
            $searchParams = ['query' => implode(' ', $searchTerms)];
        }
        $request->redirect(null, 'search', 'search', null, $searchParams);
    }

    /**
     * Setup common template variables.
     *
     * @param \APP\core\Request $request
     */
    public function setupTemplate($request)
    {
        parent::setupTemplate($request);
        $templateMgr = TemplateManager::getManager($request);
        $server = $request->getServer();
        if (!$server || !$server->getData('restrictSiteAccess')) {
            $templateMgr->setCacheability(TemplateManager::CACHEABILITY_PUBLIC);
        }
    }
}
