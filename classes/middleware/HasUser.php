<?php

/**
 * @file classes/middleware/HasUser.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class HasUser
 *
 * @ingroup middleware
 *
 * @brief Routing middleware to check user existence
 */

namespace PKP\middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PKP\user\User;

class HasUser
{
    /**
     * Check the existence of bound user to the request object
     *
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user instanceof User) {
            return $next($request);
        }

        return response()->json([
            'error' => __('api.403.unauthorized'),
        ], Response::HTTP_UNAUTHORIZED);
    }
}
