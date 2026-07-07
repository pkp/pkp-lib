<?php

/**
 * @file classes/middleware/DecodeApiTokenWithValidation.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DecodeApiTokenWithValidation
 *
 * @ingroup middleware
 *
 * @brief Routing middleware to decode and validate API token
 */

namespace PKP\middleware;

use APP\core\Application;
use APP\facades\Repo;
use Closure;
use DomainException;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PKP\middleware\HasUser;
use PKP\middleware\traits\HasRequiredMiddleware;
use PKP\config\Config;
use PKP\core\PKPJwt as JWT;
use PKP\core\PKPSessionGuard;
use PKP\user\User;
use stdClass;
use Throwable;
use UnexpectedValueException;

class DecodeApiTokenWithValidation
{
    use HasRequiredMiddleware;

    /**
     * @copydoc \PKP\middleware\traits\HasRequiredMiddleware::requiredMiddleware()
     */
    public function requiredMiddleware(): array
    {
        return [
            HasUser::class,
        ];
    }
    
    /**
     * Decode and validate the API token with incoming api request.
     *
     * On successful validation of API Token, set the PKP User object to
     * Laravel's user resolver.
     *
     */
    public function handle(Request $request, Closure $next)
    {
        // Set the default/initial user resolver
        $this->setUserResolver($request);

        if (!$this->hasRequiredMiddleware($request)) {
            // Required middleware not attached to target routes, move to next
            return $next($request);
        }

        $jwtToken = $this->getApiToken($request);

        /* VALIDATIONS */

        if (!$jwtToken) {
            // there is nothing to decode or validate,
            // upto the auth layer to determine the how to handle
            return $next($request);
        }

        $secret = Config::getVar('security', 'api_key_secret', null);

        if (!$secret) {
            throw new AuthorizationException(__('api.500.apiSecretKeyMissing'));
        }

        $user = null;

        try {
            $headers = new stdClass();
            $apiToken = ((array)JWT::decode($jwtToken, new Key($secret, 'HS256'), $headers))[0]; /** @var string $apiToken */

            /**
             * Compatibility with old API keys
             *
             * @link https://github.com/pkp/pkp-lib/issues/6462
             */
            if (substr($apiToken, 0, 2) === '""') {
                $apiToken = json_decode($apiToken);
            }

            $user = Repo::user()->getByApiKey($apiToken);

            if (!$user || !$user->getData('apiKeyEnabled')) {
                return response()->json([
                    'error' => __('api.403.unauthorized'),
                ], Response::HTTP_UNAUTHORIZED);
            }
        } catch (Throwable $exception) {

            if($exception instanceof SignatureInvalidException) {
                return response()->json([
                    'error' => __('api.400.invalidApiToken'),
                ], Response::HTTP_BAD_REQUEST);
            }

            if($exception instanceof DomainException || $exception instanceof UnexpectedValueException) {
                return response()->json([
                    'error' => __('api.400.tokenCouldNotBeDecoded'),
                ], Response::HTTP_BAD_REQUEST);
            }

            // We don't know response to provide so better to just re-throw the exception
            throw $exception;
        }

        /* ACTIONS */

        // Update the user resolver
        $this->setUserResolver($request, $user);

        return $next($request);
    }

    /**
     * Set the user resolving handler
     *
     * If user not resolved retrived through the API Token or it missing,
     * that mean the request probably came from within the app itself
     * and we need to retrive the user from session manager in that case
     */
    protected function setUserResolver(Request &$request, ?User $user = null): void
    {
        if (!$user && !PKPSessionGuard::isSessionDisable()) {
            $user = Application::get()->getRequest()->getUser();
        }

        $request->setUserResolver(function () use ($user) {
            return $user;
        });
    }

    /**
     * Get the API Token
     *
     * API Token may passed as authorization Header such as --> Authorization: Bearer API_TOKEN
     * or as a query param as --> API_URL/?apiToken=API_TOKEN
     */
    protected function getApiToken(Request $request): ?string
    {
        $authHeader = $request->header('Authorization');

        if (!$authHeader) {
            return $request->query('apiToken');
        }

        // Several authorization methods may be supplied with commas between them.
        // For example: Basic basic_auth_string_here, Bearer api_key_here
        // JWT uses the Bearer scheme with an API key. Ignore the others.
        $clauses = explode(',', $authHeader);

        foreach ($clauses as $clause) {

            // Split the authorization scheme and parameters and look for the Bearer scheme.
            $parts = explode(' ', trim($clause));

            if (count($parts) == 2 && $parts[0] == 'Bearer') {
                return $parts[1]; // Found bearer authorization; return the token.
            }
        }

        return null;
    }
}
