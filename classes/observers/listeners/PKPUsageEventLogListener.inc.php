<?php

/**
 * @file classes/observers/listeners/PKPUsageEventLogListener.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPUsageEventLogListener
 * @ingroup observers_listeners
 *
 * @brief Listener listening for the usage events.
 */

namespace PKP\observers\listeners;

use APP\core\Application;
use APP\facades\Repo;
use APP\observers\events\UsageEvent;
use APP\statistics\StatisticsHelper;
use GeoIp2\Database\Reader;
use PKP\cache\CacheManager;
use PKP\cache\FileCache;
use PKP\file\PrivateFileManager;
use Sokil\IsoCodes\IsoCodesFactory;

class PKPUsageEventLogListener
{
    public FileCache $geoDataCache;

    /**
     * Handle the event.
     */
    public function handle(UsageEvent $usageEvent): void
    {
        $usageEventArray = $this->prepareUsageEvent($usageEvent);
        $this->logUsageEvent($usageEventArray);
    }

    /**
     * Log the usage event
     */
    public function logUsageEvent(array $usageEventArray): void
    {
        $usageEventLogEntry = json_encode($usageEventArray) . PHP_EOL;

        // Log file name (from the current day)
        $logFileName = $this->getUsageEventLogFileName();

        // Write the event to the log file
        // Keep the locking in order not to care about the filesystems's block sizes and if the file is on a local filesystem
        $fp = fopen($logFileName, 'a+b');
        if (flock($fp, LOCK_EX)) {
            fwrite($fp, $usageEventLogEntry);
            flock($fp, LOCK_UN);
        } else {
            // Couldn't lock the file.
            assert(false);
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
        $usageEventArray = (array) $usageEvent;

        // The current usage event log file name (from the current day)
        $logFileName = $this->getUsageEventLogFileName();

        // Salt management.
        $salt = null;
        $flushCache = false;
        $saltFileName = $this->getSaltFileName();
        // Create salt file and salt for the first time
        if (!file_exists($saltFileName)) {
            $salt = $this->_createNewSalt($saltFileName);
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
            $salt = $this->_createNewSalt($saltFileName);
            // Salt changed, flush the cache
            $flushCache = true;
        }

        if (!isset($salt)) {
            $salt = trim(file_get_contents($saltFileName));
        }

        // Hash the IP
        $ip = $usageEventArray['ip'];
        $hashedIp = $this->_hashIp($ip, $salt);
        // Never store unhashed IPs!
        if ($hashedIp === null) {
            assert(false);
        }
        $usageEventArray['ip'] = $hashedIp;

        $site = Application::get()->getRequest()->getSite();
        $context = Application::get()->getRequest()->getContext();
        $enableGeoUsageStats = $site->getData('enableGeoUsageStats');
        if (($enableGeoUsageStats > 0) && ($context->getData('enableGeoUsageStats') !== null) && ($context->getData('enableGeoUsageStats') < $site->getData('enableGeoUsageStats'))) {
            $enableGeoUsageStats = $context->getData('enableGeoUsageStats');
        }
        // Geo data
        $usageEventArray['country'] = $usageEventArray['region'] = $usageEventArray['city'] = null;
        if ($enableGeoUsageStats > 0) {
            $geoIPArray = $this->_getCachedIPLocation($ip, $hashedIp, $flushCache);
            $usageEventArray['country'] = $geoIPArray['country'];
            if ($enableGeoUsageStats > 1) {
                $usageEventArray['region'] = $geoIPArray['region'];
                if ($enableGeoUsageStats == 3) {
                    $usageEventArray['city'] = $geoIPArray['city'];
                }
            }
        }

        // institutions IDs
        $enableInstitutionUsageStats = $site->getData('enableInstitutionUsageStats');
        if ($enableInstitutionUsageStats && ($context->getData('enableInstitutionUsageStats') !== null) && !$context->getData('enableInstitutionUsageStats')) {
            $enableInstitutionUsageStats = $context->getData('enableInstitutionUsageStats');
        }
        $usageEventArray['institutionIds'] = [];
        if ($enableInstitutionUsageStats) {
            $institutionIds = Repo::institution()->getIdsByIP($ip, $usageEventArray['contextId'])->toArray();
            $usageEventArray['institutionIds'] = $institutionIds;
        }
        return $usageEventArray;
    }

    /**
     * Get the path to the Geo DB file.
     */
    public function getGeoDBPath(): string
    {
        return StatisticsHelper::getUsageStatsDirPath() . '/IPGeoDB.mmdb';
    }

    /**
     * Get the path to the salt file.
     */
    public function getSaltFileName(): string
    {
        return StatisticsHelper::getUsageStatsDirPath() . '/salt';
    }

    /**
     * Get current day usage event log file name.
     */
    public function getUsageEventLogFileName(): string
    {
        $usageEventLogsDir = StatisticsHelper::getUsageStatsDirPath() . '/usageEventLogs';
        if (!file_exists($usageEventLogsDir) || !is_dir($usageEventLogsDir)) {
            $fileMgr = new PrivateFileManager();
            $success = $fileMgr->mkdirtree($usageEventLogsDir);
            if (!$success) {
                // Files directory wrong configuration?
                assert(false);
            }
        }
        return $usageEventLogsDir . '/usage_events_' . date('Ymd') . '.log';
    }

    /**
     * Create a new salt, write it to the salt file and return it
     */
    public function _createNewSalt(string $saltFileName): string
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
    public function _hashIp(string $ip, string $salt): ?string
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
     * Get cached IP location.
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
    public function _getCachedIPLocation(string $ip, string $hashedIp, bool $flush): array
    {
        if (!isset($this->geoDataCache)) {
            $geoCacheManager = CacheManager::getManager();
            $this->geoDataCache = $geoCacheManager->getCache('geoIP', 'all', [&$this, 'geoDataCacheMiss']);
        }

        if ($flush) {
            // Salt and thus hashed IPs changed, empty the cache.
            $this->geoDataCacheMiss($this->geoDataCache, 'ID');
        }

        $cachedGeoData = $this->geoDataCache->getContents();
        if (!array_key_exists($hashedIp, $cachedGeoData)) {
            $reader = $countryIsoCode = $regionIsoCode = $cityName = null;
            try {
                $reader = new Reader($this->getGeoDBPath());
            } catch (\MaxMind\Db\Reader\InvalidDatabaseException $e) {
                error_log('MaxMind DB Reader InvalidDatabaseException: the Geo database is corrupt or invalid');
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
                    error_log('BadMethodCallException: city method cannot be used to open this Geo database');
                } catch (\GeoIp2\Exception\AddressNotFoundException $e) {
                    error_log('GeoIp2 AddressNotFoundException: the IP address is not in the Geo database');
                } catch (\MaxMind\Db\Reader\InvalidDatabaseException $e) {
                    error_log('MaxMind DB Reader InvalidDatabaseException: the Geo database is corrupt or invalid');
                }
            }
            $cachedGeoData[$hashedIp]['country'] = $countryIsoCode;
            $cachedGeoData[$hashedIp]['region'] = $regionIsoCode;
            $cachedGeoData[$hashedIp]['city'] = $cityName;
            $this->geoDataCache->setEntireCache($cachedGeoData);
        }
        return $cachedGeoData[$hashedIp];
    }

    /**
    * Geo cache miss callback.
    */
    public function geoDataCacheMiss(FileCache $cache): array
    {
        $geoData = [];
        $cache->setEntireCache($geoData);
        return $geoData;
    }
}
