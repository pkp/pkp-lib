<?php

declare(strict_types=1);

namespace PKP\middlewares;

use APP\core\Application;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PKP\context\Context;
use PKP\core\Core;

class SetupContextBasedOnRequestUrl
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
        $contextPath = Core::getContextPath($request->getPathInfo());

        if ($contextPath === 'index') {
            $request->attributes->add(['context' => null]);

            return $next($request);
        }

        $contextDao = Application::getContextDAO();

        $context = $contextDao->getByPath($contextPath);

        if (!$context) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        $request->attributes->add(['context' => $context]);

        return $next($request);
    }

}