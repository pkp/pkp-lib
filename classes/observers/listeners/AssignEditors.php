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
use APP\notification\NotificationManager;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Mail;
use PKP\context\SubEditorsDAO;
use PKP\db\DAORegistry;
use PKP\log\SubmissionEmailLogEventType;
use PKP\mail\mailables\SubmissionNeedsEditor;
use PKP\notification\Notification;
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
        /** @var NotificationSubscriptionSettingsDAO $notificationSubscriptionSettingsDao */
        $notificationSubscriptionSettingsDao = DAORegistry::getDAO('NotificationSubscriptionSettingsDAO');
        foreach ($managers as $manager) {

            // Send notification
            $notification = $notificationManager->createNotification(
                Application::get()->getRequest(),
                $manager->getId(),
                Notification::NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_REQUIRED,
                $event->context->getId(),
                Application::ASSOC_TYPE_SUBMISSION,
                $event->submission->getId(),
                Notification::NOTIFICATION_LEVEL_TASK
            );

            // Check if subscribed to this type of emails
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

            // Send email
            $emailTemplate = Repo::emailTemplate()->getByKey($event->context->getId(), SubmissionNeedsEditor::getEmailTemplateKey());
            $mailable = new SubmissionNeedsEditor($event->context, $event->submission);

            // The template may not exist, see pkp/pkp-lib#9217; FIXME remove after #9202 is resolved
            if (!$emailTemplate) {
                $emailTemplate = Repo::emailTemplate()->getByKey($event->context->getId(), 'NOTIFICATION');
                $request = Application::get()->getRequest();
                $mailable->addData([
                    'notificationContents' => $notificationManager->getNotificationContents($request, $notification),
                    'notificationUrl' => $notificationManager->getNotificationUrl($request, $notification),
                ]);
            }

            $mailable
                ->from($event->context->getData('contactEmail'), $event->context->getData('contactName'))
                ->subject($emailTemplate->getLocalizedData('subject'))
                ->body($emailTemplate->getLocalizedData('body'))
                ->recipients([$manager]);

            Mail::send($mailable);

            // Log email
            Repo::emailLogEntry()->logMailable(
                SubmissionEmailLogEventType::NEEDS_EDITOR,
                $mailable,
                $event->submission
            );
        }
    }
}
