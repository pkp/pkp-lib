<?php
/**
 * @file tests/mock/env1/MockValidation.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Validation
 * @ingroup tests_mock_env1
 *
 * @see PKPPageRouterTest
 *
 * @brief Mock implementation of the Validation class for the PKPPageRouterTest
 */

namespace PKP\security;

class Validation
{
    public static $_isLoggedIn = false;

    public static function isLoggedIn()
    {
        return Validation::$_isLoggedIn;
    }

    public static function setIsLoggedIn($isLoggedIn)
    {
        Validation::$_isLoggedIn = $isLoggedIn;
    }
}
