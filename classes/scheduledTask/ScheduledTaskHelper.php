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
use PKP\site\Site;
use PKP\site\SiteDAO;
use PKP\xml\XMLNode;

class ScheduledTaskHelper
{
    public const SCHEDULED_TASK_MESSAGE_TYPE_COMPLETED = 'common.completed';
    public const SCHEDULED_TASK_MESSAGE_TYPE_ERROR = 'common.error';
    public const SCHEDULED_TASK_MESSAGE_TYPE_WARNING = 'common.warning';
    public const SCHEDULED_TASK_MESSAGE_TYPE_NOTICE = 'common.notice';
    public const SCHEDULED_TASK_EXECUTION_LOG_DIR = 'scheduledTaskLogs';

    /** @var string Contact email. */
    public $_contactEmail;

    /** @var string Contact name. */
    public $_contactName;

    /**
     * Constructor.
     * Overwrites both parameters if one is not passed.
     *
     * @param string $email (optional)
     * @param string $contactName (optional)
     */
    public function __construct($email = '', $contactName = '')
    {
        if (!$email || !$contactName) {
            $siteDao = DAORegistry::getDAO('SiteDAO'); /** @var SiteDAO $siteDao */
            $site = $siteDao->getSite(); /** @var Site $site */
            $email = $site->getLocalizedContactEmail();
            $contactName = $site->getLocalizedContactName();
        }

        $this->_contactEmail = $email;
        $this->_contactName = $contactName;
    }

    /**
     * Get the arguments for a task from the parsed XML.
     *
     * @param XMLNode $task
     *
     * @return array
     */
    public static function getTaskArgs($task)
    {
        $args = [];
        $index = 0;

        while (($arg = $task->getChildByName('arg', $index)) != null) {
            array_push($args, $arg->getValue());
            $index++;
        }

        return $args;
    }

    /**
     * Check if the specified task should be executed according to the specified
     * frequency and its last run time.
     *
     * @param string $className
     * @param XMLNode $frequency
     *
     * @return string
     */
    public static function checkFrequency($className, $frequency)
    {
        $isValid = true;
        $taskDao = DAORegistry::getDAO('ScheduledTaskDAO'); /** @var ScheduledTaskDAO $taskDao */
        $lastRunTime = $taskDao->getLastRunTime($className);

        // Check day of week
        $dayOfWeek = $frequency->getAttribute('dayofweek');
        if (isset($dayOfWeek)) {
            $isValid = self::_isInRange($dayOfWeek, (int)date('w'), $lastRunTime, 'day', strtotime('-1 week'), strtotime('-1 day'));
        }

        if ($isValid) {
            // Check month
            $month = $frequency->getAttribute('month');
            if (isset($month)) {
                $isValid = self::_isInRange($month, (int)date('n'), $lastRunTime, 'month', strtotime('-1 year'), strtotime('-1 month'));
            }
        }

        if ($isValid) {
            // Check day
            $day = $frequency->getAttribute('day');
            if (isset($day)) {
                $isValid = self::_isInRange($day, (int)date('j'), $lastRunTime, 'day', strtotime('-1 month'), strtotime('-1 day'));
            }
        }

        if ($isValid) {
            // Check hour
            $hour = $frequency->getAttribute('hour');
            if (isset($hour)) {
                $isValid = self::_isInRange($hour, (int)date('G'), $lastRunTime, 'hour', strtotime('-1 day'), strtotime('-1 hour'));
            }
        }

        if ($isValid) {
            // Check minute
            $minute = $frequency->getAttribute('minute');
            if (isset($minute)) {
                $isValid = self::_isInRange($minute, (int)date('i'), $lastRunTime, 'min', strtotime('-1 hour'), strtotime('-1 minute'));
            }
        }

        if ($isValid) {
            // Check second
            $second = $frequency->getAttribute('second');
            if (isset($second)) {
                $isValid = self::_isInRange($second, (int)date('s'), $lastRunTime, 'sec', strtotime('-1 minute'), strtotime('-1 second'));
            }
        }

        return $isValid;
    }

    /**
     * Notifies site administrator about the
     * task execution result.
     *
     * @param int $id Task id.
     * @param string $name Task name.
     * @param bool $result Whether or not the task
     * execution was successful.
     * @param string $executionLogFile Task execution log file path.
     */
    public function notifyExecutionResult($id, $name, $result, $executionLogFile = '')
    {
        $reportErrorOnly = Config::getVar('general', 'scheduled_tasks_report_error_only', true);

        if (!$result || !$reportErrorOnly) {
            $message = $this->getMessage($executionLogFile);

            if ($result) {
                // Success.
                $type = self::SCHEDULED_TASK_MESSAGE_TYPE_COMPLETED;
            } else {
                // Error.
                $type = self::SCHEDULED_TASK_MESSAGE_TYPE_ERROR;
            }

            $subject = $name . ' - ' . $id . ' - ' . __($type);
            return $this->_sendEmail($message, $subject);
        }

        return false;
    }

    /**
     * Get execution log email message.
     *
     * @param string $executionLogFile
     *
     * @return string
     */
    public function getMessage($executionLogFile)
    {
        if (!$executionLogFile) {
            return __('admin.scheduledTask.noLog');
        }

        $application = Application::get();
        $request = $application->getRequest();
        $router = $request->getRouter();
        $downloadLogUrl = $router->url($request, 'index', 'admin', 'downloadScheduledTaskLogFile', null, ['file' => basename($executionLogFile)]);
        return __('admin.scheduledTask.downloadLog', [
            'url' => $downloadLogUrl,
            'softwareName' => __($application->getNameKey()),
        ]);
    }

    //
    // Static methods.
    //
    /**
     * Clear tasks execution log files.
     */
    public static function clearExecutionLogs()
    {
        $fileMgr = new PrivateFileManager();

        $fileMgr->rmtree("{$fileMgr->getBasePath()}/" . self::SCHEDULED_TASK_EXECUTION_LOG_DIR);
    }

    /**
     * Download execution log file.
     *
     * @param string $file
     */
    public static function downloadExecutionLog($file)
    {
        $fileMgr = new PrivateFileManager();

        $fileMgr->downloadByPath("{$fileMgr->getBasePath()}/" . self::SCHEDULED_TASK_EXECUTION_LOG_DIR . "/{$file}");
    }


    //
    // Private helper methods.
    //
    /**
     * Send email to the site administrator.
     */
    private function _sendEmail(string $message, string $subject): bool
    {
        $mailable = new Mailable();
        $mailable
            ->to($this->_contactEmail, $this->_contactName)
            ->from($this->_contactEmail, $this->_contactName)
            ->subject($subject)
            ->body($message);

        return !is_null(Mail::send($mailable));
    }

    /**
     * Check if a value is within the specified range.
     *
     * @param string $rangeStr the range (e.g., 0, 1-5, *, etc.)
     * @param int $currentValue value to check if its in the range
     * @param int $lastTimestamp the last time the task was executed
     * @param string $timeCompareStr value to use in strtotime("-X $timeCompareStr")
     * @param int $passTimestamp If the last run is older than this timestamp, consider executing.
     * @param int $blockTimestamp If the last run is newer than this timestamp, do not execute.
     *
     * @return bool
     */
    private static function _isInRange($rangeStr, $currentValue, $lastTimestamp, $timeCompareStr, $passTimestamp, $blockTimestamp)
    {
        $isValid = false;
        $rangeArray = explode(',', $rangeStr);

        // If the last task run is newer than the block timestamp, do not execute the task again yet.
        if ($lastTimestamp > $blockTimestamp) {
            return false;
        }

        // If the last task run is older than the pass timestamp, consider running the task.
        if ($passTimestamp > $lastTimestamp) {
            $isValid = true;
        }

        for ($i = 0, $count = count($rangeArray); !$isValid && ($i < $count); $i++) {
            if ($rangeArray[$i] == '*') {
                // Is wildcard
                $isValid = true;
            }
            if (is_numeric($rangeArray[$i])) {
                // Is just a value
                $isValid = ($currentValue == (int)$rangeArray[$i]);
            } elseif (preg_match('/^(\d*)\-(\d*)$/', $rangeArray[$i], $matches)) {
                // Is a range
                $isValid = self::_isInNumericRange($currentValue, (int)$matches[1], (int)$matches[2]);
            } elseif (preg_match('/^(.+)\/(\d+)$/', $rangeArray[$i], $matches)) {
                // Is a range with a skip factor
                $skipRangeStr = $matches[1];
                $skipFactor = (int)$matches[2];

                if ($skipRangeStr == '*') {
                    $isValid = true;
                } elseif (preg_match('/^(\d*)\-(\d*)$/', $skipRangeStr, $matches)) {
                    $isValid = self::_isInNumericRange($currentValue, (int)$matches[1], (int)$matches[2]);
                }

                if ($isValid) {
                    // Check against skip factor
                    $isValid = (strtotime("-{$skipFactor} {$timeCompareStr}") > $lastTimestamp);
                }
            }
        }

        return $isValid;
    }

    /**
     * Check if a numeric value is within the specified range.
     *
     * @param int $value
     * @param int $min
     * @param int $max
     *
     * @return bool
     */
    private static function _isInNumericRange($value, $min, $max)
    {
        return ($value >= $min && $value <= $max);
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
