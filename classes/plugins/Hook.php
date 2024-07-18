<?php

/**
 * @file classes/plugins/Hook.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Hook
 *
 * @ingroup plugins
 *
 * @brief Class implementing a registry of hooks for extending core functionality
 */

namespace PKP\plugins;

use PKP\core\Registry;

class Hook
{
    public const SEQUENCE_CORE = 0;
    public const SEQUENCE_NORMAL = 256;
    public const SEQUENCE_LATE = 512;
    public const SEQUENCE_LAST = 768;

    public const CONTINUE = false;
    public const ABORT = true;

    /** @var array An associative array of [unsupported_hook_name => anything] */
    protected static $unsupportedHooks = [];

    /**
     * Get the current set of hook registrations.
     *
     * @param string $hookName Name of hook to optionally return
     *
     * @return mixed Array of all hooks or just those attached to $hookName, or
     *   null if nothing has been attached to $hookName
     */
    public static function &getHooks(?string $hookName = null): ?array
    {
        $hooks = & Registry::get('hooks', true, []);
        if ($hookName === null) {
            return $hooks;
        }

        if (isset($hooks[$hookName])) {
            return $hooks[$hookName]['hooks'];
        }

        $returner = null;
        return $returner;
    }

    /**
     * Add an unsupported hook name to the list. If a caller attempts to register for a hook named by
     * calls to this function, an exception will be thrown.
     */
    public static function addUnsupportedHooks(...$hookNames): void
    {
        self::$unsupportedHooks = array_merge(self::$unsupportedHooks, array_flip($hookNames));
    }

    /**
     * Clear hooks registered against the given name.
     */
    public static function clear(string $hookName): void
    {
        $hooks = & static::getHooks();
        unset($hooks[$hookName]);
    }

    /**
     * Register a hook against the given hook name.
     *
     * @param $hookName Name of hook to register against
     * @param $callback Callback pseudo-type
     * @param $hookSequence Optional hook sequence specifier SEQUENCE_...
     */
    public static function add(string $hookName, callable $callback, int $hookSequence = self::SEQUENCE_NORMAL): void
    {
        if (isset(self::$unsupportedHooks[$hookName])) {
            throw new \Exception("Hook {$hookName} is not supported (possibly removed) and callbacks should not be added to it!");
        }
        $hooks = & static::getHooks();
        $hooks[$hookName]['hooks'][$hookSequence][] = $callback;
        $hooks[$hookName]['dirty'] = true; // Need to re-sort
    }

    /**
     * Alias of Hook::add
     *
     * @deprecated 3.4
     */
    public static function register(string $hookName, callable $callback, int $hookSequence = self::SEQUENCE_NORMAL): void
    {
        self::add($hookName, $callback, $hookSequence);
    }

    /**
     * Call each callback registered against $hookName in sequence.
     * The first callback that returns ABORT will interrupt processing and
     * this function will return ABORT; otherwise, all callbacks will be
     * called in sequence and the return value of this call will be
     * CONTINUE.
     *
     * The signature of the callback function should be:
     *   function callback($hookName, $args) : bool;
     * ...and $args will receive the array provided here.
     *
     * This function should be considered deprecated in favour of
     * Hook::call.
     */
    public static function call(string $hookName, array $args = []): mixed
    {
        // Called only by Unit Test
        // This behaviour is DEPRECATED and not replicated in the preferred
        // Hook::call function.
        if (self::rememberCalledHooks(true)) {
            // Remember the called hooks for testing.
            $calledHooks = & static::getCalledHooks();
            $calledHooks[] = [
                $hookName, $args
            ];
        }

        return self::run($hookName, [$args]);
    }

    /**
     * Call each callback registered against $hookName in sequence.
     * The first callback that returns ABORT will interrupt processing and
     * this function will return ABORT; otherwise, all callbacks will be
     * called in sequence and the return value of this call will be
     * CONTINUE.
     *
     * The signature of a callback function should be:
     *   function callback($hookName, ...) : bool;
     * where ... corresponds to the parameters named/listed in the $args
     * parameter to Hook::call. These may be named if desired,
     * and may include references.
     */
    public static function run(string $hookName, array $args = []): bool
    {
        $hooks = & static::getHooks();
        if (!isset($hooks[$hookName])) {
            return self::CONTINUE;
        }

        // Sort callbacks if the list is dirty
        if ($hooks[$hookName]['dirty']) {
            ksort($hooks[$hookName]['hooks'], SORT_NUMERIC);
            $hooks[$hookName]['dirty'] = false;
        }

        foreach ($hooks[$hookName]['hooks'] as $priority => $hookList) {
            foreach ($hookList as $callback) {
                if (call_user_func_array($callback, [$hookName, ...$args]) === self::ABORT) {
                    return self::ABORT;
                }
            }
        }

        return self::CONTINUE;
    }


    //
    // Methods required for testing only.
    //
    /**
     * Set/query the flag that triggers storing of
     * called hooks.
     *
     * @param bool $askOnly When set to true, the flag will not
     *   be changed but only returned.
     * @param bool $updateTo When $askOnly is set to 'true' then
     *   this parameter defines the value of the flag.
     *
     * @return bool The current value of the flag.
     */
    public static function rememberCalledHooks(bool $askOnly = false, bool $updateTo = true): bool
    {
        static $rememberCalledHooks = false;
        if (!$askOnly) {
            $rememberCalledHooks = $updateTo;
        }
        return $rememberCalledHooks;
    }

    /**
     * Switch off the function to store hooks and delete all stored hooks.
     * Always call this after using otherwise we get a severe memory.
     *
     * @param bool $leaveAlive Set this to true if you only want to
     *   delete hooks stored so far but if you want to record future
     *   hook calls, too.
     */
    public static function resetCalledHooks(bool $leaveAlive = false): void
    {
        if (!$leaveAlive) {
            static::rememberCalledHooks(false, false);
        }
        $calledHooks = & static::getCalledHooks();
        $calledHooks = [];
    }

    /**
     * Return a reference to the stored hooks.
     */
    public static function &getCalledHooks(): array
    {
        static $calledHooks = [];
        return $calledHooks;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\plugins\Hook', '\HookRegistry');
    foreach (['SEQUENCE_CORE', 'SEQUENCE_NORMAL', 'SEQUENCE_LATE', 'SEQUENCE_LAST'] as $constantName) {
        define('HOOK_' . $constantName, constant('\HookRegistry::' . $constantName));
    }
}
