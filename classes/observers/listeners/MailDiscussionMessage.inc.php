<?php

/**
 * @file classes/observers/listeners/MailDiscussionUpdated.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class MailDiscussionUpdated
 * @ingroup observers_listeners
 *
 * @brief Notify users when a discussion is updated
 */

namespace PKP\observers\listeners;

use Illuminate\Support\Facades\Mail;
use PKP\db\DAORegistry;
use PKP\notification\NotificationSubscriptionSettingsDAO;
use PKP\notification\PKPNotification;
use PKP\observers\events\DiscussionMessageSent;

class MailDiscussionMessage
{
    /**
     * @param \PKP\observers\events\DiscussionMessageSent $event
     */
    public function handle(DiscussionMessageSent $event)
    {
        $notificationSubscriptionSettingsDao = DAORegistry::getDAO('NotificationSubscriptionSettingsDAO');

        $users = $event->formEmailData->getRecipients($event->context->getId());
        foreach ($users as $user) {
            // Check if user is unsubscribed
            $notificationSubscriptionSettings = $notificationSubscriptionSettingsDao->getNotificationSubscriptionSettings(
                NotificationSubscriptionSettingsDAO::BLOCKED_EMAIL_NOTIFICATION_KEY,
                $user->getId(),
                (int) $event->context->getId()
            );
            if (in_array(PKPNotification::NOTIFICATION_TYPE_NEW_QUERY, $notificationSubscriptionSettings)) {
                return;
            }

            $submission = $event->submission;
            $sender = $event->formEmailData->getSender();

            $mailable = new \PKP\mail\mailables\MailDiscussionMessage($event->context, $submission);
            $emailTemplate = $mailable->getTemplate($event->context->getId());

            $mailable->addVariables(array_merge(
                [
                    'siteTitle' => $mailable->viewData['contextName'],
                ],
                $event->formEmailData->getVariables($user->getId())
            ));

            $mailable
                ->body($emailTemplate->getLocalizedData('body'))
                ->subject($emailTemplate->getLocalizedData('subject'))
                ->setSender($sender)
                ->setRecipients([$user])
                ->replyTo($event->context->getContactEmail(), $event->context->getContactName());

            Mail::send($mailable);
        }
    }
}
