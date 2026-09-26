<?php

/**
 * @file classes/middleware/TestModeGate.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class TestModeGate
 *
 * @ingroup middleware
 *
 * @brief Gate for the test-only /api/v1/_test/* routes.
 *
 * Only admits requests when the environment is explicitly in test mode.
 * Both checks must pass:
 *   1. APPLICATION_ENV env var equals 'test'
 *   2. X-Test-Key header matches the TEST_API_KEY env var (non-empty)
 *
 * Failures return 404 so that the test-only surface is indistinguishable
 * from "route does not exist" from an attacker's point of view.
 */

namespace PKP\middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TestModeGate
{
    public function handle(Request $request, Closure $next)
    {
        if (getenv('APPLICATION_ENV') !== 'test') {
            return $this->notFound();
        }

        $expected = getenv('TEST_API_KEY');
        if ($expected === false || $expected === '') {
            return $this->notFound();
        }

        $provided = $request->header('X-Test-Key');
        if (!is_string($provided) || !hash_equals($expected, $provided)) {
            return $this->notFound();
        }

        return $next($request);
    }

    private function notFound()
    {
        return response()->json(
            ['error' => 'Not Found'],
            Response::HTTP_NOT_FOUND
        );
    }
}
