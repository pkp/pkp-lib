<?php

declare(strict_types=1);

/**
 * @file classes/core/middleware/HasUser.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class HasUser
 * @ingroup core_middleware
 *
 * @brief Middleware to check if Request object has an associated user, attached by Laravel's setUserResolver.
 */

namespace PKP\core\middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class HasUser
{
    public function handle($request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return new JsonResponse(
                ['error' => 'api.403.unauthorized'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        return $next($request);
    }
}
