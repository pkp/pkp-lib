<?php

/**
 * @file tests/mock/env1/MockRequest.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Request
 * @ingroup tests_mock_env1
 *
 * @brief Mock implementation of the Request class
 */

namespace APP\core;

use PKP\core\PKPRequest;

class Request extends PKPRequest
{
    private static $_requestMethod;

    public function setRequestMethod($requestMethod)
    {
        self::$_requestMethod = $requestMethod;
    }

    public function isPost()
    {
        return (self::$_requestMethod == 'POST');
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\APP\core\Request', '\Request');
}
