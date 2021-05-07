<?php

/**
 * @file pages/announcement/AnnouncementHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPAnnouncementHandler
 * @ingroup pages_announcement
 *
 * @brief Handle requests for public announcement functions.
 */

use APP\handler\Handler;

use APP\template\TemplateManager;
use PKP\db\DBResultRange;
use PKP\security\authorization\ContextRequiredPolicy;

class AnnouncementHandler extends Handler
{
    //
    // Implement methods from Handler.
    //
    /**
     * @copydoc Handler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        $this->addPolicy(new ContextRequiredPolicy($request));

        return parent::authorize($request, $args, $roleAssignments);
    }


    //
    // Public handler methods.
    //
    /**
     * Show public announcements page.
     *
     * @param $args array
     * @param $request PKPRequest
     *
     * @return string
     */
    public function index($args, $request)
    {
        if (!$request->getContext()->getData('enableAnnouncements')) {
            $request->getDispatcher()->handle404();
        }

        $this->setupTemplate($request);

        $context = $request->getContext();
        $announcementsIntro = $context->getLocalizedData('announcementsIntroduction');

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('announcementsIntroduction', $announcementsIntro);


        $announcementDao = DAORegistry::getDAO('AnnouncementDAO'); /** @var AnnouncementDAO $announcementDao */
        // TODO the announcements list should support pagination
        $rangeInfo = new DBResultRange(50, -1);
        $announcements = $announcementDao->getAnnouncementsNotExpiredByAssocId($context->getAssocType(), $context->getId(), $rangeInfo);
        $templateMgr->assign('announcements', $announcements->toArray());

        $templateMgr->display('frontend/pages/announcements.tpl');
    }

    /**
     * View announcement details.
     *
     * @param $args array first parameter is the ID of the announcement to display
     * @param $request PKPRequest
     */
    public function view($args, $request)
    {
        if (!$request->getContext()->getData('enableAnnouncements')) {
            $request->getDispatcher()->handle404();
        }
        $this->validate();
        $this->setupTemplate($request);

        $context = $request->getContext();
        $announcementId = (int) array_shift($args);
        $announcementDao = DAORegistry::getDAO('AnnouncementDAO'); /** @var AnnouncementDAO $announcementDao */
        $announcement = $announcementDao->getById($announcementId);
        if ($announcement && $announcement->getAssocType() == Application::getContextAssocType() && $announcement->getAssocId() == $context->getId() && ($announcement->getDateExpire() == null || strtotime($announcement->getDateExpire()) > time())) {
            $templateMgr = TemplateManager::getManager($request);
            $templateMgr->assign('announcement', $announcement);
            $templateMgr->assign('announcementTitle', $announcement->getLocalizedTitleFull());
            return $templateMgr->display('frontend/pages/announcement.tpl');
        }
        $request->redirect(null, 'announcement');
    }
}
