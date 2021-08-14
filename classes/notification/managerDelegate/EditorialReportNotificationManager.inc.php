<?php
/**
 * @file classes/notification/managerDelegate/EditorialReportNotificationManager.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EditorialReportNotificationManager
 * @ingroup managerDelegate
 *
 * @brief Editorial report notification manager.
 */

namespace PKP\notification\managerDelegate;

use APP\core\Application;
use APP\core\Services;
use PKP\facades\Locale;
use APP\facades\Repo;
use APP\notification\Notification;
use DateTimeInterface;
use PKP\context\Context;
use PKP\core\PKPApplication;
use PKP\emailTemplate\EmailTemplate;

use PKP\mail\Mail;
use PKP\mail\MailTemplate;
use PKP\notification\NotificationManagerDelegate;
use PKP\notification\PKPNotification;

use PKP\user\User;
use SplFileObject;

class EditorialReportNotificationManager extends NotificationManagerDelegate
{
    /** @var Context Context instance */
    private $_context;
    /** @var Request Request instance */
    private $_request;
    /** @var array Cached message parameters */
    private $_params;
    /** @var array Cached attachment */
    private $_attachmentFilename;
    /** @var array Cache of editorial stats by date range */
    private $_editorialTrends;
    /** @var array Cache of editorial stats for all time */
    private $_editorialTrendsTotal;
    /** @var array Cache of user counts by role */
    private $_userRolesOverview;

    /**
     * @copydoc NotificationManagerDelegate::__construct()
     */
    public function __construct(int $notificationType)
    {
        parent::__construct($notificationType);
        $this->_request = Application::get()->getRequest();
    }

    /**
     * Initializes the class.
     *
     * @param Context $context The context from where the statistics shall be retrieved
     * @param DateTimeInterface $context Start date filter for the ranged statistics
     * @param DateTimeInterface $context End date filter for the ranged statistics
     */
    public function initialize(Context $context, DateTimeInterface $dateStart, DateTimeInterface $dateEnd): void
    {
        $this->_context = $context;
        $locale = $this->_context->getPrimaryLocale();
        $dateStart = $dateStart;
        $dateEnd = $dateEnd;

        $dispatcher = Application::get()->getDispatcher();

        $this->_editorialTrends = Services::get('editorialStats')->getOverview([
            'contextIds' => [$this->_context->getId()],
            'dateStart' => $dateStart->format('Y-m-d'),
            'dateEnd' => $dateEnd->format('Y-m-d'),
        ]);
        $this->_editorialTrendsTotal = Services::get('editorialStats')->getOverview([
            'contextIds' => [$this->_context->getId()]
        ]);

        foreach ($this->_editorialTrends as $stat) {
            switch ($stat['key']) {
                case 'submissionsReceived':
                    $newSubmissions = $stat['value'];
                    break;
                case 'submissionsDeclined':
                    $declinedSubmissions = $stat['value'];
                    break;
                case 'submissionsAccepted':
                    $acceptedSubmissions = $stat['value'];
                    break;
            }
        }

        $this->_params = [
            'newSubmissions' => $newSubmissions,
            'declinedSubmissions' => $declinedSubmissions,
            'acceptedSubmissions' => $acceptedSubmissions,
            'totalSubmissions' => Services::get('editorialStats')->countSubmissionsReceived(['contextIds' => [$this->_context->getId()]]),
            'month' => $this->_getLocalizedMonthName($dateStart, $locale),
            'year' => $dateStart->format('Y'),
            'editorialStatsLink' => $dispatcher->url($this->_request, PKPApplication::ROUTE_PAGE, $this->_context->getPath(), 'stats', 'editorial'),
            'publicationStatsLink' => $dispatcher->url($this->_request, PKPApplication::ROUTE_PAGE, $this->_context->getPath(), 'stats', 'publications')
        ];

        $this->_userRolesOverview = Repo::user()->getRolesOverview(Repo::user()->getCollector()->filterByContextIds([$this->_context->getId()]));

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
            foreach (Application::get()->getApplicationStages() as $stageId) {
                $file->fputcsv([
                    __(Application::get()->getWorkflowStageName($stageId), [], $locale),
                    Services::get('editorialStats')->countActiveByStages($stageId)
                ]);
            }

            $file->fputcsv([]);

            // Editorial trends
            $file->fputcsv([
                __('stats.trends', [], $locale),
                $this->_getLocalizedMonthName($dateStart, $locale) . __('common.commaListSeparator', [], $locale) . $dateStart->format('Y'),
                __('stats.total', [], $locale)
            ]);
            foreach ($this->_editorialTrends as $i => $stat) {
                $file->fputcsv([
                    __($stat['name'], [], $locale),
                    $stat['value'],
                    $this->_editorialTrendsTotal[$i]['value']
                ]);
            }

            $file->fputcsv([]);

            // Count of users by role
            $file->fputcsv([
                __('manager.users', [], $locale),
                __('stats.total', [], $locale)
            ]);
            foreach ($this->_userRolesOverview as $role) {
                $file->fputcsv([
                    __($role['name'], [], $locale),
                    $role['value']
                ]);
            }

            $this->_attachmentFilename = $file->getRealPath();
        } finally {
            $file = null;
        }
    }

    /**
     * Retrieves the localized month name for the given date and locale
     *
     * @param \DateTimeInterface Date object
     * @
     */
    public function _getLocalizedMonthName(\DateTimeInterface $date, ?string $locale = null): string
    {
        static $cache = [];
        $locale ?? $locale = Locale::getLocale();
        $formatter = $cache[$locale] ?? $cache[$locale] = \IntlDateFormatter::create($locale, null, null, null, null, 'MMMM');
        return $formatter->format($date);
    }

    /**
     * @copydoc PKPNotificationOperationManager::getNotificationMessage()
     */
    public function getNotificationMessage($request, $notification): string
    {
        return __('notification.type.editorialReport', [], $this->_context->getPrimaryLocale());
    }

    /**
     * @copydoc PKPNotificationOperationManager::getNotificationMessage()
     */
    public function getNotificationContents($request, $notification): EmailTemplate
    {
        return Repo::emailTemplate()->getByKey($notification->getContextId(), 'STATISTICS_REPORT_NOTIFICATION');
    }

    /**
     * @copydoc PKPNotificationOperationManager::getNotificationUrl()
     */
    public function getNotificationUrl($request, $notification)
    {
        $application = Application::get();
        $context = $application->getContextDAO()->getById($notification->getContextId());
        return $application->getDispatcher()->url($this->_request, PKPApplication::ROUTE_PAGE, $context->getPath(), 'stats', 'editorial');
    }

    /**
     * @copydoc PKPNotificationManager::getIconClass()
     */
    public function getIconClass($notification): string
    {
        return 'notifyIconInfo';
    }

    /**
     * @copydoc PKPNotificationManager::getStyleClass()
     */
    public function getStyleClass($notification): string
    {
        return NOTIFICATION_STYLE_CLASS_INFORMATION;
    }

    /**
     * Sends a notification to the given user.
     *
     * @param User $user The user who will be notified
     *
     * @return PKPNotification The notification instance
     */
    public function notify(User $user): ?PKPNotification
    {
        return parent::createNotification(
            $this->_request,
            $user->getId(),
            PKPNotification::NOTIFICATION_TYPE_EDITORIAL_REPORT,
            $this->_context->getId(),
            null,
            null,
            Notification::NOTIFICATION_LEVEL_TASK,
            ['contents' => __('notification.type.editorialReport.contents', [], $this->_context->getPrimaryLocale())],
            false,
            function ($mail) use ($user) {
                return $this->_setupMessage($mail, $user);
            }
        );
    }

    /**
     * @copydoc PKPNotificationManager::getMailTemplate()
     *
     * @param null|mixed $emailKey
     */
    protected function getMailTemplate($emailKey = null): MailTemplate
    {
        $mail = new MailTemplate('STATISTICS_REPORT_NOTIFICATION', $this->_context->getPrimaryLocale(), $this->_context, false);
        $mail->setFrom($this->_context->getData('contactEmail'), $this->_context->getData('contactName'));
        return $mail;
    }

    /**
     * Setups a customized message for the given user.
     *
     * @param Mail $mail The message which will be customized
     * @param User $user The user who will be notified
     *
     * @return Mail The prepared message
     */
    private function _setupMessage(Mail $mail, User $user): Mail
    {
        $mail->assignParams($this->_getMessageParams($user));
        if ($this->_getMessageAttachment()) {
            $mail->addAttachment($this->_getMessageAttachment(), 'editorial-report.csv');
        }
        return $mail;
    }

    /**
     * Retrieves the message parameters.
     *
     * @param User $user The user who will be notified
     *
     * @return array An array with the parameters and their values
     */
    private function _getMessageParams(User $user): array
    {
        return $this->_params + ['name' => $user->getLocalizedGivenName($this->_context->getPrimaryLocale())];
    }

    /**
     * Retrieves the message attachment.
     *
     * @return string The full path of the attachment
     */
    private function _getMessageAttachment(): string
    {
        return $this->_attachmentFilename;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\notification\managerDelegate\EditorialReportNotificationManager', '\EditorialReportNotificationManager');
}
