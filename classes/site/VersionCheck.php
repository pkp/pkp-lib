<?php

/**
 * @file classes/site/VersionCheck.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class VersionCheck
 *
 * @see Version
 *
 * @brief Provides methods to check for the latest version of the application.
 */

namespace PKP\site;

use APP\core\Application;
use DateInterval;
use Exception;
use Illuminate\Support\Facades\Cache;
use PKP\config\Config;
use PKP\db\DAORegistry;
use PKP\file\FileManager;
use SimpleXMLElement;

class VersionCheck
{
    /** Max lifetime for the version cache */
    protected const MAX_CACHE_LIFETIME = '1 year';

    public const VERSION_CODE_PATH = 'dbscripts/xml/version.xml';

    /**
     * Return information about the latest available version.
     */
    public static function getLatestVersion(): array
    {
        $application = Application::get();
        $includeId = Application::isInstalled() &&
            !Application::isUpgrading() &&
            Config::getVar('general', 'enable_beacon', true);

        if ($includeId) {
            $uniqueSiteId = Application::get()->getUUID();
        } else {
            $uniqueSiteId = null;
        }

        $request = $application->getRequest();
        return self::parseVersionXML(
            $application->getVersionDescriptorUrl() .
            ($includeId ? '?id=' . urlencode($uniqueSiteId) .
                '&oai=' . urlencode($request->url(Application::SITE_CONTEXT_PATH, 'oai'))
            : '')
        );
    }

    /**
     * Return the currently installed database version.
     */
    public static function getCurrentDBVersion(): Version
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
        $isVirtual = FileManager::isVirtualPath($path);
        $key = __METHOD__ . static::MAX_CACHE_LIFETIME . $path . ($isVirtual ? '' : filemtime($path));
        $expiration = $isVirtual ? 0 : DateInterval::createFromDateString(static::MAX_CACHE_LIFETIME);
        $version = Cache::remember($key, $expiration, function () use ($path) {
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
            return $version;
        });

        // Built outside of the cache section to avoid serializing the Version (which would need a __set_state implementation)
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
     * Checks whether the given version file exists and whether it
     * contains valid data. Returns a Version object if everything
     * is ok, otherwise throws an Exception.
     *
     */
    public static function getValidPluginVersionInfo($versionFile): Version
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
            if (!preg_match('/[a-z][a-zA-Z0-9]+/', $nameToValidate)) {
                throw new Exception(__('manager.plugins.versionFileInvalid'));
            }
        }

        return $pluginVersion;
    }

    /**
     * Checks the application's version against the latest version
     * on the PKP servers.
     *
     * @return Version description or false if no newer version
     */
    public static function checkIfNewVersionExists(): bool|string
    {
        try {
            $versionInfo = self::getLatestVersion();
        } catch (\GuzzleHttp\Exception\TransferException $e) {
            error_log('Failed to retrieve the latest version info: ' . $e->getMessage());
            return false;
        }
        $latestVersion = $versionInfo['release'];

        $currentVersion = self::getCurrentDBVersion();
        if ($currentVersion->compare($latestVersion) < 0) {
            return $latestVersion;
        }
        return false;
    }
}
