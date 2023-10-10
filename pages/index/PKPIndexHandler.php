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
use PKP\context\Context;
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
    protected function _setupAnnouncements($context, $templateMgr)
    {
        $enableAnnouncements = $context->getData('enableAnnouncements');
        $numAnnouncementsHomepage = $context->getData('numAnnouncementsHomepage');
        if ($enableAnnouncements && $numAnnouncementsHomepage) {
            $announcements = Repo::announcement()
                ->getCollector()
                ->filterByContextIds([$context->getId()])
                ->filterByActive()
                ->limit((int) $numAnnouncementsHomepage)
                ->getMany();

            $templateMgr->assign([
                'announcements' => $announcements->toArray(),
                'numAnnouncementsHomepage' => $numAnnouncementsHomepage,
            ]);
        }
    }
}
