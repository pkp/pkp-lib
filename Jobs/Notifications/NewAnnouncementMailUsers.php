<?php

/**
 * @file Jobs/Notifications/NewAnnouncementMailUsers.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NewAnnouncementMailUsers
 * @ingroup jobs
 *
 * @brief Class to send email notifications when a new announcement is added
 */

namespace PKP\Jobs\Notifications;

use APP\core\Application;
use APP\facades\Repo;
use Illuminate\Bus\Batchable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use PKP\announcement\Announcement;
use PKP\context\Context;
use PKP\Domains\Jobs\Exceptions\JobException;
use PKP\emailTemplate\EmailTemplate;
use PKP\mail\mailables\AnnouncementNotify;
use PKP\Support\Jobs\BaseJob;
use PKP\user\User;

class NewAnnouncementMailUsers extends BaseJob
{
    use Batchable;

    protected Collection $recipientIds;
    protected int $contextId;
    protected int $announcementId;
    protected User $sender;
    protected string $locale;

    public function __construct(Collection $recipientIds, int $contextId, int $announcementId, User $sender, string $locale)
    {
        parent::__construct();

        $this->recipientIds = $recipientIds;
        $this->contextId = $contextId;
        $this->announcementId = $announcementId;
        $this->sender = $sender;
        $this->locale = $locale;
    }

    public function handle()
    {
        $announcement = Repo::announcement()->get($this->announcementId);
        // Announcement was removed
        if (!$announcement) {
            throw new JobException(JobException::INVALID_PAYLOAD);
            return;
        }

        $context = Application::getContextDAO()->getById($this->contextId);
        $template = Repo::emailTemplate()->getByKey($context->getId(), AnnouncementNotify::getEmailTemplateKey());

        foreach ($this->recipientIds as $recipientId) {
            $recipient = Repo::user()->get($recipientId);
            if (!$recipient) {
                continue;
            }

            // Send email
            $mailable = $this->createMailable($context, $recipient, $announcement, $template);
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
        $mailable->body($template->getData('body', $this->locale));
        $mailable->subject($template->getData('subject', $this->locale));

        return $mailable;
    }
}
