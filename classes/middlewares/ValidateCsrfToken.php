<?php

declare(strict_types=1);

namespace PKP\middlewares;

use APP\core\Application;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ValidateCsrfToken
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
        if($this->isApiRequest($request)) {
            return $next($request);
        }

        if (!$this->isCsrfRequiredForRequest($request)) {
            return $next($request);
        }

        if (!$this->isCsrfValid($request)) {
            return response()->json([
                'error' => __('form.csrfInvalid')
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }

    protected function isApiRequest(Request $request): bool
    {
        return $request->query('apiToken', null) ? true : false;
    }

    protected function isCsrfRequiredForRequest(Request $request): bool
    {
        return in_array($request->server('REQUEST_METHOD'), ['PUT', 'PATCH', 'POST', 'DELETE']);
    }

    protected function isCsrfValid(Request $request): bool
    {
        $requestCsrfToken = $request->server('HTTP_X_CSRF_TOKEN', null);

        if($requestCsrfToken === null) {
            return false;
        }

        $pkpSession = Application::get()->getRequest()->getSession();

        return $pkpSession->getCSRFToken() === $requestCsrfToken;
    }
}