<?php

/**
 * @file classes/middleware/AllowCrossOrigin.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AllowCrossOrigin
 *
 * @ingroup middleware
 *
 * @brief Routing middleware to apply cross origin header
 */

namespace PKP\middleware;

use Closure;
use Illuminate\Http\Request;

class AllowCrossOrigin
{
    /**
     * Apply cross origin headers to request
     *
     */
    public function handle(Request $request, Closure $next)
    {
        $request->headers->set('Access-Control-Allow-Origin', '*');
        $request->headers->set('Access-Control-Allow-Methods', ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS']);
        $request->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization');

        return $next($request);
    }
}
