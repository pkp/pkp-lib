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
use APP\facades\Repo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use PKP\core\PKPBaseController;
use PKP\jobs\orcid\SendAuthorMail;
use PKP\orcid\OrcidManager;
use PKP\security\Role;
use PKP\stageAssignment\StageAssignment;

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
        $context = $this->getRequest()->getContext();
        $validate = $this->validateRequest($illuminateRequest);

        if ($validate['error']) {
            return response()->json([
                'error' => $validate['error'],
            ], $validate['status']);
        }

        $author = $validate['author']; /** @var Author $author */
        try {
            $author->setData('orcidVerificationRequested', true);
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

        if ($validate['error']) {
            return response()->json(['error' => $validate['error']], $validate['status']);
        }

        $author = $validate['author']; /** @var Author $author */

        $author->setOrcid(null);
        $author->setOrcidVerified(false);
        OrcidManager::removeOrcidAccessToken($author);
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
     * Check if the current user has editor permissions
     */
    private function hasEditorPermissions(int $publicationId): bool
    {
        $user = $this->getRequest()->getUser();
        $context = $this->getRequest()->getContext();
        $currentRoles = array_map(
            function (Role $role) {
                return $role->getId();
            },
            $user->getRoles($context->getId())
        );

        if (array_intersect([Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER], $currentRoles)) {
            return true;
        }

        $submissionId = Repo::publication()->get($publicationId)->getData('submissionId');
        $editorAssignment = StageAssignment::withSubmissionIds([$submissionId])
            ->withRoleIds([Role::ROLE_ID_SUB_EDITOR])
            ->withUserId($user->getId())
            ->first();

        return $editorAssignment !== null;
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

        if (!$this->hasEditorPermissions($author->getData('publicationId'))) {
            return [
                'error' => __('api.orcid.403.editWithoutPermission'),
                'status' => Response::HTTP_FORBIDDEN
            ];
        }

        return ['author' => $author];
    }
}
