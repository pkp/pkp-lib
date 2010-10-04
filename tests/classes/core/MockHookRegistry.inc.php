<?php

/**
 * @file tests/classes/core/MockHookRegistry.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class HookRegistry
 * @ingroup tests_classes_core
 * @see PKPRequestTest
 *
 * @brief Mock implementation of the HookRegistry class for the PKPRequestTest
 */


class HookRegistry {
	static $_calledHooks = array();

	/**
	 * Mocked method
	 * @param $hookName string The name of the hook to register against
	 * @param $args string Hooks are called with this as the second param
	 * @return null
	 */
	function call($hookName, $args = null) {
		self::$_calledHooks[] = array(
			$hookName, $args
		);

		// We work around the problem that we cannot
		// call header() in PHPUnit.
		if ($hookName == 'Request::redirect') return true;

		return null;
	}

	//
	// Methods that configure the mock implementation
	//
	public static function resetCalledHooks() {
		self::$_calledHooks = array();
	}

	public static function getCalledHooks() {
		return self::$_calledHooks;
	}
}
?>