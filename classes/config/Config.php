<?php

/**
 * @defgroup config Config
 * Implements configuration concerns such as the configuration file parser.
 */

/**
 * @file classes/config/Config.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Config
 *
 * @ingroup config
 *
 * @brief Config class for accessing configuration parameters.
 */

namespace PKP\config;

use Exception;
use PKP\core\Registry;

/** The path to the default configuration file */
define('CONFIG_FILE', \PKP\core\Core::getBaseDir() . '/config.inc.php');

class Config
{
    /**
     * The sensitive data from the config files in the formate of `section` to `keys` mapping as
     * [
     *   'section1' => ['key1', 'key2', ...],
     *   'section2' => ['key1', 'key2', ...],
     * ]
     */
    public const SENSITIVE_DATA = [
        'general' => [
            'app_key',
        ],
        'database' => [
            'password',
        ],
        'email' => [
            'smtp_password',
            'smtp_username',
        ],
        'security' => [
            'api_key_secret',
            'salt',
        ],
        'captcha' => [
            'recaptcha_private_key',
        ],
    ];

    /**
     * Check and determine if the given section key is sensitive data or not
     */
    public static function isSensitive(string $section, string $key): bool
    {
        if (!isset(static::SENSITIVE_DATA[$section])) {
            return false;
        }

        return in_array($key, static::SENSITIVE_DATA[$section]);
    }

    /**
     * Retrieve a specified configuration variable.
     *
     * @param string $section
     * @param string $key
     * @param mixed $default Optional default if the var doesn't exist
     *
     * @return mixed May return boolean (in case of "off"/"on"/etc), numeric, string, or null.
     */
    public static function getVar($section, $key, $default = null)
    {
        $configData = & Config::getData();
        return $configData[$section][$key] ?? $default;
    }

    /**
     * Get the current configuration data.
     *
     * @return array the configuration data
     */
    public static function &getData()
    {
        $configData = & Registry::get('configData', true, null);

        if ($configData === null) {
            // Load configuration data only once per request, implicitly
            // sets config data by ref in the registry.
            $configData = Config::reloadData();
        }

        return $configData;
    }

    /**
     * Load configuration data from a file.
     * The file is assumed to be formatted in php.ini style.
     *
     * @return array the configuration data
     */
    public static function &reloadData()
    {
        if (($configData = & ConfigParser::readConfig(Config::getConfigFileName())) === false) {
            throw new Exception(sprintf('Cannot read configuration file %s', Config::getConfigFileName()));
        }

        return $configData;
    }

    /**
     * Reset the config data in registry
     */
    public static function resetData()
    {
        Registry::set('configData', static::reloadData());
    }

    /**
     * Set the path to the configuration file.
     *
     * @param string $configFile
     */
    public static function setConfigFileName($configFile)
    {
        // Reset the config data
        $configData = null;
        Registry::set('configData', $configData);

        // Set the config file
        Registry::set('configFile', $configFile);
    }

    /**
     * Return the path to the configuration file.
     *
     * @return string
     */
    public static function getConfigFileName()
    {
        return Registry::get('configFile', true, CONFIG_FILE);
    }

    /**
     * Get context base urls from config file.
     *
     * @return array Empty array if none is set.
     */
    public static function &getContextBaseUrls()
    {
        $contextBaseUrls = & Registry::get('contextBaseUrls'); // Reference required.

        if (is_null($contextBaseUrls)) {
            $contextBaseUrls = [];
            $configData = self::getData();
            // Filter the settings.
            $matches = null;
            foreach ($configData['general'] as $settingName => $settingValue) {
                if (preg_match('/base_url\[(.*)\]/', $settingName, $matches)) {
                    $workingContextPath = $matches[1];
                    $contextBaseUrls[$workingContextPath] = $settingValue;
                }
            }
        }

        return $contextBaseUrls;
    }

    /**
     * Retrieve whether the specified configuration variable is defined, even if it's null.
     *
     * @return bool
     */
    public static function hasVar(string $section, string $key): bool
    {
        return array_key_exists($key, Config::getData()[$section] ?? []);
    }
}
