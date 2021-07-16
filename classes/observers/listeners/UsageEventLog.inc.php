<?php

/**
 * @file classes/observers/listeners/UsageEventLog.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UsageEventLog
 * @ingroup observers_traits
 *
 * @brief Listener listening for and logging the usage events.
 */

namespace PKP\observers\listeners;

use APP\core\Application;
use APP\facades\Repo;
use APP\observers\events\Usage;
use APP\statistics\StatisticsHelper;
use APP\submission\Submission;
use GeoIp2\Database\Reader;
use Illuminate\Events\Dispatcher;
use PKP\cache\CacheManager;
use PKP\cache\FileCache;
use PKP\context\Context;
use PKP\file\PrivateFileManager;
use PKP\plugins\HookRegistry;
use PKP\site\Site;
use Sokil\IsoCodes\IsoCodesFactory;

class UsageEventLog
{
    public FileCache $geoDataCache;
    public FileCache $institutionDataCache;

    /**
     * Maps methods with correspondent events to listen
     */
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(
            Usage::class,
            self::class . '@handle'
        );
    }

    /**
     * Handle the event.
     */
    public function handle(Usage $usageEvent): void
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
    protected function canHandle(Usage $usageEvent): bool
    {
        if ($usageEvent->request->isDNTSet()) {
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
        HookRegistry::call('Stats::logUsageEvent', [$usageEventLogEntry]);

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
    protected function prepareUsageEvent(Usage $usageEvent): array
    {
        $request = $usageEvent->request;

        // The current usage event log file name (from the current day)
        $logFileName = $this->getUsageEventLogFileName();

        // Salt management.
        [$salt, $flushCache] = $this->getSalt($logFileName);

        // Hash the IP
        $ip = $request->getRemoteAddr();
        $hashedIp = $this->hashIp($ip, $salt);
        if ($hashedIp === null) {
            error_log('UsageEventLog: the IP cound not be hashed.');
        }

        $site = $request->getSite();
        $context = $usageEvent->context;

        // Geo data
        [$country, $region, $city] = $this->getGeoData($site, $context, $ip, $hashedIp, $flushCache);

        // institutions IDs
        $institutionIds = [];
        if ($context->isInstitutionStatsEnabled($site)) {
            $institutionIds = $this->getInstitutionIds($context->getId(), $ip, $hashedIp, $flushCache);
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
            'fileType' => $usageEvent->submissionFile ? $this->getDocumentType($usageEvent->submissionFile->getData('mimetype')) : null,
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
     * Get the path to the Geo DB file.
     */
    protected function getGeoDBPath(): string
    {
        return StatisticsHelper::getUsageStatsDirPath() . '/IPGeoDB.mmdb';
    }

    /**
     * Get the path to the salt file.
     */
    protected function getSaltFileName(): string
    {
        return StatisticsHelper::getUsageStatsDirPath() . '/salt';
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
     * Get salt
     */
    protected function getSalt(string $logFileName): array
    {
        $salt = null;
        $flushCache = false;
        $saltFileName = $this->getSaltFileName();
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

    /**
    * Hash (SHA256) the given IP using the given SALT.
    *
    * NB: This implementation was taken from OA-S directly. See
    * http://sourceforge.net/p/openaccessstati/code-0/3/tree/trunk/logfile-parser/lib/logutils.php
    * We just do not implement the PHP4 part as OJS dropped PHP4 support.
    *
    */
    protected function hashIp(string $ip, string $salt): ?string
    {
        $hashedIp = null;
        if (function_exists('mhash')) {
            $hashedIp = bin2hex(mhash(MHASH_SHA256, $ip . $salt));
        } else {
            assert(function_exists('hash'));
            if (function_exists('hash')) {
                $hashedIp = hash('sha256', $ip . $salt);
            }
        }
        return $hashedIp;
    }

    /**
     * Get institution IDs for a given context based on the IP, use cache if exists.
     *
     * @param string $contextId Context ID
     * @param string $ip User IP
     * @param string $hashedIp Hashed user IP
     * @param bool $flush If true empty cache
     *
     * @return array Cached Geo data
     *  [
     *   hashedIP => contextId => institutionIds[]
     *  ]
     *
     */
    protected function getInstitutionIds(int $contextId, string $ip, string $hashedIp, bool $flush): array
    {
        if (!isset($this->institutionDataCache)) {
            $institutionCacheManager = CacheManager::getManager();
            $this->institutionDataCache = $institutionCacheManager->getCache('institutionIP', 'all', [&$this, 'institutionDataCacheMiss']);
        }

        if ($flush) {
            // Salt and thus hashed IPs changed, empty the cache.
            $this->institutionDataCacheMiss($this->institutionDataCache, 'ID');
        }

        $cachedInstitutionData = $this->institutionDataCache->getContents();
        if (array_key_exists($hashedIp, $cachedInstitutionData) && array_key_exists($contextId, $cachedInstitutionData[$hashedIp])) {
            return $cachedInstitutionData[$hashedIp][$contextId];
        }
        $institutionIds = Repo::institution()->getIds(Repo::institution()->getCollector()->filterByContextIds([$contextId])->filterByIps([$ip]))->toArray();
        $cachedInstitutionData[$hashedIp][$contextId] = $institutionIds;
        $this->institutionDataCache->setEntireCache($cachedInstitutionData);
        return $cachedInstitutionData[$hashedIp][$contextId];
    }

    /**
    * Institution cache miss callback.
    */
    public function institutionDataCacheMiss(FileCache $cache): array
    {
        $cache->setEntireCache([]);
        return [];
    }

    /**
     * Retrieve Geo data (country, region, city) using IP and based on the site i.e. context settings
     */
    protected function getGeoData(Site $site, Context $context, string $ip, string $hashedIp, bool $flushCache): array
    {
        $enableGeoUsageStats = $site->getData('enableGeoUsageStats');
        if (($enableGeoUsageStats != 'disabled') && ($context->getData('enableGeoUsageStats') !== null) && ($context->getData('enableGeoUsageStats') != $site->getData('enableGeoUsageStats'))) {
            $enableGeoUsageStats = $context->getData('enableGeoUsageStats');
        }

        $country = $region = $city = null;
        if ($enableGeoUsageStats != 'disabled') {
            $geoIPArray = $this->getLocation($ip, $hashedIp, $flushCache);
            $country = $geoIPArray['country'];
            if ($enableGeoUsageStats == 'country+region+city' || $enableGeoUsageStats == 'country+region') {
                $region = $geoIPArray['region'];
                if ($enableGeoUsageStats == 'country+region+city') {
                    $city = $geoIPArray['city'];
                }
            }
        }
        return [$country, $region, $city];
    }

    /**
     * Get location based on the IP, use cache if exists.
     *
     * @param string $ip User IP
     * @param string $hashedIp Hashed user IP
     * @param bool $flush If true empty cache
     *
     * @return array Cached Geo data
     *  [
     *   hashedIP => [
     *    'country' => string Country ISO code,
     *    'region' => string Region ISO code
     *    'city' => string City name
     *   ]
     *  ]
     *
     */
    protected function getLocation(string $ip, string $hashedIp, bool $flush): array
    {
        $ip = '142.58.100.60';
        if (!isset($this->geoDataCache)) {
            $geoCacheManager = CacheManager::getManager();
            $this->geoDataCache = $geoCacheManager->getCache('geoIP', 'all', [&$this, 'geoDataCacheMiss']);
        }

        if ($flush) {
            // Salt and thus hashed IPs changed, empty the cache.
            $this->geoDataCacheMiss($this->geoDataCache, 'ID');
        }

        $cachedGeoData = $this->geoDataCache->getContents();
        if (array_key_exists($hashedIp, $cachedGeoData)) {
            return $cachedGeoData[$hashedIp];
        }

        $reader = $countryIsoCode = $regionIsoCode = $cityName = null;
        try {
            $reader = new Reader($this->getGeoDBPath());
        } catch (\MaxMind\Db\Reader\InvalidDatabaseException $e) {
            error_log('There was a problem reading the Geo database at ' . $this->getGeoDBPath() . '. Error: ' . $e->getMessage());
        }
        if (isset($reader)) {
            try {
                $geoIPRecord = $reader->city($ip);
                $countryIsoCode = $geoIPRecord->country->isoCode;
                // When found, up to three characters long subdivision portion of the ISO 3166-2 code is returned
                // s. https://github.com/maxmind/GeoIP2-php/blob/main/src/Record/Subdivision.php#L20
                $regionIsoCode = $geoIPRecord->mostSpecificSubdivision->isoCode;
                // DB-IP IP to City Lite database does not provide region Iso code but name,
                // thus try to get the region Iso code by the name,
                // but we need country for that
                if (!isset($regionIsoCode) && isset($countryIsoCode)) {
                    $regionName = $geoIPRecord->mostSpecificSubdivision->name;
                    if (isset($regionName)) {
                        $isoCodes = app(IsoCodesFactory::class);
                        $allCountryRegions = $isoCodes->getSubdivisions()->getAllByCountryCode($countryIsoCode);
                        foreach ($allCountryRegions as $countryRegion) {
                            if ($countryRegion->getName() == $regionName) {
                                $regionIsoCodeArray = explode('-', $countryRegion->getCode());
                                $regionIsoCode = $regionIsoCodeArray[1];
                                break;
                            }
                        }
                    }
                }
                $cityName = $geoIPRecord->city->name;
            } catch (\BadMethodCallException $e) {
                error_log('There was a problem using city method on the Geo database at ' . $this->getGeoDBPath() . '. Error: ' . $e->getMessage());
            } catch (\GeoIp2\Exception\AddressNotFoundException $e) {
                error_log('There was a problem finding IP in the Geo database at ' . $this->getGeoDBPath() . '. Error: ' . $e->getMessage());
            } catch (\MaxMind\Db\Reader\InvalidDatabaseException $e) {
                error_log('There was a problem reading the Geo database at ' . $this->getGeoDBPath() . '. Error: ' . $e->getMessage());
            }
        }
        $cachedGeoData[$hashedIp]['country'] = $countryIsoCode;
        $cachedGeoData[$hashedIp]['region'] = $regionIsoCode;
        $cachedGeoData[$hashedIp]['city'] = $cityName;
        $this->geoDataCache->setEntireCache($cachedGeoData);
        return $cachedGeoData[$hashedIp];
    }

    /**
    * Geo cache miss callback.
    */
    public function geoDataCacheMiss(FileCache $cache): array
    {
        $cache->setEntireCache([]);
        return [];
    }

    /**
    * Get document type based on the mimetype
    * The mimetypes considered here are subset of those used in PKPFileService::getDocumentType()
    *
    * @return int One of the StatisticsHelper::STATISTICS_FILE_TYPE_ constants
    */
    protected function getDocumentType(string $mimetype): int
    {
        switch ($mimetype) {
           case 'application/pdf':
           case 'application/x-pdf':
           case 'text/pdf':
           case 'text/x-pdf':
               return StatisticsHelper::STATISTICS_FILE_TYPE_PDF;
           case 'application/msword':
           case 'application/word':
           case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
               return StatisticsHelper::STATISTICS_FILE_TYPE_DOC;
           case 'text/html':
               return StatisticsHelper::STATISTICS_FILE_TYPE_HTML;
           default:
               return StatisticsHelper::STATISTICS_FILE_TYPE_OTHER;
       }
    }
}
