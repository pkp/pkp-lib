<?php

declare(strict_types=1);

namespace PKP\core\Controllers\Middlewares\Permissions;

use Closure;
use PKP\db\DAORegistry;

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
        $userId = $request->user()->getId();

        $roleDao = DAORegistry::getDAO('RoleDAO'); /** @var RoleDAO $roleDao */
        $userRoles = $roleDao->getByUserIdGroupedByContext($userId);

        // dd($userRoles);
        // Check UserRolesRequiredPolicy
        return $next($request);
    }
}
