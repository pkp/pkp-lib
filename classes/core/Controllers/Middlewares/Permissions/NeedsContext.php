<?php

declare(strict_types=1);

namespace PKP\core\Controllers\Middlewares\Permissions;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use PKP\context\Context;

class NeedsContext
{
    /**
     * Verify if current request has a Context associated with it
     *
     * @param \Illuminate\Http\Request $request
     *
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
