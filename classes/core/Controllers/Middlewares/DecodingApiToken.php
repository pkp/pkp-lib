<?php

declare(strict_types=1);

namespace PKP\core\Controllers\Middlewares;

use APP\facades\Repo;
use Closure;
use DomainException;
use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\SignatureInvalidException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

use PKP\config\Config;
use UnexpectedValueException;

class DecodingApiToken
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     *
     */
    public function handle($request, Closure $next)
    {
        $jwt = $request->query('apiToken');

        if (!$jwt) {
            /**
             * If we don't have a token, it's for the authentication logic to handle if it's a problem.
             */

            return $next($request);
        }

        $secret = Config::getVar('security', 'api_key_secret', null);
        if (!$secret) {
            throw new AuthorizationException('api.500.apiSecretKeyMissing');
        }

        $apiUser = null;

        try {
            $apiToken = (string) JWT::decode($jwt, $secret, ['HS256']);

            /**
             * Compatibility with old API keys
             *
             * @link https://github.com/pkp/pkp-lib/issues/6462
             */
            if (substr($apiToken, 0, 2) === '""') {
                $apiToken = json_decode($apiToken);
            }

            $apiUser = Repo::user()->getByApiKey($apiToken);

            if ($apiUser === null || !$apiUser->getData('apiKeyEnabled')) {
                return new JsonResponse(
                    ['error' => 'api.403.unauthorized'],
                    Response::HTTP_UNAUTHORIZED
                );
            }

            $contextId = $request->attributes->get('pkpContext')->getData('id');
            $apiUser->getRoles($contextId, true);
        } catch (Exception $e) {
            /**
             * If JWT decoding fails, it throws an 'UnexpectedValueException'.
             * If JSON decoding fails (of the JWT payload), it throws a 'DomainException'.
             * If token couldn't verified, it throws a 'SignatureInvalidException'.
             */
            if ($e instanceof SignatureInvalidException) {
                return new AuthorizationException(
                    'api.400.invalidApiToken',
                    Response::HTTP_UNAUTHORIZED
                );
            }

            if ($e instanceof UnexpectedValueException ||
                $e instanceof DomainException
            ) {
                return new AuthorizationException(
                    'api.400.tokenCouldNotBeDecoded',
                    Response::HTTP_UNAUTHORIZED
                );
            }

            throw $e;
        }

        $request->setUserResolver(function () use ($apiUser) {
            return $apiUser;
        });

        return $next($request);
    }
}
