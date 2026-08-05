<?php

/**
 * @file classes/testing/RequireTestApiKey.php
 *
 * Copyright (c) 2014-2026 Simon Fraser University
 * Copyright (c) 2000-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RequireTestApiKey
 *
 * @ingroup testing
 *
 * @brief Routing middleware guarding every /api/v1/_test/* route.
 *
 * Without the environment variable the namespace does not exist (404); with it,
 * a request must present the matching X-Test-Key header (403 otherwise).
 */

namespace PKP\testing;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class RequireTestApiKey
{
    public function handle(Request $request, Closure $next)
    {
        if (!TestApiGate::isEnabled()) {
            return response()->json([
                'error' => 'api.404.endpointNotFound',
                'errorMessage' => __('api.404.endpointNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        if (!TestApiGate::accepts($request->header(TestApiGate::HEADER))) {
            return response()->json([
                'error' => 'testApi.403.invalidKey',
                'errorMessage' => 'A valid ' . TestApiGate::HEADER . ' header is required.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
