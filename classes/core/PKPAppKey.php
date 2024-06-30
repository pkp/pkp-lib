<?php

/**
 * @file classes/core/PKPAppKey.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPAppKey
 *
 * @brief Class to manage app key related behaviours
 */

namespace PKP\core;

use Exception;
use PKP\config\Config;
use PKP\config\ConfigParser;
use Illuminate\Support\Str;
use Illuminate\Encryption\Encrypter;

class PKPAppKey
{
    /**
     * The supported cipher algorithms and their properties.
     *
     * @var array
     */
    public const SUPPORTED_CIPHERS = [
        'aes-128-cbc' => ['size' => 16, 'aead' => false],
        'aes-256-cbc' => ['size' => 32, 'aead' => false],
        'aes-128-gcm' => ['size' => 16, 'aead' => true],
        'aes-256-gcm' => ['size' => 32, 'aead' => true],
    ];

    /**
     * The default cipher algorithms
     *
     * @var string
     */
    public const DEFAULT_CIPHER = 'aes-256-cbc';

    /**
     * The `app_key` variable with description to write in the `config.inc.php` file
     *
     * @var string
     */
    public const APP_KEY_VARIABLE_CONTENT = <<<CONTENT
    ; An application specific key that is required for the app to run
    ; Internally this is used for any encryption (specifically cookie encryption if enabled)
    app_key = 

    CONTENT;

    /**
     * Get the defined cipher
     */
    public static function getCipher(): string
    {
        return Config::getVar('security', 'cipher', self::DEFAULT_CIPHER);
    }

    /**
     * Is the app key defined in config file
     */
    public static function hasKey(): bool
    {
        return !empty(Config::getVar('general', 'app_key'));
    }

    /**
     * Has the app key variable defined in config file
     */
    public static function hasKeyVariable(): bool
    {
        return Config::hasVar('general', 'app_key');
    }

    /**
     * Get the app key defined in config file
     */
    public static function getKey(): ?string
    {
        return Config::getVar('general', 'app_key');
    }

    /**
     * Validate a given cipher
     */
    public static function validateCipher(string $cipher): string
    {
        $cipher = strtolower($cipher);

        if (!in_array($cipher, array_keys(self::SUPPORTED_CIPHERS))) {
            $ciphers = implode(', ', array_keys(self::SUPPORTED_CIPHERS));
            
            throw new Exception(
                sprintf(
                    'Invalid cipher %s provided, must be among [%s]',
                    $cipher,
                    $ciphers
                )
            );
        }

        return $cipher;
    }

    /**
     * Validate given or config defined app key
     */
    public static function validate(string $key = null, string $cipher = null): bool
    {
        $config = app('config')->get('app');

        return Encrypter::supported(
            static::parseKey($key ?? $config['key']),
            static::validateCipher($cipher ?? $config['cipher'])
        );
    }

    /**
     * Generate a new app key
     */
    public static function generate(string $cipher = null): string
    {
        $config = app('config')->get('app');

        return 'base64:'.base64_encode(
            Encrypter::generateKey(static::validateCipher($cipher ?? $config['cipher']))
        );
    }

    /**
     * Write the `app_key` variable in the `config.inc.php` file
     *
     * @throws \Exception
     */
    public static function writeAppKeyVariableToConfig(): bool
    {
        $instruction = __('installer.appKey.keyVariable.missing.instruction');

        if (static::hasKeyVariable()) {
            throw new Exception(__('installer.appKey.keyVariable.alreadyExist.notice'));
        }

        $configFile = Config::getConfigFileName();

        if (!file_exists($configFile) || !is_readable($configFile)) {
            throw new Exception(__('installer.appKey.keyVariable.configFile.read.error') . $instruction);
        }

        $configContent = file_get_contents($configFile);
        $splits = explode("[general]\n", $configContent);

        if (count($splits) > 2) {
            throw new Exception(__('installer.appKey.keyVariable.configFile.parse.error') . $instruction);
        }

        $configContent = $splits[0] . "[general]\n\n" . self::APP_KEY_VARIABLE_CONTENT . "\n" . $splits[1];
        $configParser = (new ConfigParser)->setFileContent($configContent);

        if (!$configParser->writeConfig(Config::getConfigFileName())) {
            throw new Exception(__('installer.appKey.keyVariable.configFile.write.error') . $instruction);
        }

        // Need to reset the config data in registry 
        // This is to make sure this change is available on running request lift cycle
        Config::setConfigFileName(CONFIG_FILE);

        return true;
    }

    /**
     * Write the given app key in the config file
     * 
     * @throws \Exception
     */
    public static function writeAppKeyToConfig(string $key): bool
    {
        if (!static::validate($key)) {
            $ciphers = implode(', ', array_keys(self::SUPPORTED_CIPHERS));
            
            // Error invalid app key
            throw new Exception(__('installer.appKey.validate.error', [
                'ciphers' => $ciphers
            ]));
        }

        $configParser = new ConfigParser;
        $configParams = [
            'general' => [
                'app_key' => $key,
            ],
        ];

        if (!static::hasKeyVariable()) {
            // Error if the config key `app_key` not defined under `general` section
            throw new Exception(
                __('installer.appKey.keyVariable.missing.error') .
                __('installer.appKey.keyVariable.missing.instruction') .
                __('installer.appKey.set.value', ['key' => $key])
            );
        }

        if (!$configParser->updateConfig(Config::getConfigFileName(), $configParams)) {
            // Error reading config file
            throw new Exception(
                __('installer.appKey.keyVariable.configFile.read.error') .
                __('installer.appKey.set.value', ['key' => $key])
            );
        }

        if (!$configParser->writeConfig(Config::getConfigFileName())) {
            // Error writing config file
            throw new Exception(
                __('installer.appKey.write.error') .
                __('installer.appKey.set.value', ['key' => $key])
            );
        }

        return true;
    }

    /**
     * Parse the given app key and return the real key value
     */
    public static function parseKey(string $key): string
    {
        if (Str::startsWith($key, $prefix = 'base64:')) {
            $key = base64_decode(Str::after($key, $prefix));
        }

        return $key;
    }
}
