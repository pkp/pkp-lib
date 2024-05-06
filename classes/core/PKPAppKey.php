<?php

/**
 * @file classes/core/PKPAppKey.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPAppKey
 *
 * @ingroup core
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
    private static array $supportedCiphers = [
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
    private static string $defaultCipher = 'aes-256-cbc';

    /**
     * Get the list of supported ciphers
     */
    public static function getSupportedCiphers(): array
    {
        return self::$supportedCiphers;
    }

    /**
     * Get the defined cipher
     */
    public static function getCipher(): string
    {
        return Config::getVar('security', 'cipher', self::$defaultCipher);
    }

    /**
     * Has the app key defined in config file
     */
    public static function hasKey(): bool
    {
        return !empty(Config::getVar('general', 'app_key', ''));
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
    public static function getKey(): string
    {
        return Config::getVar('general', 'app_key', '');
    }

    /**
     * Validate a given cipher
     */
    public static function validateCipher(string $cipher): string
    {
        $cipher = strtolower($cipher);

        if (!in_array($cipher, array_keys(static::getSupportedCiphers()))) {
            $ciphers = implode(', ', array_keys(static::getSupportedCiphers()));
            
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
     * Validate given or config defined app
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
     * Write the given app key in the config file
     * 
     * @throws \Exception
     */
    public static function writeToConfig(string $key): bool
    {
        if (!static::validate($key)) {
            $ciphers = implode(', ', array_keys(static::getSupportedCiphers()));
            
            // Error invalid app key
            throw new Exception(
                "Unsupported cipher or incorrect key length. Supported ciphers are: {$ciphers}."
            );
        }

        $configParser = new ConfigParser;
        $configParams = [
            'general' => [
                'app_key' => $key,
            ],
        ];

        if (!static::hasKeyVariable()) {
            // Error if the config key `app_key` not defined under `general` section
            throw new Exception('Config variable named `app_key` not defined in the `general` section');
        }

        if (!$configParser->updateConfig(Config::getConfigFileName(), $configParams)) {
            // Error reading config file
            throw new Exception('Unable to read the config file');
        }

        if (!$configParser->writeConfig(Config::getConfigFileName())) {
            // Error writing config file
            throw new Exception('Unable to write the app key in the config file');
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
