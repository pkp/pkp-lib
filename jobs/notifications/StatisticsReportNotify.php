<?php

/**
 * @file jobs/notifications/StatisticsReportNotify.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class StatisticsReportNotify
 * @ingroup jobs
 *
 * @brief Class to create system notifications for editors about editorial report
 */

namespace PKP\jobs\notifications;

use APP\facades\Repo;
use Illuminate\Bus\Batchable;
use Illuminate\Support\Collection;
use PKP\notification\managerDelegate\EditorialReportNotificationManager;
use PKP\jobs\BaseJob;

class StatisticsReportNotify extends BaseJob
{
    use Batchable;

    protected Collection $userIds;
    protected EditorialReportNotificationManager $notificationManager;

    public function __construct(Collection $userIds, EditorialReportNotificationManager $notificationManager)
    {
        parent::__construct();

        $this->userIds = $userIds;
        $this->notificationManager = $notificationManager;
    }

    public function handle()
    {
        foreach ($this->userIds as $userId) {
            /** @var int $userId */
            $user = Repo::user()->get($userId);
            if (!$user) {
                continue;
            }
            $this->notificationManager->notify($user);
        }
    }
}
