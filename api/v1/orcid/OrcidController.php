<?php

/**
 * @file api/v1/dois/OrcidController.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class OrcidController
 *
 * @ingroup api_v1_orcid
 *
 * @brief Handle API requests for ORCID operations.
 *
 */

namespace PKP\API\v1\orcid;

use APP\author\Author;
use APP\core\Application;
use APP\facades\Repo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use PKP\context\Context;
use PKP\core\PKPBaseController;
use PKP\jobs\orcid\SendAuthorMail;
use PKP\orcid\OrcidManager;
use PKP\security\Role;

class OrcidController extends PKPBaseController
{
    /**
     * @inheritDoc
     */
    public function getHandlerPath(): string
    {
        return 'orcid';
    }

    /**
     * @inheritDoc
     */
    public function getRouteGroupMiddleware(): array
    {
        return [
            'has.user',
            'has.context',
            self::roleAuthorizer([
                Role::ROLE_ID_SITE_ADMIN,
                Role::ROLE_ID_MANAGER,
                Role::ROLE_ID_SUB_EDITOR,
                Role::ROLE_ID_AUTHOR,
                Role::ROLE_ID_ASSISTANT,
            ]),
        ];
    }

    /**
     * @inheritDoc
     */
    public function getGroupRoutes(): void
    {
        Route::post('requestAuthorVerification/{authorId}', $this->requestAuthorVerification(...))
            ->name('orcid.requestAuthorVerification');
        Route::post('deleteForAuthor/{authorId}', $this->deleteForAuthor(...))
            ->name('orcid.delete');
    }

    /**
     * Send email request for author to link their ORCID to the submission in OJS
     *
     */
    public function requestAuthorVerification(Request $illuminateRequest): JsonResponse
    {
        $validate = $this->validateRequest($illuminateRequest);

        if ($validate['error'] ?? false) {
            return response()->json([
                'error' => $validate['error'],
            ], $validate['status']);
        }

        $author = $validate['author']; /** @var Author $author */
        $context = $validate['context']; /** @var Context $context */
        try {
            $author->setData('orcidVerificationRequested', true);
            Repo::author()->edit($author, ['orcidVerificationRequested']);
            dispatch(new SendAuthorMail($author, $context, true));
        } catch (\Exception $exception) {
            return response()->json([
                'error' => __('api.orcid.404.contextRequired'),
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([], Response::HTTP_OK);
    }

    /**
     * Remove ORCID and access token data from submission author
     *
     */
    public function deleteForAuthor(Request $illuminateRequest): JsonResponse
    {
        $validate = $this->validateRequest($illuminateRequest);

        if (array_key_exists('error', $validate)) {
            return response()->json(['error' => $validate['error']], $validate['status']);
        }

        $author = $validate['author']; /** @var Author $author */
        $context = $validate['context']; /** @var Context $context */

        $author->setOrcid(null);
        $author->setOrcidVerified(false);
        OrcidManager::removeOrcidAccessToken($author, context: $context);
        Repo::author()->edit($author, []);

        return response()->json([], Response::HTTP_OK);
    }

    /**
     * Check if ORCID is enabled in the current context
     */
    private function isOrcidEnabled(): bool
    {
        $context = $this->getRequest()->getContext();
        return OrcidManager::isEnabled($context);
    }

    /**
     * Performs common validation logic for controller endpoints, return validated data or error information.
     *
     * @return array Returns an associative array containing either the validated data or error information
     */
    private function validateRequest(Request $illuminateRequest): array
    {
        if (!$this->isOrcidEnabled()) {
            return [
                'error' => __('api.orcid.403.orcidNotEnabled'),
                'status' => Response::HTTP_FORBIDDEN
            ];
        }

        $authorId = (int)$illuminateRequest->route('authorId');
        $author = Repo::author()->get($authorId);

        if (empty($author)) {
            return [
                'error' => __('api.orcid.404.authorNotFound'),
                'status' => Response::HTTP_NOT_FOUND
            ];
        }

        $publication = Repo::publication()->get($author->getData('publicationId'));
        if (!$publication) {
            return [
                'error' => __('api.404.resourceNotFound'),
                'status' => Response::HTTP_NOT_FOUND
            ];
        }
        $submission = Repo::submission()->get($publication->getData('submissionId'));
        $submissionContextId = $submission?->getData('contextId');
        $requestContext = $this->getRequest()->getContext();

        if ($submissionContextId !== $requestContext->getId()) {
            return [
                'error' => __('api.orcid.404.authorNotFound'),
                'status' => Response::HTTP_NOT_FOUND
            ];
        }

        $user = $this->getRequest()->getUser();
        $isSiteAdmin = $user->hasRole([Role::ROLE_ID_SITE_ADMIN], Application::SITE_CONTEXT_ID);

        if (!$isSiteAdmin && !Repo::submission()->canEditPublication($submission->getId(), $user->getId())
        ) {
            return [
                'error' => __('api.orcid.403.editWithoutPermission'),
                'status' => Response::HTTP_FORBIDDEN
            ];
        }

        return ['author' => $author, 'context' => $requestContext];
    }
}
