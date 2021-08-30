<?php

/**
 * @file pages/search/SearchHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SearchHandler
 * @ingroup pages_search
 *
 * @brief Handle site index requests.
 */

use APP\facades\Repo;
use APP\security\authorization\OpsServerMustPublishPolicy;
use APP\template\TemplateManager;

import('classes.search.PreprintSearch');
import('classes.handler.Handler');

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
     * @param $args array
     * @param $request PKPRequest
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
     * @param $request PKPRequest
     * @param $templateMgr TemplateManager
     * @param $searchFilters array
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
            $month = $request->getUserVar("date${fromTo}Month");
            $day = $request->getUserVar("date${fromTo}Day");
            $year = $request->getUserVar("date${fromTo}Year");
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
                "date${fromTo}Month" => $month,
                "date${fromTo}Day" => $day,
                "date${fromTo}Year" => $year,
                "date${fromTo}" => $date
            ]);
        }

        // Assign the year range.
        $yearRange = Repo::publication()->getDateBoundaries(['contextIds' => $serverId]);
        $yearStart = substr($yearRange[0], 0, 4);
        $yearEnd = substr($yearRange[1], 0, 4);
        $templateMgr->assign([
            'yearStart' => $yearStart,
            'yearEnd' => $yearEnd,
        ]);
    }

    /**
     * Show the search form
     *
     * @param $args array
     * @param $request PKPRequest
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

        // Result set ordering options.
        $orderByOptions = $preprintSearch->getResultSetOrderingOptions($request);
        $templateMgr->assign('searchResultOrderOptions', $orderByOptions);
        $orderDirOptions = $preprintSearch->getResultSetOrderingDirectionOptions();
        $templateMgr->assign('searchResultOrderDirOptions', $orderDirOptions);

        // Result set ordering selection.
        [$orderBy, $orderDir] = $preprintSearch->getResultSetOrdering($request);
        $templateMgr->assign('orderBy', $orderBy);
        $templateMgr->assign('orderDir', $orderDir);

        // Similar documents.
        $templateMgr->assign('simDocsEnabled', true);

        // Result set display.
        $this->_assignSearchFilters($request, $templateMgr, $searchFilters);
        $templateMgr->assign('results', $results);
        $templateMgr->assign('error', $error);
        $templateMgr->display('frontend/pages/search.tpl');
    }

    /**
     * Redirect to a search query that shows documents
     * similar to the one identified by an preprint id in the
     * request.
     *
     * @param $args array
     * @param $request Request
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
     * Show index of published submissions by author.
     *
     * @param $args array
     * @param $request PKPRequest
     */
    public function authors($args, $request)
    {
        $this->validate(null, $request);
        $this->setupTemplate($request);

        $server = $request->getServer();
        $user = $request->getUser();

        if (isset($args[0]) && $args[0] == 'view') {
            // View a specific author
            $authorName = $request->getUserVar('authorName');
            $givenName = $request->getUserVar('givenName');
            $familyName = $request->getUserVar('familyName');
            $affiliation = $request->getUserVar('affiliation');
            $country = $request->getUserVar('country');

            $submissions = Repo::author()
                ->getMany(
                    Repo::author()
                        ->getCollector()
                        ->filterByContextIds($server ? [$server->getId()] : [])
                        ->filterByName($givenName, $familyName)
                        ->filterByAffiliation($affiliation)
                        ->filterByCountry($country)
                )
                ->map(function($author) {
                    return $author->getData('publicationId');
                })
                ->unique()
                ->map(function($publicationId) {
                    return Repo::publication()->get($publicationId)->getData('submissionId');
                })
                ->unique()
                ->map(function($submissionId) {
                    return Repo::submission()->get($submissionId);
                });

            // Load information associated with each preprint.
            $servers = [];
            $sections = [];

            $sectionDao = DAORegistry::getDAO('SectionDAO'); /* @var $sectionDao SectionDAO */
            $serverDao = DAORegistry::getDAO('ServerDAO'); /* @var $serverDao ServerDAO */

            foreach ($submissions as $preprint) {
                $preprintId = $preprint->getId();
                $sectionId = $preprint->getSectionId();
                $serverId = $preprint->getData('contextId');

                if (!isset($servers[$serverId])) {
                    $servers[$serverId] = $serverDao->getById($serverId);
                }
                if (!isset($sections[$sectionId])) {
                    $sections[$sectionId] = $sectionDao->getById($sectionId, $serverId, true);
                }
            }

            if (empty($submissions)) {
                $request->redirect(null, $request->getRequestedPage());
            }

            $templateMgr = TemplateManager::getManager($request);
            $templateMgr->assign([
                'submissions' => $submissions,
                'sections' => $sections,
                'servers' => $servers,
                'givenName' => $givenName,
                'familyName' => $familyName,
                'affiliation' => $affiliation,
                'authorName' => $authorName
            ]);

            $isoCodes = new \Sokil\IsoCodes\IsoCodesFactory();
            $countries = $countries = $isoCodes->getCountries();
            $country = $countries->getByAlpha2($country);
            $templateMgr->assign('country', $country ? $country->getLocalName() : '');

            $templateMgr->display('frontend/pages/searchAuthorDetails.tpl');
        } else {
            // Show the author index
            $searchInitial = $request->getUserVar('searchInitial');
            $rangeInfo = $this->getRangeInfo($request, 'authors');

            $authors = Repo::author()->dao->getAuthorsAlphabetizedByServer(
                isset($server) ? $server->getId() : null,
                $searchInitial,
                $rangeInfo
            );

            $templateMgr = TemplateManager::getManager($request);
            $templateMgr->assign([
                'searchInitial' => $request->getUserVar('searchInitial'),
                'alphaList' => array_merge(['-'], explode(' ', __('common.alphaList'))),
                'authors' => $authors,
            ]);
            $templateMgr->display('frontend/pages/searchAuthorIndex.tpl');
        }
    }

    /**
     * Setup common template variables.
     *
     * @param $request PKPRequest
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
