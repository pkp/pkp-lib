<?php

/**
 * @file classes/middleware/
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2000-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class 
 *
 * @ingroup middleware
 *
 * @brief 
 *
 */

namespace PKP\middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PKP\context\Context;

class HasContext
{
    /**
     * 
     * 
     * @param \Illuminate\Http\Request  $request
     * @param Closure                   $next
     * 
     * @return mixed
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