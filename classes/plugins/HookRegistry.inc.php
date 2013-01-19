<?php

/**
 * @file classes/plugins/HookRegistry.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class HookRegistry
 * @ingroup plugins
 *
 * @brief Class for linking core functionality with plugins
 */


class HookRegistry {
	/**
	 * Get the current set of hook registrations.
	 */
	static function &getHooks() {
		$hooks =& Registry::get('hooks', true, array());
		return $hooks;
	}

	/**
	 * Set the hooks table for the given hook name to the supplied array
	 * of callbacks.
	 * @param $hookName string Name of hook to set
	 * @param $callbacks array Array of callbacks for this hook
	 */
	static function setHooks($hookName, $callbacks) {
		$hooks =& HookRegistry::getHooks();
		$hooks[$hookName] =& $callbacks;
	}

	/**
	 * Clear hooks registered against the given name.
	 * @param $hookName string Name of hook
	 */
	static function clear($hookName) {
		$hooks =& HookRegistry::getHooks();
		unset($hooks[$hookName]);
		return $hooks;
	}

	/**
	 * Register a hook against the given hook name.
	 * @param $hookName string Name of hook to register against
	 * @param $callback object Callback pseudotype
	 */
	static function register($hookName, $callback) {
		$hooks =& HookRegistry::getHooks();
		if (!isset($hooks[$hookName])) {
			$hooks[$hookName] = array();
		}
		$hooks[$hookName][] =& $callback;
	}

	/**
	 * Call each callback registered against $hookName in sequence.
	 * The first callback that returns a value that evaluates to true
	 * will interrupt processing and this function will return its return
	 * value; otherwise, all callbacks will be called in sequence and the
	 * return value of this call will be the value returned by the last
	 * callback.
	 * @param $hookName string The name of the hook to register against
	 * @param $args string Hooks are called with this as the second param
	 * @return mixed
	 */
	static function call($hookName, $args = null) {
		// Remember the called hooks for testing.
		$calledHooks =& HookRegistry::getCalledHooks();
		$calledHooks[] = array(
			$hookName, $args
		);

		$hooks =& HookRegistry::getHooks();
		if (!isset($hooks[$hookName])) {
			return false;
		}

		foreach ($hooks[$hookName] as $hook) {
			if ($result = call_user_func($hook, $hookName, $args)) {
				break;
			}
		}

		return $result;
	}


	//
	// Methods required for testing only.
	//
	static function resetCalledHooks() {
		$calledHooks =& HookRegistry::getCalledHooks();
		$calledHooks = array();
	}

	static function &getCalledHooks() {
		static $calledHooks;
		return $calledHooks;
	}
}

?>
