<?php

/**
 * @file classes/site/VersionCheck.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class VersionCheck
 * @ingroup site
 *
 * @see Version
 *
 * @brief Provides methods to check for the latest version of OJS.
 */

namespace PKP\site;

use APP\core\Application;
use DateTime;
use Exception;
use Illuminate\Support\Facades\Cache;
use PKP\config\Config;
use PKP\core\PKPString;
use PKP\db\DAORegistry;
use PKP\file\FileManager;
use SimpleXMLElement;

class VersionCheck
{
    public const VERSION_CODE_PATH = 'dbscripts/xml/version.xml';

    /**
     * Return information about the latest available version.
     *
     * @return array
     */
    public static function getLatestVersion()
    {
        $application = Application::get();
        $includeId = Application::isInstalled() &&
            !Application::isUpgrading() &&
            Config::getVar('general', 'enable_beacon', true);

        if ($includeId) {
            $pluginSettingsDao = & DAORegistry::getDAO('PluginSettingsDAO');
            $uniqueSiteId = $pluginSettingsDao->getSetting(\PKP\core\PKPApplication::CONTEXT_SITE, 'UsageEventPlugin', 'uniqueSiteId');
        } else {
            $uniqueSiteId = null;
        }

        $request = $application->getRequest();
        return self::parseVersionXML(
            $application->getVersionDescriptorUrl() .
            ($includeId ? '?id=' . urlencode($uniqueSiteId) .
                '&oai=' . urlencode($request->url('index', 'oai'))
            : '')
        );
    }

    /**
     * Return the currently installed database version.
     *
     * @return Version
     */
    public static function getCurrentDBVersion()
    {
        $versionDao = DAORegistry::getDAO('VersionDAO'); /** @var VersionDAO $versionDao */
        return $versionDao->getCurrentVersion();
    }

    /**
     * Return the current code version.
     */
    public static function getCurrentCodeVersion(): ?Version
    {
        return self::parseVersionXML(self::VERSION_CODE_PATH)['version'] ?? null;
    }

    /**
     * Parse information from a version XML file.
     */
    public static function parseVersionXML(string $path): ?array
    {
        $key = __METHOD__ . $path;
        $cache = Cache::get($key);
        if (!is_array($cache) || FileManager::isVirtualPath($path) || filemtime($path) > ($cache['createdAt'] ?? new DateTime())->getTimestamp()) {
            $xml = new SimpleXMLElement(FileManager::getStream($path));
            $version = [];
            foreach (['application', 'class', 'type', 'release', 'tag', 'date', 'info', 'package', 'lazy-load', 'sitewide'] as $name) {
                if (isset($xml->$name)) {
                    $version[$name] = (string) $xml->$name;
                }
            }
            $version['sitewide'] = (int) ($version['sitewide'] ?? 0);
            $version['lazy-load'] = (int) ($version['lazy-load'] ?? 0);
            if (isset($xml->patch)) {
                $version['patch'] = [];
                foreach ($xml->patch as $patch) {
                    $version['patch'][$patch['from']] = (string) $patch;
                }
            }
            $cache = [
                'createdAt' => new DateTime(),
                'data' => $version
            ];
            Cache::put($key, $cache);
        }
        $version = $cache['data'];

        // Built outside of the cache to avoid serializing the Version (which would need a __set_state implementation)
        if (isset($version['release']) && isset($version['application'])) {
            $version['version'] = Version::fromString(
                $version['release'] ?? '',
                $version['type'] ?? '',
                $version['application'] ?? '',
                $version['class'] ?? '',
                $version['lazy-load'],
                $version['sitewide']
            );
        }
        return $version;
    }

    /**
     * Find the applicable patch for the current code version (if available).
     *
     * @param array $versionInfo as returned by parseVersionXML()
     * @param Version $codeVersion as returned by getCurrentCodeVersion()
     *
     * @return string
     */
    public static function getPatch($versionInfo, $codeVersion = null)
    {
        if (!isset($codeVersion)) {
            $codeVersion = self::getCurrentCodeVersion();
        }
        if (isset($versionInfo['patch'][$codeVersion->getVersionString()])) {
            return $versionInfo['patch'][$codeVersion->getVersionString()];
        }
        return null;
    }

    /**
     * Checks whether the given version file exists and whether it
     * contains valid data. Returns a Version object if everything
     * is ok, otherwise throws an Exception.
     *
     * @param string $versionFile
     *
     * @return Version
     */
    public static function getValidPluginVersionInfo($versionFile)
    {
        $fileManager = new FileManager();
        if ($fileManager->fileExists($versionFile)) {
            $versionInfo = self::parseVersionXML($versionFile);
        } else {
            throw new Exception(__('manager.plugins.versionFileNotFound'));
        }

        // Validate plugin name and type to avoid abuse
        $productType = explode('.', $versionInfo['type']);
        if (count($productType) != 2 || $productType[0] != 'plugins') {
            throw new Exception(__('manager.plugins.versionFileInvalid'));
        }

        $pluginVersion = $versionInfo['version'];
        $namesToValidate = [$pluginVersion->getProduct(), $productType[1]];
        foreach ($namesToValidate as $nameToValidate) {
            if (!PKPString::regexp_match('/[a-z][a-zA-Z0-9]+/', $nameToValidate)) {
                throw new Exception(__('manager.plugins.versionFileInvalid'));
            }
        }

        return $pluginVersion;
    }

    /**
     * Checks the application's version against the latest version
     * on the PKP servers.
     *
     * @return string|false Version description or false if no newer version
     */
    public static function checkIfNewVersionExists()
    {
        $versionInfo = self::getLatestVersion();
        $latestVersion = $versionInfo['release'];

        $currentVersion = self::getCurrentDBVersion();
        if ($currentVersion->compare($latestVersion) < 0) {
            return $latestVersion;
        }
        return false;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\site\VersionCheck', '\VersionCheck');
    define('VERSION_CODE_PATH', \VersionCheck::VERSION_CODE_PATH);
}
