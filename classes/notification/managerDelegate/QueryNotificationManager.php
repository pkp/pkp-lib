<?php

/**
 * @file classes/notification/managerDelegate/QueryNotificationManager.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class QueryNotificationManager
 *
 * @ingroup managerDelegate
 *
 * @brief Query notification types manager delegate.
 */

namespace PKP\notification\managerDelegate;

use APP\core\Application;
use APP\facades\Repo;
use APP\submission\Submission;
use PKP\core\PKPRequest;
use PKP\core\PKPString;
use PKP\db\DAORegistry;
use PKP\note\NoteDAO;
use PKP\notification\Notification;
use PKP\notification\NotificationManagerDelegate;
use PKP\query\Query;
use PKP\query\QueryDAO;

class QueryNotificationManager extends NotificationManagerDelegate
{
    /**
     * @copydoc NotificationManagerDelegate::getNotificationMessage()
     */
    public function getNotificationMessage(PKPRequest $request, Notification $notification): string|array|null
    {
        if ($notification->assocType != Application::ASSOC_TYPE_QUERY) {
            throw new \Exception('Unexpected assoc type!');
        }
        $queryDao = DAORegistry::getDAO('QueryDAO'); /** @var QueryDAO $queryDao */
        $query = $queryDao->getById($notification->assocId);

        $headNote = $query->getHeadNote();
        if (!$headNote) {
            throw new \Exception('Unable to retrieve head note for query!');
        }

        switch ($notification->type) {
            case Notification::NOTIFICATION_TYPE_NEW_QUERY:
                $user = $headNote->getUser();
                return __('submission.query.new', [
                    'creatorName' => $user->getFullName(),
                    'noteContents' => substr(PKPString::html2text($headNote->getContents()), 0, 200),
                    'noteTitle' => substr($headNote->getTitle(), 0, 200),
                ]);
            case Notification::NOTIFICATION_TYPE_QUERY_ACTIVITY:
                $notes = $query->getReplies(null, NoteDAO::NOTE_ORDER_ID, \PKP\db\DAO::SORT_DIRECTION_DESC);
                $latestNote = $notes->first();
                $user = $latestNote->getUser();
                return __('submission.query.activity', [
                    'responderName' => $user->getFullName(),
                    'noteContents' => substr(PKPString::html2text($latestNote->getContents()), 0, 200),
                    'noteTitle' => substr($headNote->getTitle(), 0, 200),
                ]);
        }
        throw new \Exception('Unexpected notification type!');
    }

    /**
     * Get the submission for a query.
     */
    protected function getQuerySubmission(Query $query): Submission
    {
        switch ($query->getAssocType()) {
            case Application::ASSOC_TYPE_SUBMISSION:
                return Repo::submission()->get($query->getAssocId());
            case Application::ASSOC_TYPE_REPRESENTATION:
                $representationDao = Application::getRepresentationDAO();
                $representation = $representationDao->getById($query->getAssocId());
                $publication = Repo::publication()->get($representation->getData('publicationId'));
                return Repo::submission()->get($publication->getData('submissionId'));
        }
        throw new \Exception('Unexpected query assoc type!');
    }

    /**
     * @copydoc NotificationManagerDelegate::getNotificationUrl()
     */
    public function getNotificationUrl(PKPRequest $request, Notification $notification): ?string
    {
        if ($notification->assocType != Application::ASSOC_TYPE_QUERY) {
            throw new \Exception('Unexpected query assoc type!');
        }

        $queryDao = DAORegistry::getDAO('QueryDAO'); /** @var QueryDAO $queryDao */
        $query = $queryDao->getById($notification->assocId);
        if (!$query) {
            return null;
        }

        $submission = $this->getQuerySubmission($query);
        return Repo::submission()->getWorkflowUrlByUserRoles($submission, $notification->userId);
    }

    /**
     * @copydoc NotificationManagerDelegate::getNotificationContents()
     */
    public function getNotificationContents(PKPRequest $request, Notification $notification): mixed
    {
        if($notification->assocType != Application::ASSOC_TYPE_QUERY) {
            throw new \Exception('Unexpected assoc type!');
        }
        $queryDao = DAORegistry::getDAO('QueryDAO'); /** @var QueryDAO $queryDao */
        $query = $queryDao->getById($notification->assocId);
        $submission = $this->getQuerySubmission($query);

        switch ($notification->type) {
            case Notification::NOTIFICATION_TYPE_NEW_QUERY:
                return __(
                    'submission.query.new.contents',
                    [
                        'queryTitle' => $query->getHeadNote()->getTitle(),
                        'submissionTitle' => $submission->getCurrentPublication()->getLocalizedTitle(null, 'html'),
                    ]
                );
            case Notification::NOTIFICATION_TYPE_QUERY_ACTIVITY:
                return __(
                    'submission.query.activity.contents',
                    [
                        'queryTitle' => $query->getHeadNote()->getTitle(),
                        'submissionTitle' => $submission->getCurrentPublication()->getLocalizedTitle(null, 'html'),
                    ]
                );
        }
        throw new \Exception('Unexpected notification type!');
    }

    /**
     * @copydoc NotificationManagerDelegate::getStyleClass()
     */
    public function getStyleClass(Notification $notification): string
    {
        return NOTIFICATION_STYLE_CLASS_WARNING;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\notification\managerDelegate\QueryNotificationManager', '\QueryNotificationManager');
}
