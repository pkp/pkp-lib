<?php

/**
 * @file Jobs/Notifications/NewAnnouncementNotifyUsers.php
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

namespace PKP\Jobs\Notifications;

use APP\facades\Repo;
use APP\notification\Notification;
use Illuminate\Bus\Batchable;
use PKP\Domains\Jobs\Exceptions\JobException;
use PKP\notification\managerDelegate\AnnouncementNotificationManager;
use PKP\Support\Jobs\BaseJob;
use Illuminate\Support\Collection;

class NewAnnouncementNotifyUsers extends BaseJob
{
    use Batchable;

    protected Collection $recipientIds;
    protected int $contextId;
    protected int $announcementId;

    public function __construct(Collection $recipientIds, int $contextId, int $announcementId)
    {
        parent::__construct();

        $this->recipientIds = $recipientIds;
        $this->contextId = $contextId;
        $this->announcementId = $announcementId;
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

        foreach ($this->recipientIds as $recipientId) {
            $recipient = Repo::user()->get($recipientId);
            if (!$recipient) {
                continue;
            }
            $announcementNotificationManager->notify($recipient);
        }
    }
}
