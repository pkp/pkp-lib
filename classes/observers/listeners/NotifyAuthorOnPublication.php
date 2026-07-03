<?php

/**
 * @file classes/observers/listeners/NotifyAuthorOnPublication.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NotifyAuthorOnPublication
 *
 * @brief Notify the author(s) upon a publication event.
 */

namespace PKP\observers\listeners;

use APP\core\Application;
use APP\facades\Repo;
use APP\notification\NotificationManager;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Mail;
use PKP\core\PKPApplication;
use PKP\db\DAORegistry;
use PKP\facades\Locale;
use PKP\mail\mailables\AuthorPublicationPublished;
use PKP\notification\Notification;
use PKP\notification\NotificationSubscriptionSettingsDAO;
use PKP\observers\events\PublicationPublished;
use PKP\observers\events\PublicationUnpublished;
use PKP\security\Role;
use PKP\stageAssignment\StageAssignment;

class NotifyAuthorOnPublication
{
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(
            [PublicationPublished::class],
            NotifyAuthorOnPublication::class
        );
    }

    public function handle(PublicationPublished|PublicationUnpublished $event): void
    {
        $notificationMgr = new NotificationManager();

        $submission = $event->submission;
        $publication = $event->publication;
        $context = Application::getContextDAO()->getById($submission->getData('contextId'));

        switch (get_class($event)) {
            case PublicationPublished::class:
                $stageAssignments = StageAssignment::withSubmissionIds([$submission->getId()])
                    ->withRoleIds([Role::ROLE_ID_AUTHOR])
                    ->get();

                $requestLocale = Locale::getLocale();
                Locale::setLocale($context->getPrimaryLocale());
                $notificationSubscriptionSettingsDao = DAORegistry::getDAO('NotificationSubscriptionSettingsDAO');

                foreach ($stageAssignments as $stageAssignment) {
                    $user = Repo::user()->get($stageAssignment->userId);
                    if (!$user) {
                        continue;
                    } // Skip disabled users

                    $notification = $notificationMgr->createNotification(
                        $user->getId(),
                        Notification::NOTIFICATION_TYPE_PUBLICATION_PUBLISHED,
                        $context->getId(),
                        PKPApplication::ASSOC_TYPE_PUBLICATION,
                        $publication->getId(),
                        Notification::NOTIFICATION_LEVEL_TASK
                    );
                    if (!$notification) {
                        continue;
                    }

                    $unsubscribed = in_array(
                        Notification::NOTIFICATION_TYPE_PUBLICATION_PUBLISHED,
                        $notificationSubscriptionSettingsDao->getNotificationSubscriptionSettings(
                            NotificationSubscriptionSettingsDAO::BLOCKED_EMAIL_NOTIFICATION_KEY,
                            $user->getId(),
                            $context->getId()
                        )
                    );
                    if ($unsubscribed) {
                        continue;
                    }

                    $mailable = new AuthorPublicationPublished($context, $publication);
                    $emailTemplate = Repo::emailTemplate()->getByKey($context->getId(), $mailable::getEmailTemplateKey());

                    $mailable
                        ->from($context->getContactEmail(), $context->getLocalizedName(Locale::getLocale()))
                        ->recipients([$user])
                        ->subject($emailTemplate->getLocalizedData('subject'))
                        ->body($emailTemplate->getLocalizedData('body'));

                    Mail::send($mailable);
                }

                Locale::setLocale($requestLocale);
                break;
            case PublicationUnpublished::class:
                Notification::withType(Notification::NOTIFICATION_TYPE_PUBLICATION_PUBLISHED)
                    ->withAssoc(PKPApplication::ASSOC_TYPE_PUBLICATION, $publication->getId())
                    ->delete();
                break;
        }
    }
}
