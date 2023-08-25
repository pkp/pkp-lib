<?php

/**
 * @file pages/index/IndexHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPIndexHandler
 *
 * @ingroup pages_index
 *
 * @brief Handle site index requests.
 */

namespace PKP\pages\index;

use APP\facades\Repo;
use APP\handler\Handler;
use Illuminate\Support\LazyCollection;
use PKP\config\Config;
use PKP\announcement\Collector;
use PKP\context\Context;
use PKP\site\Site;
use PKP\template\PKPTemplateManager;

class PKPIndexHandler extends Handler
{
    /**
     * Set up templates with announcement data.
     *
     * @protected
     *
     * @param Context $context
     * @param PKPTemplateManager $templateMgr
     */
    protected function _setupAnnouncements(Context|Site $contextOrSite, $templateMgr)
    {
        $enableAnnouncements = $contextOrSite->getData('enableAnnouncements');
        $numAnnouncementsHomepage = $contextOrSite->getData('numAnnouncementsHomepage');
        if ($enableAnnouncements && $numAnnouncementsHomepage) {
            $collector = Repo::announcement()
                ->getCollector()
                ->filterByActive()
                ->limit((int) $numAnnouncementsHomepage);

            if (is_a($contextOrSite, Context::class)) {
                $collector->filterByContextIds([$contextOrSite->getId()]);
            } else  {
                $collector->withSiteAnnouncements(Collector::SITE_ONLY);
            }

            $announcements = $collector->getMany();

            $templateMgr->assign([
                'announcements' => $announcements->toArray(),
                'numAnnouncementsHomepage' => $numAnnouncementsHomepage,
            ]);
        }
    }

    /**
     * Get the Highlights for this context
     */
    protected function getHighlights(?Context $context = null): LazyCollection
    {
        if (!Config::getVar('features', 'highlights')) {
            return LazyCollection::make();
        }

        $collector = Repo::highlight()->getCollector();

        if ($context) {
            $collector->filterByContextIds([$context->getId()]);
        } else {
            $collector->withSiteHighlights($collector::SITE_ONLY);
        }

        return $collector->getMany();
    }
}
