<?php

/**
 * @file classes/middleware/AttachFileUploadHeader.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AttachFileUploadHeader
 *
 * @ingroup middleware
 *
 * @brief Routing middleware to adds the necessary response headers to allow file upload
 */

namespace PKP\middleware;

use Closure;
use Illuminate\Http\Request;

class AttachFileUploadHeader
{
    /**
     * Add necessary headers to allow file uploading process
     *
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $response->headers->set(
            'Access-Control-Allow-Headers',
            'Content-Type, X-Requested-With, X-PINGOTHER, X-File-Name, Cache-Control'
        );

        return $response;
    }
}
