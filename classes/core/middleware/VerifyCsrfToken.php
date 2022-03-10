<?php

declare(strict_types=1);

/**
 * @file classes/core/middleware/VerifyCsrfToken.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class VerifyCsrfToken
 * @ingroup core_middleware
 *
 * @brief Middleware to check if Request needs a CSRFToken, and validates it if necessary.
 */

namespace PKP\core\middleware;

use APP\core\Application;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class VerifyCsrfToken
{
    protected $request = null;

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $this->request = $request;

        if ($this->isApiRequest()) {
            return $next($this->request);
        }

        if ($this->shouldValidateCsrfOnThisRequest() &&
            $this->itsCsrfTokenValid() === false
        ) {
            return new JsonResponse(
                [
                    'error' => 'form.csrfInvalid',
                    'errorMessage' => __('form.csrfInvalid'),
                ],
                Response::HTTP_UNAUTHORIZED
            );
        }

        return $next($request);
    }

    protected function isApiRequest(): bool
    {
        return (bool) $this->request->query('apiToken', false);
    }

    protected function shouldValidateCsrfOnThisRequest(): bool
    {
        $method = $this->request->server('REQUEST_METHOD', false);

        return (bool) ($method !== false && in_array($method, ['POST', 'PUT', 'DELETE']));
    }

    protected function itsCsrfTokenValid(): bool
    {
        $token = $this->request->server('HTTP_X_CSRF_TOKEN', false);
        if (!$token) {
            return false;
        }
        $session = Application::get()->getRequest()->getSession();

        return $session && $session->getCSRFToken() === $token;
    }
}
