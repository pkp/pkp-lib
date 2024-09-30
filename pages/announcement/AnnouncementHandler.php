<?php

/**
 * @file pages/announcement/AnnouncementHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AnnouncementHandler
 *
 * @ingroup pages_announcement
 *
 * @brief Handle requests for public announcement functions.
 */

namespace PKP\pages\announcement;

use APP\core\Application;
use APP\core\Request;
use APP\handler\Handler;
use APP\template\TemplateManager;
use Carbon\Carbon;
use PKP\announcement\Announcement;
use PKP\core\PKPApplication;
use PKP\core\PKPRequest;

class AnnouncementHandler extends Handler
{
    //
    // Public handler methods.
    //
    /**
     * Show public announcements page.
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function index($args, $request)
    {
        if (!$this->isAnnouncementsEnabled($request)) {
            $request->getDispatcher()->handle404();
        }

        $this->setupTemplate($request);

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('announcementsIntroduction', $this->getAnnouncementsIntro($request));

        // TODO the announcements list should support pagination
        $announcements = Announcement::withActiveByDate();

        $contextIds = [];
        $request->getContext() ? $contextIds[] = $request->getContext()->getId() : $contextIds[] = PKPApplication::SITE_CONTEXT_ID;
        $announcements->withContextIds($contextIds);

        $templateMgr->assign('announcements', $announcements->get());
        $templateMgr->display('frontend/pages/announcements.tpl');
    }

    /**
     * View announcement details.
     *
     * @param array $args first parameter is the ID of the announcement to display
     * @param PKPRequest $request
     */
    public function view($args, $request)
    {
        if (!$this->isAnnouncementsEnabled($request)) {
            $request->getDispatcher()->handle404();
        }
        $this->validate();
        $this->setupTemplate($request);

        $announcementId = (int) array_shift($args);
        $announcement = Announcement::find($announcementId);
        if (
            $announcement
            && $announcement->assocType == Application::getContextAssocType()
            && $announcement->assocId == $request->getContext()?->getId()
            && (
                $announcement->dateExpire == null || Carbon::now()->lte($announcement->dateExpire)
            )
        ) {
            $templateMgr = TemplateManager::getManager($request);
            $templateMgr->assign('announcement', $announcement);
            $templateMgr->assign('announcementTitle', $announcement->getLocalizedData('fullTitle'));
            return $templateMgr->display('frontend/pages/announcement.tpl');
        }
        $request->redirect(null, 'announcement');
    }

    protected function isAnnouncementsEnabled(Request $request): bool
    {
        $contextOrSite = $request->getContext() ?? $request->getSite();
        return (bool) $contextOrSite->getData('enableAnnouncements');
    }

    protected function getAnnouncementsIntro(Request $request): ?string
    {
        $contextOrSite = $request->getContext() ?? $request->getSite();
        return $contextOrSite->getLocalizedData('announcementsIntroduction');
    }
}
