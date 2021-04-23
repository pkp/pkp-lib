<?php

/**
 * @file pages/index/IndexHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class IndexHandler
 * @ingroup pages_index
 *
 * @brief Handle site index requests.
 */

use APP\handler\Handler;

class PKPIndexHandler extends Handler
{
    /**
     * Set up templates with announcement data.
     *
     * @protected
     *
     * @param $context Context
     * @param $templateMgr PKPTemplateManager
     */
    protected function _setupAnnouncements($context, $templateMgr)
    {
        $enableAnnouncements = $context->getData('enableAnnouncements');
        $numAnnouncementsHomepage = $context->getData('numAnnouncementsHomepage');
        if ($enableAnnouncements && $numAnnouncementsHomepage) {
            $announcementDao = DAORegistry::getDAO('AnnouncementDAO'); /** @var AnnouncementDAO $announcementDao */
            $announcements = $announcementDao->getNumAnnouncementsNotExpiredByAssocId($context->getAssocType(), $context->getId(), $numAnnouncementsHomepage);
            $templateMgr->assign([
                'announcements' => $announcements->toArray(),
                'numAnnouncementsHomepage' => $numAnnouncementsHomepage,
            ]);
        }
    }
}
