<?php

declare(strict_types=1);

/**
 * @file classes/core/middleware/permissions/NeedsContext.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NeedsContext
 * @ingroup core_middleware_permissions
 *
 * @brief Middleware to validate if request has an associated context
 */

namespace PKP\core\middleware\permissions;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use PKP\context\Context;

class NeedsContext
{
    /**
     * Verify if current request has a Context associated with it
     */
    public function handle($request, Closure $next)
    {
        $context = $request->attributes->get('pkpContext', null);

        if (!is_a($context, Context::class)) {
            return new JsonResponse(
                ['error' => 'api.404.resourceNotFound'],
                Response::HTTP_NOT_FOUND
            );
        }

        return $next($request);
    }
}
