<?php

/**
 * @file classes/notification/managerDelegate/QueryNotificationManager.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class QueryNotificationManager
 * @ingroup managerDelegate
 *
 * @brief Query notification types manager delegate.
 */

namespace PKP\notification\managerDelegate;

use APP\core\Application;
use APP\facades\Repo;
use PKP\core\PKPString;
use PKP\db\DAORegistry;
use PKP\note\NoteDAO;

use PKP\notification\NotificationManagerDelegate;
use PKP\notification\PKPNotification;

class QueryNotificationManager extends NotificationManagerDelegate
{
    /**
     * @copydoc NotificationManagerDelegate::getNotifictionTitle()
     */
    public function getNotificationTitle($notification)
    {
        switch ($notification->getType()) {
            case PKPNotification::NOTIFICATION_TYPE_NEW_QUERY:
                assert(false);
                break;
            case PKPNotification::NOTIFICATION_TYPE_QUERY_ACTIVITY:
                assert(false);
                break;
            default: assert(false);
        }
    }

    /**
     * @copydoc NotificationManagerDelegate::getNotificationMessage()
     */
    public function getNotificationMessage($request, $notification)
    {
        assert($notification->getAssocType() == ASSOC_TYPE_QUERY);
        $queryDao = DAORegistry::getDAO('QueryDAO'); /** @var QueryDAO $queryDao */
        $query = $queryDao->getById($notification->getAssocId());

        $headNote = $query->getHeadNote();
        assert(isset($headNote));

        switch ($notification->getType()) {
            case PKPNotification::NOTIFICATION_TYPE_NEW_QUERY:
                $user = $headNote->getUser();
                return __('submission.query.new', [
                    'creatorName' => $user->getFullName(),
                    'noteContents' => substr(PKPString::html2text($headNote->getContents()), 0, 200),
                    'noteTitle' => substr($headNote->getTitle(), 0, 200),
                ]);
            case PKPNotification::NOTIFICATION_TYPE_QUERY_ACTIVITY:
                $notes = $query->getReplies(null, NoteDAO::NOTE_ORDER_ID, \PKP\db\DAO::SORT_DIRECTION_DESC);
                $latestNote = $notes->next();
                $user = $latestNote->getUser();
                return __('submission.query.activity', [
                    'responderName' => $user->getFullName(),
                    'noteContents' => substr(PKPString::html2text($latestNote->getContents()), 0, 200),
                    'noteTitle' => substr($headNote->getTitle(), 0, 200),
                ]);
            default: assert(false);
        }
    }

    /**
     * Get the submission for a query.
     *
     * @param Query $query
     *
     * @return Submission
     */
    protected function getQuerySubmission($query)
    {
        switch ($query->getAssocType()) {
            case ASSOC_TYPE_SUBMISSION:
                return Repo::submission()->get($query->getAssocId());
            case ASSOC_TYPE_REPRESENTATION:
                $representationDao = Application::getRepresentationDAO();
                $representation = $representationDao->getById($query->getAssocId());
                $publication = Repo::publication()->get($representation->getData('publicationId'));
                return Repo::submission()->get($publication->getData('submissionId'));
        }
        assert(false);
    }

    /**
     * @copydoc NotificationManagerDelegate::getNotificationUrl()
     */
    public function getNotificationUrl($request, $notification)
    {
        assert($notification->getAssocType() == ASSOC_TYPE_QUERY);
        $queryDao = DAORegistry::getDAO('QueryDAO'); /** @var QueryDAO $queryDao */
        $query = $queryDao->getById($notification->getAssocId());
        assert($query instanceof \PKP\query\Query);
        $submission = $this->getQuerySubmission($query);

        return Repo::submission()->getWorkflowUrlByUserRoles($submission, $notification->getUserId());
    }

    /**
     * @copydoc NotificationManagerDelegate::getNotificationContents()
     */
    public function getNotificationContents($request, $notification)
    {
        assert($notification->getAssocType() == ASSOC_TYPE_QUERY);
        $queryDao = DAORegistry::getDAO('QueryDAO'); /** @var QueryDAO $queryDao */
        $query = $queryDao->getById($notification->getAssocId());
        assert($query instanceof \PKP\query\Query);

        $submission = $this->getQuerySubmission($query);
        assert($submission instanceof \APP\submission\Submission);

        switch ($notification->getType()) {
            case PKPNotification::NOTIFICATION_TYPE_NEW_QUERY:
                return __(
                    'submission.query.new.contents',
                    [
                        'queryTitle' => $query->getHeadNote()->getTitle(),
                        'submissionTitle' => $submission->getLocalizedTitle(),
                    ]
                );
            case PKPNotification::NOTIFICATION_TYPE_QUERY_ACTIVITY:
                return __(
                    'submission.query.activity.contents',
                    [
                        'queryTitle' => $query->getHeadNote()->getTitle(),
                        'submissionTitle' => $submission->getLocalizedTitle(),
                    ]
                );
            default: assert(false);
        }
    }

    /**
     * @copydoc NotificationManagerDelegate::getStyleClass()
     */
    public function getStyleClass($notification)
    {
        return NOTIFICATION_STYLE_CLASS_WARNING;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\notification\managerDelegate\QueryNotificationManager', '\QueryNotificationManager');
}
