<?php

/**
 * @file api/v1/rors/PKPRorController.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPRorController
 *
 * @ingroup api_v1_rors
 *
 * @brief Controller class to handle API requests for ror operations.
 *
 */

namespace PKP\API\v1\rors;

use APP\core\Application;
use APP\facades\Repo;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\plugins\Hook;
use PKP\security\authorization\ContextRequiredPolicy;
use PKP\security\authorization\PolicySet;
use PKP\security\authorization\RoleBasedHandlerOperationPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Role;
use Psr\Http\Message\ResponseInterface;

class PKPRorController extends PKPBaseController
{
    /** @var int The default number of rors to return in one request */
    public const DEFAULT_COUNT = 30;

    /** @var int The maximum number of rors to return in one request */
    public const MAX_COUNT = 100;

    /** @var int Maximum number of ror.org API lookups allowed per user within the decay window */
    protected const RATE_LIMIT_MAX_ATTEMPTS = 20;

    /** @var int Number of seconds before the per-user ror.org API lookup rate limit window resets */
    protected const RATE_LIMIT_DECAY_SECONDS = 60;

    /** @var string Shared rate limit key for all outbound ror.org API lookups, regardless of caller */
    protected const GLOBAL_RATE_LIMIT_KEY = 'ror-api:global';

    /**
     * @var int Maximum number of ror.org API lookups allowed institution-wide within the decay
     *  window, since every outbound call reaches ror.org from this server's single IP regardless
     *  of which client triggered it. Kept under ror.org's documented unauthenticated floor of
     *  50 requests/5 minutes (with headroom), since this codebase has no ROR client ID setting yet.
     */
    protected const GLOBAL_RATE_LIMIT_MAX_ATTEMPTS = 40;

    /** @var int Number of seconds before the global ror.org API lookup rate limit window resets */
    protected const GLOBAL_RATE_LIMIT_DECAY_SECONDS = 300;

    /**
     * @copydoc \PKP\core\PKPBaseController::getHandlerPath()
     */
    public function getHandlerPath(): string
    {
        return 'rors';
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::getRouteGroupMiddleware()
     */
    public function getRouteGroupMiddleware(): array
    {
        return [
            'has.user',
            'has.context',
            self::roleAuthorizer([
                Role::ROLE_ID_MANAGER,
                Role::ROLE_ID_SUB_EDITOR,
                Role::ROLE_ID_ASSISTANT,
                Role::ROLE_ID_AUTHOR,
            ]),
        ];
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::getGroupRoutes()
     */
    public function getGroupRoutes(): void
    {
        Route::get('{rorId}', $this->get(...))
            ->name('ror.getRor')
            ->whereNumber('rorId');

        Route::get('', $this->getMany(...))
            ->name('ror.getMany');

        Route::post('', $this->addOrEdit(...))
            ->name('ror.addOrEdit');
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::authorize()
     */
    public function authorize(PKPRequest $request, array &$args, array $roleAssignments): bool
    {
        $this->addPolicy(new UserRolesRequiredPolicy($request), true);

        $rolePolicy = new PolicySet(PolicySet::COMBINING_PERMIT_OVERRIDES);

        $this->addPolicy(new ContextRequiredPolicy($request));

        foreach ($roleAssignments as $role => $operations) {
            $rolePolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, $role, $operations));
        }

        $this->addPolicy($rolePolicy);

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Get a single ror
     */
    public function get(Request $illuminateRequest): JsonResponse
    {
        if (!Repo::ror()->exists((int) $illuminateRequest->route('rorId'))) {
            return response()->json([
                'error' => __('api.rors.404.rorNotFound')
            ], Response::HTTP_OK);
        }

        $ror = Repo::ror()->get((int) $illuminateRequest->route('rorId'));

        return response()->json(Repo::ror()->getSchemaMap()->map($ror), Response::HTTP_OK);
    }

    /**
     * Get a collection of rors
     *
     * @hook API::rors::params [[$collector, $illuminateRequest]]
     */
    public function getMany(Request $illuminateRequest): JsonResponse
    {
        $collector = Repo::ror()->getCollector()
            ->limit(self::DEFAULT_COUNT)
            ->offset(0);

        foreach ($illuminateRequest->query() as $param => $val) {
            switch ($param) {
                case 'count':
                    $collector->limit(min((int) $val, self::MAX_COUNT));
                    break;
                case 'offset':
                    $collector->offset((int) $val);
                    break;
                case 'searchPhrase':
                    $collector->filterBySearchPhrase($val);
                    $collector->filterByIsActive(true);
                    break;
            }
        }

        Hook::call('API::rors::params', [$collector, $illuminateRequest]);

        $rors = $collector->getMany();

        return response()->json([
            'itemsMax' => $collector->getCount(),
            'items' => Repo::ror()->getSchemaMap()->summarizeMany($rors->values())->values(),
        ], Response::HTTP_OK);
    }


    /**
     * Add or refresh a ror
     *
     * The client may only specify which ROR ID to (re)cache; the actual name,
     * display locale and active status come from the authoritative ror.org API,
     * never from the request body, so a caller cannot inject or overwrite
     * institution data in the shared local cache. If the ROR is already cached,
     * the cached record is returned as-is without a new ror.org lookup.
     */
    public function addOrEdit(Request $illuminateRequest): JsonResponse
    {
        $ror = (string) $illuminateRequest->input('ror');

        if (!preg_match('#^https://ror\.org/(0[^ILOU]{6}\d{2})$#', $ror, $matches)) {
            return response()->json([
                'ror' => [__('ror.invalidRorId')],
            ], Response::HTTP_BAD_REQUEST);
        }

        // Skip the ror.org lookup if already cached, trading staleness (only the monthly
        // UpdateRorRegistryDataset task refreshes it otherwise) for rate-limit headroom.
        // Revisit once a ROR client ID setting exists (open issue) to always refetch for those installs.
        $existingRor = Repo::ror()->getByRor($ror);
        if ($existingRor !== null) {
            return response()->json(Repo::ror()->getSchemaMap()->map($existingRor), Response::HTTP_OK);
        }

        $rateLimitKey = 'ror-api:' . $this->getRequest()->getUser()->getId();
        if (RateLimiter::tooManyAttempts($rateLimitKey, self::RATE_LIMIT_MAX_ATTEMPTS)) {
            return response()->json([
                'ror' => [__('api.rors.429.tooManyRequests')],
                'retryAfter' => RateLimiter::availableIn($rateLimitKey),
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        if (RateLimiter::tooManyAttempts(self::GLOBAL_RATE_LIMIT_KEY, self::GLOBAL_RATE_LIMIT_MAX_ATTEMPTS)) {
            return response()->json([
                'ror' => [__('api.rors.429.globalRateLimited')],
                'retryAfter' => RateLimiter::availableIn(self::GLOBAL_RATE_LIMIT_KEY),
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        RateLimiter::hit($rateLimitKey, self::RATE_LIMIT_DECAY_SECONDS);
        RateLimiter::hit(self::GLOBAL_RATE_LIMIT_KEY, self::GLOBAL_RATE_LIMIT_DECAY_SECONDS);

        try {
            $response = Application::get()->getHttpClient()->request(
                'GET',
                'https://api.ror.org/v2/organizations/' . $matches[1],
                ['timeout' => 10]
            );
        } catch (RequestException $e) {
            if ($e->getResponse()?->getStatusCode() === Response::HTTP_TOO_MANY_REQUESTS) {
                return $this->rorApiRateLimitedResponse($e->getResponse());
            }

            return response()->json([
                'ror' => [__('api.rors.404.rorNotFound')],
            ], Response::HTTP_NOT_FOUND);
        } catch (GuzzleException $e) {
            return response()->json([
                'ror' => [__('api.rors.404.rorNotFound')],
            ], Response::HTTP_NOT_FOUND);
        }

        if ($response->getStatusCode() === Response::HTTP_TOO_MANY_REQUESTS) {
            return $this->rorApiRateLimitedResponse($response);
        }

        if ($response->getStatusCode() !== 200) {
            return response()->json([
                'ror' => [__('api.rors.404.rorNotFound')],
            ], Response::HTTP_NOT_FOUND);
        }

        $record = json_decode($response->getBody(), true);
        $params = is_array($record) ? Repo::ror()->mapFromApiRecord($record) : null;

        if ($params === null) {
            return response()->json([
                'ror' => [__('api.rors.404.rorNotFound')],
            ], Response::HTTP_NOT_FOUND);
        }

        $rorObject = Repo::ror()->newDataObject($params);
        $id = Repo::ror()->updateOrInsert($rorObject);
        $rorObject = Repo::ror()->get($id);

        return response()->json(Repo::ror()->getSchemaMap()->map($rorObject), Response::HTTP_OK);
    }

    /**
     * Build the response for a 429 originating from ror.org itself, passing through its
     * Retry-After header when present rather than guessing a wait time we weren't given.
     */
    protected function rorApiRateLimitedResponse(?ResponseInterface $response): JsonResponse
    {
        $body = ['ror' => [__('api.rors.429.rorApiRateLimited')]];

        $retryAfter = $response?->getHeaderLine('Retry-After');
        if ($retryAfter !== null && $retryAfter !== '') {
            $body['retryAfter'] = $retryAfter;
        }

        return response()->json($body, Response::HTTP_TOO_MANY_REQUESTS);
    }
}
