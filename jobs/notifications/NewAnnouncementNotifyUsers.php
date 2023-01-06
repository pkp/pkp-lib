<?php

/**
 * @file jobs/notifications/NewAnnouncementNotifyUsers.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NewAnnouncementNotifyUsers
 * @ingroup jobs
 *
 * @brief Class to send system notifications when a new announcement is added
 */

namespace PKP\jobs\notifications;

use APP\core\Application;
use APP\facades\Repo;
use APP\notification\Notification;
use Illuminate\Bus\Batchable;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use PKP\announcement\Announcement;
use PKP\context\Context;
use PKP\emailTemplate\EmailTemplate;
use PKP\mail\mailables\AnnouncementNotify;
use PKP\user\User;
use Illuminate\Support\Collection;
use PKP\notification\managerDelegate\AnnouncementNotificationManager;
use PKP\jobs\BaseJob;
use PKP\job\exceptions\JobException;

class NewAnnouncementNotifyUsers extends BaseJob
{
    use Batchable;

    protected Collection $recipientIds;
    protected int $contextId;
    protected int $announcementId;
    protected string $locale;

    // Sender of the email
    protected ?User $sender;

    public function __construct(
        Collection $recipientIds,
        int $contextId,
        int $announcementId,
        string $locale,
        ?User $sender = null // Leave null to not send an email
    )
    {
        parent::__construct();

        $this->recipientIds = $recipientIds;
        $this->contextId = $contextId;
        $this->announcementId = $announcementId;
        $this->locale = $locale;
        $this->sender = $sender;
    }

    public function handle()
    {
        $announcement = Repo::announcement()->get($this->announcementId);
        // Announcement was removed
        if (!$announcement) {
            throw new JobException(JobException::INVALID_PAYLOAD);
        }

        $announcementNotificationManager = new AnnouncementNotificationManager(Notification::NOTIFICATION_TYPE_NEW_ANNOUNCEMENT);
        $announcementNotificationManager->initialize($announcement);
        $context = Application::getContextDAO()->getById($this->contextId);
        $template = Repo::emailTemplate()->getByKey($context->getId(), AnnouncementNotify::getEmailTemplateKey());

        foreach ($this->recipientIds as $recipientId) {
            /** @var int $recipientId */
            $recipient = Repo::user()->get($recipientId);
            if (!$recipient) {
                continue;
            }
            $notification = $announcementNotificationManager->notify($recipient);

            if (!$this->sender) {
                continue;
            }

            // Send email
            $mailable = $this->createMailable($context, $recipient, $announcement, $template)
                ->allowUnsubscribe($notification);

            $mailable->setData($this->locale);
            Mail::send($mailable);
        }
    }

    /**
     * Creates new announcement notification email
     */
    protected function createMailable(
        Context $context,
        User $recipient,
        Announcement $announcement,
        EmailTemplate $template
    ): AnnouncementNotify
    {
        $mailable = new AnnouncementNotify($context, $announcement);

        $mailable->sender($this->sender);
        $mailable->recipients([$recipient]);
        $mailable->body($template->getLocalizedData('body', $this->locale));
        $mailable->subject($template->getLocalizedData('subject', $this->locale));

        return $mailable;
    }
}
