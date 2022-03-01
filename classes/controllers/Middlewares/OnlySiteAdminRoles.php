<?php

declare(strict_types=1);

namespace PKP\controllers\Middlewares;

use Closure;

class OnlySiteAdminRoles
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     *
     */
    public function handle($request, Closure $next)
    {
        return $next($request);
    }
}
