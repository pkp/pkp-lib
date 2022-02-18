<?php
/**
 * @file classes/core/APIResponse.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class APIResponse
 * @ingroup core
 *
 * @brief Extends the Response class in the Slim microframework.
 */

namespace PKP\core;

use Slim\Http\Response;

class APIResponse extends Response
{
    /**
     * Response with an error message
     *
     * @param string $msg The message translation key
     * @param string $params Optional parameters to pass to the translation
     *
     * @return APIResponse
     */
    public function withJsonError($msg, $params = null)
    {
        return $this->withJson(
            [
                'error' => $msg,
                'errorMessage' => __($msg, $params ?? []),
            ]
        );
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\core\APIResponse', '\APIResponse');
}
