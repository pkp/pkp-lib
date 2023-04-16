<?php
/**
 * @file classes/observers/listeners/AssignEditors.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AssignEditors
 *
 * @ingroup observers_listeners
 *
 * @brief Assign editors to a submission based on the configuration settings
 *
 * If no editors are assigned, creates a notification that an editor needs
 * to be assigned.
 */

namespace PKP\observers\listeners;

use APP\core\Application;
use APP\facades\Repo;
use APP\notification\Notification;
use APP\notification\NotificationManager;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Mail;
use PKP\context\SubEditorsDAO;
use PKP\db\DAORegistry;
use PKP\log\SubmissionEmailLogDAO;
use PKP\log\SubmissionEmailLogEntry;
use PKP\mail\mailables\SubmissionNeedsEditor;
use PKP\notification\NotificationSubscriptionSettingsDAO;
use PKP\observers\events\SubmissionSubmitted;
use PKP\security\Role;

class AssignEditors
{
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(
            SubmissionSubmitted::class,
            AssignEditors::class
        );
    }

    public function handle(SubmissionSubmitted $event)
    {
        /** @var SubEditorsDAO $subEditorsDao */
        $subEditorsDao = DAORegistry::getDAO('SubEditorsDAO');
        $assignedUserIds = $subEditorsDao->assignEditors($event->submission, $event->context);

        if ($assignedUserIds->count()) {
            return;
        }

        $managers = Repo::user()
            ->getCollector()
            ->filterByRoleIds([Role::ROLE_ID_MANAGER])
            ->filterByContextIds([$event->context->getId()])
            ->getMany();

        if (!$managers->count()) {
            return;
        }

        $notificationManager = new NotificationManager();
        foreach ($managers as $manager) {
            $notificationManager->createNotification(
                Application::get()->getRequest(),
                $manager->getId(),
                Notification::NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_REQUIRED,
                $event->context->getId(),
                Application::ASSOC_TYPE_SUBMISSION,
                $event->submission->getId(),
                Notification::NOTIFICATION_LEVEL_TASK
            );
        }

        /** @var NotificationSubscriptionSettingsDAO $notificationSubscriptionSettingsDao */
        $notificationSubscriptionSettingsDao = DAORegistry::getDAO('NotificationSubscriptionSettingsDAO');
        $emailTemplate = Repo::emailTemplate()->getByKey($event->context->getId(), SubmissionNeedsEditor::getEmailTemplateKey());
        $mailable = new SubmissionNeedsEditor($event->context, $event->submission);
        $mailable
            ->from($event->context->getData('contactEmail'), $event->context->getData('contactName'))
            ->subject($emailTemplate->getLocalizedData('subject'))
            ->body($emailTemplate->getLocalizedData('body'));

        foreach ($managers as $manager) {
            $unsubscribed = in_array(
                Notification::NOTIFICATION_TYPE_SUBMISSION_SUBMITTED,
                $notificationSubscriptionSettingsDao->getNotificationSubscriptionSettings(
                    NotificationSubscriptionSettingsDAO::BLOCKED_EMAIL_NOTIFICATION_KEY,
                    $manager->getId(),
                    $event->context->getId()
                )
            );

            if ($unsubscribed) {
                continue;
            }

            Mail::send($mailable->recipients([$manager]));

            /** @var SubmissionEmailLogDAO $logDao */
            $logDao = DAORegistry::getDAO('SubmissionEmailLogDAO');
            $logDao->logMailable(
                SubmissionEmailLogEntry::SUBMISSION_EMAIL_NEEDS_EDITOR,
                $mailable,
                $event->submission
            );
        }
    }
}
