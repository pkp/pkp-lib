<?php

/**
 * @file lib/pkp/pages/search/SearchHandler.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2003-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SearchHandler
 *
 * @brief Handle search requests.
 */

namespace PKP\pages\search;

use APP\facades\Repo;
use APP\handler\Handler;
use APP\search\SubmissionSearchResult;
use APP\template\TemplateManager;
use PKP\core\PKPRequest;

class SearchHandler extends Handler
{
    /**
     * Show the search form
     */
    public function index($args, $request)
    {
        $this->validate(null, $request);
        $this->search($args, $request);
    }

    /**
     * Show the search form
     */
    public function search(array $args, PKPRequest $request): void
    {
        $this->validate(null, $request);

        // Work around https://github.com/php/php-src/issues/20469
        $junk = \APP\publication\Publication::STATUS_PUBLISHED;

        $rangeInfo = $this->getRangeInfo($request, 'search');
        $builder = (new SubmissionSearchResult())->builderFromRequest($request, $rangeInfo);
        $results = $builder->paginate($rangeInfo->getCount(), 'submissions', $rangeInfo->getPage());

        $this->setupTemplate($request);

        $templateMgr = TemplateManager::getManager($request);

        // Assign the year range.
        $collector = Repo::publication()->getCollector();
        $context = $request->getContext();
        $collector->filterByContextIds($context ? [$context->getId()] : null);
        $yearRange = Repo::publication()->getDateBoundaries($collector);
        $yearStart = substr($yearRange->min_date_published, 0, 4);
        $yearEnd = substr($yearRange->max_date_published, 0, 4);

        $this->_assignDateFromTo($request, $templateMgr);

        $templateMgr->assign([
            'query' => $builder->query,
            'results' => $results,
            'searchContext' => $context?->getId(),
            'yearStart' => $yearStart,
            'yearEnd' => $yearEnd,
            'orderBy' => $request->getUserVar('orderBy'),
            'orderDir' => $request->getUserVar('orderDir'),
        ]);

        if (!$context) {
            $contextService = app()->get('context');
            $templateMgr->assign('searchableContexts', $contextService->getManySummary(['isEnabled' => true]));
        }

        $templateMgr->display('frontend/pages/search.tpl');
    }

    /**
     * Assign dateFrom* and dateTo* variables to template
     *
     */
    public function _assignDateFromTo(PKPRequest $request, TemplateManager &$templateMgr)
    {
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
        $context = $request->getContext();
        if (!$context || !$context->getData('restrictSiteAccess')) {
            $templateMgr->setCacheability(TemplateManager::CACHEABILITY_PUBLIC);
        }
    }
}
