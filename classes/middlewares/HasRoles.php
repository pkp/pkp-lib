<?php

declare(strict_types=1);

namespace PKP\middlewares;

use APP\core\Application;
use Closure;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class HasRoles
{
    /**
     * 
     * 
     * @param \Illuminate\Http\Request  $request
     * @param Closure                   $next
     * @param string                    $matchableRoles The passed comma separated roles e.g. , 1,2,3
     * @param bool                      $string         Should the passed roles match all(strict) or any(loose)
     * 
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string $matchableRoles, bool $strict = false)
    {
        $user = $request->user(); /** @var \PKP\user\User $user */
        $context = $request->attributes->get('context'); /** @var \PKP\context\Context $context */

        $userRoles = collect($user->getRoles($context?->getId() ?? Application::CONTEXT_SITE))
            ->map(fn ($role) => $role->getId())
            ->sort();

        $matchableRoles = Str::of($matchableRoles)
            ->explode('|')
            ->map(fn($role) => (int)$role)
            ->sort();

        $matchedRoles = $userRoles->intersect($matchableRoles);

        if ($matchedRoles->isEmpty() || ($strict && !$matchableRoles->diff($matchedRoles)->isEmpty())) {
            return response()->json([
                'error' => __('api.403.unauthorized'),
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}