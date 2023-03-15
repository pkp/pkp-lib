<?php

/**
* @file classes/statistics/PKPStatisticsHelper.php
*
* Copyright (c) 2013-2021 Simon Fraser University
* Copyright (c) 2003-2021 John Willinsky
* Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
*
* @class PKPStatisticsHelper
* @ingroup statistics
*
* @brief Statistics helper class.
*
*/

namespace PKP\statistics;

use APP\facades\Repo;
use GeoIp2\Database\Reader;
use InvalidArgumentException;
use PKP\cache\CacheManager;
use PKP\cache\FileCache;
use PKP\context\Context;
use PKP\file\PrivateFileManager;
use PKP\site\Site;
use Sokil\IsoCodes\IsoCodesFactory;

abstract class PKPStatisticsHelper
{
    // Dimensions:
    // 1) publication object dimension:
    public const STATISTICS_DIMENSION_CONTEXT_ID = 'context_id';
    public const STATISTICS_DIMENSION_SUBMISSION_ID = 'submission_id';
    public const STATISTICS_DIMENSION_ASSOC_TYPE = 'assoc_type';
    public const STATISTICS_DIMENSION_FILE_TYPE = 'file_type';
    public const STATISTICS_DIMENSION_SUBMISSION_FILE_ID = 'submission_file_id';
    public const STATISTICS_DIMENSION_REPRESENTATION_ID = 'representation_id';

    // 2) time dimension:
    public const STATISTICS_DIMENSION_YEAR = 'year';
    public const STATISTICS_DIMENSION_MONTH = 'month';
    public const STATISTICS_DIMENSION_DAY = 'day'; // used as API parameter for timelines
    public const STATISTICS_DIMENSION_DATE = 'date';

    // 3) geography dimension:
    public const STATISTICS_DIMENSION_COUNTRY = 'country';
    public const STATISTICS_DIMENSION_REGION = 'region';
    public const STATISTICS_DIMENSION_CITY = 'city';

    // Metrics:
    public const STATISTICS_METRIC = 'metric';
    public const STATISTICS_METRIC_UNIQUE = 'metric_unique';

    // Ordering:
    public const STATISTICS_ORDER_ASC = 'ASC';
    public const STATISTICS_ORDER_DESC = 'DESC';

    // File type to be used in publication object dimension.
    public const STATISTICS_FILE_TYPE_HTML = 1;
    public const STATISTICS_FILE_TYPE_PDF = 2;
    public const STATISTICS_FILE_TYPE_OTHER = 3;
    public const STATISTICS_FILE_TYPE_DOC = 4;

    // Set the earliest date used
    public const STATISTICS_EARLIEST_DATE = '2001-01-01';

    /** These are rules defined by the COUNTER project.
     * See https://www.projectcounter.org/code-of-practice-five-sections/7-processing-rules-underlying-counter-reporting-data/#doubleclick
     */
    public const COUNTER_DOUBLE_CLICK_TIME_FILTER_SECONDS = 30;

    // geotraphy settings
    public const STATISTICS_SETTING_COUNTRY = 'country';
    public const STATISTICS_SETTING_REGION = 'country+region';
    public const STATISTICS_SETTING_CITY = 'country+region+city';

    public FileCache $geoDataCache;
    public FileCache $institutionDataCache;

    /**
     * Get the usage stats directory path.
     */
    public static function getUsageStatsDirPath(): string
    {
        $fileMgr = new PrivateFileManager();
        return realpath($fileMgr->getBasePath()) . '/usageStats';
    }

    /**
     * Get the path to the salt file.
     */
    public static function getSaltFileName(): string
    {
        return self::getUsageStatsDirPath() . '/salt';
    }

    /**
     * Get the path to the Geo DB file.
     */
    public static function getGeoDBPath(): string
    {
        return self::getUsageStatsDirPath() . '/IPGeoDB.mmdb';
    }

    /**
    * Get document type based on the mimetype
    * The mimetypes considered here are subset of those used in PKPFileService::getDocumentType()
    *
    * @return int One of the StatisticsHelper::STATISTICS_FILE_TYPE_ constants
    */
    public static function getDocumentType(string $mimetype): int
    {
        switch ($mimetype) {
            case 'application/pdf':
            case 'application/x-pdf':
            case 'text/pdf':
            case 'text/x-pdf':
                return self::STATISTICS_FILE_TYPE_PDF;
            case 'application/msword':
            case 'application/word':
            case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
                return self::STATISTICS_FILE_TYPE_DOC;
            case 'text/html':
                return self::STATISTICS_FILE_TYPE_HTML;
            default:
                return self::STATISTICS_FILE_TYPE_OTHER;
        }
    }

    /**
    * Hash (SHA256) the given IP using the given SALT.
    *
    * NB: This implementation was taken from OA-S directly. See
    * http://sourceforge.net/p/openaccessstati/code-0/3/tree/trunk/logfile-parser/lib/logutils.php
    * We just do not implement the PHP4 part as OJS dropped PHP4 support.
    *
    */
    public static function hashIp(string $ip, string $salt): ?string
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
      * Retrieve Geo data (country, region, city) using IP and based on the site i.e. context settings
      */
    public function getGeoData(Site $site, Context $context, string $ip, string $hashedIp, bool $flushCache = false): array
    {
        $country = $region = $city = null;
        $enableGeoUsageStats = $context->getEnableGeoUsageStats($site);
        if ($enableGeoUsageStats != 'disabled') {
            $geoIPArray = $this->getLocation($ip, $hashedIp, $flushCache);
            $country = $geoIPArray['country'];
            if ($enableGeoUsageStats == self::STATISTICS_SETTING_CITY || $enableGeoUsageStats == self::STATISTICS_SETTING_REGION) {
                $region = $geoIPArray['region'];
                if ($enableGeoUsageStats == self::STATISTICS_SETTING_CITY) {
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
    public function getLocation(string $ip, string $hashedIp, bool $flush = false): array
    {
        if (!isset($this->geoDataCache)) {
            $geoCacheManager = CacheManager::getManager();
            /** @var FileCache */
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
        } catch (InvalidArgumentException $e) {
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
    public function getInstitutionIds(int $contextId, string $ip, string $hashedIp, bool $flush = false): array
    {
        if (!isset($this->institutionDataCache)) {
            $institutionCacheManager = CacheManager::getManager();
            /** @var FileCache */
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
        $institutionIds = Repo::institution()->getCollector()
            ->filterByContextIds([$contextId])
            ->filterByIps([$ip])
            ->getIds()
            ->toArray();

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
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\statistics\PKPStatisticsHelper', '\PKPStatisticsHelper');
    foreach ([
        'STATISTICS_DIMENSION_CONTEXT_ID',
        'STATISTICS_DIMENSION_SUBMISSION_ID',
        'STATISTICS_DIMENSION_REPRESENTATION_ID',
        'STATISTICS_DIMENSION_ASSOC_TYPE',
        'STATISTICS_DIMENSION_FILE_TYPE',
        'STATISTICS_DIMENSION_YEAR',
        'STATISTICS_DIMENSION_MONTH',
        'STATISTICS_DIMENSION_DAY',
        'STATISTICS_DIMENSION_DATE',
        'STATISTICS_DIMENSION_COUNTRY',
        'STATISTICS_DIMENSION_REGION',
        'STATISTICS_DIMENSION_CITY',
        'STATISTICS_METRIC',
        'STATISTICS_METRIC_UNIQUE',
        'STATISTICS_ORDER_ASC',
        'STATISTICS_ORDER_DESC',
        'STATISTICS_FILE_TYPE_HTML',
        'STATISTICS_FILE_TYPE_PDF',
        'STATISTICS_FILE_TYPE_OTHER',
        'STATISTICS_FILE_TYPE_DOC',
        'STATISTICS_EARLIEST_DATE',
        'COUNTER_DOUBLE_CLICK_TIME_FILTER_SECONDS',
    ] as $constantName) {
        define($constantName, constant('\PKPStatisticsHelper::' . $constantName));
    }
}
