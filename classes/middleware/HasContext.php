<?php

/**
 * @file classes/middleware/HasContext.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class HasContext
 *
 * @ingroup middleware
 *
 * @brief Routing middleware to apply context validation
 */

namespace PKP\middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PKP\context\Context;

class HasContext
{
    /**
     * Check if context has bound to request and exists
     *
     */
    public function handle(Request $request, Closure $next)
    {
        $context = $request->attributes->get('context', null);

        if ($context instanceof Context) {
            return $next($request);
        }

        return response()->json([
            'error' => __('api.404.resourceNotFound')
        ], Response::HTTP_NOT_FOUND);
    }
}
