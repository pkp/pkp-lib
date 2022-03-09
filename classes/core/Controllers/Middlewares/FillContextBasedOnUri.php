<?php

declare(strict_types=1);

namespace PKP\core\Controllers\Middlewares;

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
