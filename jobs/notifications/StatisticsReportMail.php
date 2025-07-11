<?php

/**
 * @file jobs/notifications/StatisticsReportMail.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class StatisticsReportMail
 *
 * @ingroup jobs
 *
 * @brief Class to send email to editors with monthly editorial report
 */

namespace PKP\jobs\notifications;

use APP\core\Application;
use APP\facades\Repo;
use APP\notification\NotificationManager;
use DateTimeImmutable;
use Illuminate\Bus\Batchable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use IntlDateFormatter;
use PKP\context\Context;
use PKP\jobs\BaseJob;
use PKP\mail\mailables\StatisticsReportNotify;
use PKP\notification\Notification;
use SplFileObject;

class StatisticsReportMail extends BaseJob
{
    use Batchable;

    protected Collection $userIds;
    protected int $contextId;
    protected DateTimeImmutable $dateStart;
    protected DateTimeImmutable $dateEnd;

    public function __construct(Collection $userIds, int $contextId, DateTimeImmutable $dateStart, DateTimeImmutable $dateEnd)
    {
        parent::__construct();

        $this->userIds = $userIds;
        $this->contextId = $contextId;
        $this->dateStart = $dateStart;
        $this->dateEnd = $dateEnd;
    }

    public function handle()
    {
        $contextDao = Application::getContextDAO();
        $context = $contextDao->getById($this->contextId); /** @var Context $context */
        $locale = $context->getPrimaryLocale();
        $template = Repo::emailTemplate()->getByKey($this->contextId, StatisticsReportNotify::getEmailTemplateKey());

        $editorialTrends = app()->get('editorialStats')->getOverview([
            'contextIds' => [$context->getId()],
            'dateStart' => $this->dateStart->format('Y-m-d'),
            'dateEnd' => $this->dateEnd->format('Y-m-d'),
        ]);
        $editorialTrendsTotal = app()->get('editorialStats')->getOverview(['contextIds' => [$context->getId()]]);
        $totalSubmissions = app()->get('editorialStats')->countSubmissionsReceived(['contextIds' => [$context->getId()]]);
        $formatter = IntlDateFormatter::create(
            $locale,
            IntlDateFormatter::FULL,
            IntlDateFormatter::FULL,
            null,
            null,
            'MMMM'
        );
        $month = $formatter->format($this->dateStart);
        $year = $this->dateStart->format('Y');
        $filePath = $this->createCsvAttachment($editorialTrends, $editorialTrendsTotal, $locale, $month, $year);

        foreach ($this->userIds as $userId) {
            /** @var int $userId */
            $user = Repo::user()->get($userId);
            if (!$user) {
                continue;
            }

            $notificationManager = new NotificationManager();
            $notification = $notificationManager->createNotification(
                $user->getId(),
                Notification::NOTIFICATION_TYPE_EDITORIAL_REPORT,
                $this->contextId
            );

            $mailable = new StatisticsReportNotify($context, $editorialTrends, $totalSubmissions, $month, $year);
            $mailable->recipients([$user]);
            $mailable->from($context->getContactEmail(), $context->getContactName());
            $mailable->subject($template->getLocalizedData('subject', $locale));
            $mailable->body($template->getLocalizedData('body', $locale));
            $mailable->setLocale($locale);
            $mailable->attach($filePath, ['as' => 'editorial-report.csv']);
            $mailable->allowUnsubscribe($notification);

            Mail::send($mailable);
        }

        unlink($filePath);
    }

    /**
     * Create a csv file on the file system
     *
     * @return string the full path to the attachment
     */
    protected function createCsvAttachment(array $editorialTrends, array $editorialTrendsTotal, string $locale, $month, $year): string
    {
        $userRolesOverview = Repo::user()->getRolesOverview(Repo::user()->getCollector()->filterByContextIds([$this->contextId]));

        // Create the CSV file attachment
        // Active submissions by stage
        $file = new SplFileObject(tempnam(sys_get_temp_dir(), 'tmp'), 'wb');
        // Adds BOM (byte order mark) to enforce the UTF-8 format
        try {
            $file->fwrite("\xEF\xBB\xBF");
            $file->fputcsv([
                __('stats.submissionsActive', [], $locale),
                __('stats.total', [], $locale)
            ]);
            foreach (Application::getApplicationStages() as $stageId) {
                $file->fputcsv([
                    __(Application::getWorkflowStageName($stageId), [], $locale),
                    app()->get('editorialStats')->countActiveByStages($stageId)
                ]);
            }

            $file->fputcsv([]);

            // Editorial trends
            $file->fputcsv([
                __('stats.trends', [], $locale),
                $month . __('common.commaListSeparator', [], $locale) . $year,
                __('stats.total', [], $locale)
            ]);
            foreach ($editorialTrends as $i => $stat) {
                $file->fputcsv([
                    __($stat['name'], [], $locale),
                    $stat['value'],
                    $editorialTrendsTotal[$i]['value']
                ]);
            }

            $file->fputcsv([]);

            // Count of users by role
            $file->fputcsv([
                __('manager.users', [], $locale),
                __('stats.total', [], $locale)
            ]);
            foreach ($userRolesOverview as $role) {
                $file->fputcsv([
                    __($role['name'], [], $locale),
                    $role['value']
                ]);
            }

            $filePath = $file->getRealPath();
        } finally {
            $file = null;
        }

        return $filePath;
    }
}
