<?php

/**
 * @file classes/core/Registry.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Registry
 * @ingroup core
 *
 * @brief Maintains a static table of keyed references.
 * Used for storing/accessing single instance objects and values.
 */

namespace PKP\core;

class Registry
{
    /**
     * Get a static reference to the registry data structure.
     *
     */
    public static function &_getRegistry(): array
    {
        static $registry = [];
        return $registry;
    }

    /**
     * Get the value of an item in the registry (optionally setting a default).
     *
     * @param bool $createIfEmpty Whether or not to create an entry if none exists
     * @param mixed $default If $createIfEmpty, this value will be used as a default
     */
    public static function &get(string $key, bool $createIfEmpty = false, mixed $default = null): mixed
    {
        $registry = & self::_getRegistry();

        if (isset($registry[$key])) {
            return $registry[$key];
        }
        if ($createIfEmpty) {
            self::set($key, $default);
        }
        return $default;
    }

    /**
     * Set the value of an item in the registry.
     */
    public static function set(string $key, mixed &$value): void
    {
        $registry = & self::_getRegistry();
        $registry[$key] = & $value;
    }

    /**
     * Remove an item from the registry.
     */
    public static function delete(string $key): void
    {
        $registry = & self::_getRegistry();
        if (isset($registry[$key])) {
            unset($registry[$key]);
        }
    }

    /**
     * Clear the registry of all contents.
     */
    public static function clear(): void
    {
        $registry = & self::_getRegistry();
        foreach (array_keys($registry) as $key) {
            unset($registry[$key]);
        }
    }
}
