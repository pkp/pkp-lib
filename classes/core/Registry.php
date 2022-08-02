<?php

/**
 * @file classes/core/Registry.php
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
     * @return array
     */
    public static function &_getRegistry()
    {
        static $registry = [];
        return $registry;
    }

    /**
     * Get the value of an item in the registry.
     *
     * @param string $key
     * @param bool $createIfEmpty Whether or not to create the entry if none exists
     * @param mixed $createWithDefault If $createIfEmpty, this value will be used as a default
     */
    public static function &get($key, $createIfEmpty = false, $createWithDefault = null)
    {
        $registry = & self::_getRegistry();

        $result = null;
        if (isset($registry[$key])) {
            $result = & $registry[$key];
        } elseif ($createIfEmpty) {
            $result = $createWithDefault;
            self::set($key, $result);
        }
        return $result;
    }

    /**
     * Set the value of an item in the registry.
     * The item will be added if it does not already exist.
     *
     * @param string $key
     */
    public static function set($key, &$value)
    {
        $registry = & self::_getRegistry();
        $registry[$key] = & $value;
    }

    /**
     * Remove an item from the registry.
     *
     * @param string $key
     */
    public static function delete($key)
    {
        $registry = & self::_getRegistry();
        if (isset($registry[$key])) {
            unset($registry[$key]);
        }
    }

    public static function clear()
    {
        $registry = & self::_getRegistry();
        foreach (array_keys($registry) as $key) {
            unset($registry[$key]);
        }
    }
}
