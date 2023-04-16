<?php

/**
 * @file classes/task/StatisticsReport.php
 *
 * Copyright (c) 2013-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class StatisticsReport
 *
 * @ingroup tasks
 *
 * @brief Class responsible to send the monthly statistics report.
 */

namespace PKP\task;

use APP\core\Application;
use DateTimeImmutable;
use Illuminate\Support\Facades\Bus;
use PKP\db\DAORegistry;
use PKP\jobs\notifications\StatisticsReportMail;
use PKP\jobs\notifications\StatisticsReportNotify;
use PKP\mail\Mailer;
use PKP\notification\managerDelegate\EditorialReportNotificationManager;
use PKP\notification\NotificationSubscriptionSettingsDAO;
use PKP\notification\PKPNotification;
use PKP\scheduledTask\ScheduledTask;
use PKP\security\Role;

class StatisticsReport extends ScheduledTask
{
    /** @var array List of roles that might be notified */
    private $_roleIds = [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR];

    /**
     * @copydoc ScheduledTask::getName()
     */
    public function getName(): string
    {
        return __('admin.scheduledTask.statisticsReport');
    }

    /**
     * @copydoc ScheduledTask::executeActions()
     */
    public function executeActions(): bool
    {
        $contextDao = Application::get()->getContextDAO();
        $dateStart = new DateTimeImmutable('first day of previous month midnight');
        $dateEnd = new DateTimeImmutable('first day of this month midnight');

        $jobs = [];
        for ($contexts = $contextDao->getAll(true); $context = $contexts->next();) {
            if (!$context->getData('editorialStatsEmail')) {
                continue;
            }

            /** @var NotificationSubscriptionSettingsDAO $notificationSubscriptionSettingsDao */
            $notificationSubscriptionSettingsDao = DAORegistry::getDAO('NotificationSubscriptionSettingsDAO');
            $editorialReportNotificationManager = new EditorialReportNotificationManager(PKPNotification::NOTIFICATION_TYPE_EDITORIAL_REPORT);
            $editorialReportNotificationManager->initialize(
                $context
            );

            $userIdsToNotify = $notificationSubscriptionSettingsDao->getSubscribedUserIds(
                [NotificationSubscriptionSettingsDAO::BLOCKED_NOTIFICATION_KEY],
                [PKPNotification::NOTIFICATION_TYPE_EDITORIAL_REPORT],
                [$context->getId()],
                $this->_roleIds
            );

            foreach ($userIdsToNotify->chunk(PKPNotification::NOTIFICATION_CHUNK_SIZE_LIMIT) as $notifyUserIds) {
                $notifyJob = new StatisticsReportNotify(
                    $notifyUserIds,
                    $editorialReportNotificationManager
                );
                $jobs[] = $notifyJob;
            }

            $userIdsToMail = $notificationSubscriptionSettingsDao->getSubscribedUserIds(
                [NotificationSubscriptionSettingsDAO::BLOCKED_NOTIFICATION_KEY, NotificationSubscriptionSettingsDAO::BLOCKED_EMAIL_NOTIFICATION_KEY],
                [PKPNotification::NOTIFICATION_TYPE_EDITORIAL_REPORT],
                [$context->getId()],
                $this->_roleIds
            );

            foreach ($userIdsToMail->chunk(Mailer::BULK_EMAIL_SIZE_LIMIT) as $mailUserIds) {
                $mailJob = new StatisticsReportMail(
                    $mailUserIds,
                    $context->getId(),
                    $dateStart,
                    $dateEnd
                );
                $jobs[] = $mailJob;
            }
        }

        Bus::batch($jobs)->dispatch();
        return true;
    }
}
