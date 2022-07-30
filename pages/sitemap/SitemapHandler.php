<?php

/**
 * @file pages/sitemap/SitemapHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SitemapHandler
 * @ingroup pages_sitemap
 *
 * @brief Produce a sitemap in XML format for submitting to search engines.
 */

namespace APP\pages\sitemap;

use PKP\plugins\HookRegistry;
use APP\facades\Repo;
use APP\submission\Submission;
use PKP\pages\sitemap\PKPSitemapHandler;

class SitemapHandler extends PKPSitemapHandler
{
    /**
     * @copydoc PKPSitemapHandler_createContextSitemap()
     */
    public function _createContextSitemap($request)
    {
        $doc = parent::_createContextSitemap($request);
        $root = $doc->documentElement;

        $server = $request->getServer();

        // Search
        $root->appendChild($this->_createUrlTree($doc, $request->url($server->getPath(), 'search')));

        // Preprints
        $submissionIds = Repo::submission()->getIds(
            Repo::submission()
                ->getCollector()
                ->filterByContextIds([$server->getId()])
                ->filterByStatus([Submission::STATUS_PUBLISHED])
        );
        foreach ($submissionIds as $submissionId) {
            $root->appendChild($this->_createUrlTree($doc, $request->url($server->getPath(), 'preprint', 'view', [$submissionId])));
        }

        $doc->appendChild($root);

        // Enable plugins to change the sitemap
        HookRegistry::call('SitemapHandler::createServerSitemap', [&$doc]);

        return $doc;
    }
}
