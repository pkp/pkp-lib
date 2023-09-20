<?php

/**
 * @file classes/middleware/
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2000-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class 
 *
 * @ingroup middleware
 *
 * @brief 
 *
 */

namespace PKP\middleware;

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