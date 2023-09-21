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

use Closure;
use stdClass;
use Throwable;
use DomainException;
use APP\facades\Repo;
use Firebase\JWT\Key;
use PKP\config\Config;
use PKP\core\PKPJwt as JWT;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use UnexpectedValueException;
use Firebase\JWT\SignatureInvalidException;
use Illuminate\Auth\Access\AuthorizationException;

class DecodeApiTokenWithValidation
{
    /**
     * Decode and validate the API token with incoming api request.
     * 
     * On successful validation of API Token, set the PKP User object to
     * Laravel's user resolver.
     * 
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {   
        $jwtToken = $request->query('apiToken');
        
        /* VALIDATIONS */
        
        if (!$jwtToken) {
            // As there is not api token, there is nothing to decode or validate,
            // upto the auth layer to determine the how to handle
            return $next($request);
        }
        
        $secret = Config::getVar('security', 'api_key_secret', null);

        if (!$secret) {
            throw new AuthorizationException(__('api.500.apiSecretKeyMissing'));
        }

        $user = null;

        try {
            $headers = new stdClass;
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
        
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        return $next($request);
    }
}
