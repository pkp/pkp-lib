<?php

declare(strict_types=1);

/**
 * @file classes/core/middleware/FillContextBasedOnUri.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FillContextBasedOnUri
 * @ingroup core_middleware
 *
 * @brief Middleware to add context on Request Object
 */

namespace PKP\core\middleware;

use APP\core\Application;
use Closure;
use Illuminate\Http\Request;
use PKP\core\Core;
use PKP\db\DAORegistry;

class FillContextBasedOnUri
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        parse_str($request->getQueryString(), $queryStrings);

        $application = new Application();
        $contextDepth = $application->getContextDepth();
        $contextList = $application->getContextList();

        $path = Core::getContextPaths(
            $request->getPathInfo(),
            $request->attributes->get('isPathInfoEnabled'),
            $contextList,
            $contextDepth,
            $queryStrings
        );

        if ($path === 'index') {
            $request->attributes->add(['pkpContext' => null]);

            return $next($request);
        }

        // Retrieving the context's associated DAO from contextList.
        $requestedContextLevel = $contextDepth - 1;
        $requestedContextName = $contextList[$requestedContextLevel];
        $contextClass = ucfirst($requestedContextName);
        $daoInstance = DAORegistry::getDAO($contextClass . 'DAO');

        // Retrieve the context from the DAO (by path)
        assert(method_exists($daoInstance, 'getByPath'));
        $data = $daoInstance->getByPath($path[0]);
        $request->attributes->add(['pkpContext' => $data]);

        return $next($request);
    }
}
