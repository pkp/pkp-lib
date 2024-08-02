<?php

/**
 * @file classes/scheduledTask/ScheduledTaskHelper.php
 *
 * Copyright (c) 2013-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ScheduledTaskHelper
 *
 * @ingroup scheduledTask
 *
 * @brief Helper class for common scheduled tasks operations.
 */

namespace PKP\scheduledTask;

use APP\core\Application;
use Illuminate\Support\Facades\Mail;
use PKP\config\Config;
use PKP\db\DAORegistry;
use PKP\file\PrivateFileManager;
use PKP\mail\Mailable;
use PKP\site\SiteDAO;

class ScheduledTaskHelper
{
    public const SCHEDULED_TASK_MESSAGE_TYPE_COMPLETED = 'common.completed';
    public const SCHEDULED_TASK_MESSAGE_TYPE_ERROR = 'common.error';
    public const SCHEDULED_TASK_MESSAGE_TYPE_WARNING = 'common.warning';
    public const SCHEDULED_TASK_MESSAGE_TYPE_NOTICE = 'common.notice';
    public const SCHEDULED_TASK_EXECUTION_LOG_DIR = 'scheduledTaskLogs';

    /**
     * Constructor.
     * Overwrites both parameters if one is not passed.
     *
     * @param string $email (optional)          Contact email
     * @param string $contactName (optional)    Contact name
     */
    public function __construct(public string $contactEmail = '', public string $contactName = '')
    {
        if (!$this->contactEmail || !$this->contactName) {
            $siteDao = DAORegistry::getDAO('SiteDAO'); /** @var SiteDAO $siteDao */
            $site = $siteDao->getSite();
            $this->contactEmail = $site->getLocalizedContactEmail();
            $this->contactName = $site->getLocalizedContactName();
        }
    }

    /**
     * Notifies site administrator about the task execution result.
     *
     * @param string $id Task id.
     * @param string $name Task name.
     * @param bool $result Whether or not the task execution was successful.
     * @param string $executionLogFile Task execution log file path.
     * 
     * @return bool True if the notification email was sent successfully
     */
    public function notifyExecutionResult(string $id, string $name, bool $result, string $executionLogFile = ''): bool
    {
        $reportErrorOnly = Config::getVar('schedule', 'scheduled_tasks_report_error_only', true);

        if (!$result || !$reportErrorOnly) {
            $message = $this->getMessage($executionLogFile);

            $type = $result
                ? static::SCHEDULED_TASK_MESSAGE_TYPE_COMPLETED // Success
                : static::SCHEDULED_TASK_MESSAGE_TYPE_ERROR; // Error

            $subject = $name . ' - ' . $id . ' - ' . __($type);
            return $this->_sendEmail($message, $subject);
        }

        return false;
    }

    /**
     * Get execution log email message.
     */
    public function getMessage(string $executionLogFile): string
    {
        if (!$executionLogFile) {
            return __('admin.scheduledTask.noLog');
        }

        $application = Application::get();
        $request = $application->getRequest();
        $router = $request->getRouter();
        $downloadLogUrl = $router->url($request, Application::SITE_CONTEXT_PATH, 'admin', 'downloadScheduledTaskLogFile', null, ['file' => basename($executionLogFile)]);
        return __('admin.scheduledTask.downloadLog', [
            'url' => $downloadLogUrl,
            'softwareName' => __($application->getNameKey()),
        ]);
    }

    /**
     * Clear tasks execution log files.
     */
    public static function clearExecutionLogs(): bool
    {
        $fileMgr = new PrivateFileManager();

        return $fileMgr->rmtree("{$fileMgr->getBasePath()}/" . static::SCHEDULED_TASK_EXECUTION_LOG_DIR);
    }

    /**
     * Download execution log file.
     */
    public static function downloadExecutionLog(string $file): void
    {
        $fileMgr = new PrivateFileManager();

        $fileMgr->downloadByPath("{$fileMgr->getBasePath()}/" . static::SCHEDULED_TASK_EXECUTION_LOG_DIR . "/{$file}");
    }


    /**
     * Send email to the site administrator.
     */
    private function _sendEmail(string $message, string $subject): bool
    {
        $mailable = new Mailable();
        $mailable
            ->to($this->contactEmail, $this->contactName)
            ->from($this->contactEmail, $this->contactName)
            ->subject($subject)
            ->body($message);

        return !is_null(Mail::send($mailable));
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\scheduledTask\ScheduledTaskHelper', '\ScheduledTaskHelper');
    foreach ([
        'SCHEDULED_TASK_MESSAGE_TYPE_COMPLETED',
        'SCHEDULED_TASK_MESSAGE_TYPE_ERROR',
        'SCHEDULED_TASK_MESSAGE_TYPE_WARNING',
        'SCHEDULED_TASK_MESSAGE_TYPE_NOTICE',
        'SCHEDULED_TASK_EXECUTION_LOG_DIR',
    ] as $constantName) {
        define($constantName, constant('\ScheduledTaskHelper::' . $constantName));
    }
}
