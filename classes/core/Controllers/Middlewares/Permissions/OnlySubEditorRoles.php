<?php

declare(strict_types=1);

namespace PKP\core\Controllers\Middlewares\Permissions;

use Closure;

class OnlySubEditorRoles
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
