<?php

declare(strict_types=1);

namespace PKP\controllers\Middlewares;

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
