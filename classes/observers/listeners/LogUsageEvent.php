<?php

/**
 * @file classes/observers/listeners/LogUsageEvent.php
 *
 * Copyright (c) 2022 Simon Fraser University
 * Copyright (c) 2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class LogUsageEvent
 * @ingroup observers_traits
 *
 * @brief Listener listening for and logging the usage events.
 */

namespace PKP\observers\listeners;

use APP\core\Application;
use APP\observers\events\UsageEvent;
use APP\statistics\StatisticsHelper;
use APP\submission\Submission;
use Illuminate\Events\Dispatcher;
use PKP\cache\FileCache;
use PKP\file\PrivateFileManager;
use PKP\plugins\Hook;

class LogUsageEvent
{
    public FileCache $geoDataCache;
    public FileCache $institutionDataCache;

    /**
     * Maps methods with correspondent events to listen
     */
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(
            UsageEvent::class,
            self::class . '@handle'
        );
    }

    /**
     * Handle the event.
     */
    public function handle(UsageEvent $usageEvent): void
    {
        if (!$this->canHandle($usageEvent)) {
            return;
        }

        $usageEventLogEntry = $this->prepareUsageEvent($usageEvent);
        $this->logUsageEvent($usageEventLogEntry);
    }

    /**
     * Shall this event be processed here
     */
    protected function canHandle(UsageEvent $usageEvent): bool
    {
        if ($usageEvent->request->getDoNotTrack()) {
            return false;
        }

        if (in_array($usageEvent->assocType, [
            Application::ASSOC_TYPE_SUBMISSION,
            Application::ASSOC_TYPE_SUBMISSION_FILE,
            Application::ASSOC_TYPE_SUBMISSION_FILE_COUNTER_OTHER,
        ]) && $usageEvent->submission->getData('status') != Submission::STATUS_PUBLISHED) {
            return false;
        }

        if (Application::get()->getName() == 'ojs2') {
            if (in_array($usageEvent->assocType, [Application::ASSOC_TYPE_ISSUE, Application::ASSOC_TYPE_ISSUE_GALLEY]) &&
                !$usageEvent->issue->getPublished()) {
                return false;
            }
        } elseif (Application::get()->getName() == 'omp') {
            if (in_array($usageEvent->assocType, [Application::ASSOC_TYPE_CHAPTER]) &&
                $usageEvent->submission->getData('status') != Submission::STATUS_PUBLISHED) {
                return false;
            }
        }

        return true;
    }

    /**
     * Log the usage event
     */
    protected function logUsageEvent(array $usageEventLogEntry): void
    {
        Hook::call('Stats::logUsageEvent', [$usageEventLogEntry]);

        $usageEventLogEntry = json_encode($usageEventLogEntry) . PHP_EOL;

        // Log file name (from the current day)
        $logFileName = $this->getUsageEventLogFileName();

        // Write the event to the log file
        // Keep the locking in order not to care about the filesystems's block sizes and if the file is on a local filesystem
        $fp = fopen($logFileName, 'a+b');
        if (flock($fp, LOCK_EX)) {
            fwrite($fp, $usageEventLogEntry);
            flock($fp, LOCK_UN);
        } else {
            error_log("UsageEventLog: Couldn't lock the usage event log file.");
        }
        fclose($fp);
    }

    /**
     * Prepare the usage event:
     *  create new daily salt file, if necessary
     *  get Geo data, if needed
     *  get institution IDs, if needed
     *  hash the IP
     */
    protected function prepareUsageEvent(UsageEvent $usageEvent): array
    {
        $request = $usageEvent->request;

        // The current usage event log file name (from the current day)
        $logFileName = $this->getUsageEventLogFileName();

        // Salt management.
        [$salt, $flushCache] = $this->getSalt($logFileName);

        // Hash the IP
        $ip = $request->getRemoteAddr();
        $hashedIp = StatisticsHelper::hashIp($ip, $salt);
        if ($hashedIp === null) {
            error_log('UsageEventLog: the IP cound not be hashed.');
        }

        $site = $request->getSite();
        $context = $usageEvent->context;

        // Geo data
        $statisticsHelper = new StatisticsHelper();
        [$country, $region, $city] = $statisticsHelper->getGeoData($site, $context, $ip, $hashedIp, $flushCache);

        // institutions IDs
        $institutionIds = [];
        if ($context->isInstitutionStatsEnabled($site)) {
            $institutionIds = $statisticsHelper->getInstitutionIds($context->getId(), $ip, $hashedIp, $flushCache);
        }

        // format the usage event log entry
        $usageEventLogEntry = [
            'time' => $usageEvent->time,
            'ip' => $hashedIp,
            'userAgent' => $request->getUserAgent(),
            'canonicalUrl' => $usageEvent->canonicalUrl,
            'assocType' => $usageEvent->assocType,
            'contextId' => $context->getId(),
            'submissionId' => $usageEvent->submission?->getId(),
            'representationId' => $usageEvent->representation?->getId(),
            'submissionFileId' => $usageEvent->submissionFile?->getId(),
            'fileType' => $usageEvent->submissionFile ? StatisticsHelper::getDocumentType($usageEvent->submissionFile->getData('mimetype')) : null,
            'country' => $country,
            'region' => $region,
            'city' => $city,
            'institutionIds' => $institutionIds,
            'version' => $usageEvent->version
        ];
        // get application specific IDs
        if (Application::get()->getName() == 'ojs2') {
            $usageEventLogEntry['issueId'] = $usageEvent->issue?->getId();
            $usageEventLogEntry['issueGalleyId'] = $usageEvent->issueGalley?->getId();
        } elseif (Application::get()->getName() == 'omp') {
            $usageEventLogEntry['chapterId'] = $usageEvent->chapter?->getId();
            $usageEventLogEntry['seriesId'] = $usageEvent->series?->getId();
        }
        return $usageEventLogEntry;
    }

    /**
     * Get current day usage event log file name.
     */
    protected function getUsageEventLogFileName(): string
    {
        $usageEventLogsDir = StatisticsHelper::getUsageStatsDirPath() . '/usageEventLogs';
        if (!file_exists($usageEventLogsDir) || !is_dir($usageEventLogsDir)) {
            $fileMgr = new PrivateFileManager();
            $success = $fileMgr->mkdirtree($usageEventLogsDir);
            if (!$success) {
                // Files directory wrong configuration?
                error_log("UsageEventLog: Couldn't create {$usageEventLogsDir}.");
            }
        }
        return $usageEventLogsDir . '/usage_events_' . date('Ymd') . '.log';
    }

    /**
     * Get salt (used for IP hashing) from the file.
     * For privacy reasons, the salt will change every day.
     * When salt changes, the IP hash will change too, so the cach containing the hashed IPs (for Geo and institutions data) will be removed.
     * If the salt file does not exist, it will be created.
     * If the salt file exists and it was not modified at the current day the salt will be changed.
     */
    protected function getSalt(string $logFileName): array
    {
        $salt = null;
        $flushCache = false;
        $saltFileName = StatisticsHelper::getSaltFileName();
        // Create salt file and salt for the first time
        if (!file_exists($saltFileName)) {
            $salt = $this->createNewSalt($saltFileName);
            // Salt changed, flush the cache
            $flushCache = true;
        }
        $currentDate = date('Ymd');
        $saltFileLastModified = date('Ymd', filemtime($saltFileName));
        // Create new salt if the usage log file with current date does not exist.
        // The current usage log file will be created next (s. function handle() and logUsageEvent() above).
        // If another process accesses this before the current usage event log file is created,
        // consider the last modified date stamp of the salt file too.
        if (!file_exists($logFileName) && ($currentDate != $saltFileLastModified)) {
            $salt = $this->createNewSalt($saltFileName);
            // Salt changed, flush the cache
            $flushCache = true;
        }

        if (!isset($salt)) {
            $salt = trim(file_get_contents($saltFileName));
        }

        return [$salt, $flushCache];
    }

    /**
     * Create a new salt, write it to the salt file and return it
     */
    protected function createNewSalt(string $saltFileName): string
    {
        if (function_exists('mcrypt_create_iv')) {
            $newSalt = bin2hex(mcrypt_create_iv(16, MCRYPT_DEV_URANDOM | MCRYPT_RAND));
        } elseif (function_exists('openssl_random_pseudo_bytes')) {
            $newSalt = bin2hex(openssl_random_pseudo_bytes(16, $cstrong));
        } elseif (file_exists('/dev/urandom')) {
            $newSalt = bin2hex(file_get_contents('/dev/urandom', false, null, 0, 16));
        } else {
            $newSalt = mt_rand();
        }
        file_put_contents($saltFileName, $newSalt, LOCK_EX);
        return $newSalt;
    }
}
