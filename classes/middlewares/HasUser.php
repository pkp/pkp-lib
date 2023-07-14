<?php

declare(strict_types=1);

namespace PKP\middlewares;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PKP\user\User;

class HasUser
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
        $user = $request->user();

        if ($user && $user instanceof User ) {
            return $next($request);
        }

        return response()->json([
            'error' => __('api.403.unauthorized'),
        ], Response::HTTP_UNAUTHORIZED);
    }
}