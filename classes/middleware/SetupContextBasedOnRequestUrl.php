<?php

/**
 * @file classes/middleware/SetupContextBasedOnRequestUrl.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SetupContextBasedOnRequestUrl
 *
 * @ingroup middleware
 *
 * @brief Routing middleware to apply correct context
 */

namespace PKP\middleware;

use APP\core\Application;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PKP\core\Core;

class SetupContextBasedOnRequestUrl
{
    /**
     * Determine and apply the correct context based on request url
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
