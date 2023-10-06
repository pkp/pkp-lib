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
        if (!OrcidManager::isEnabled($context)) {
            return response()->json([
                'error' => __('api.orcid.403.orcidNotEnabled'),
            ], Response::HTTP_FORBIDDEN);
        }

        $authorId = (int) $illuminateRequest->route('authorId');
        $author = Repo::author()->get($authorId);

        if (empty($author)) {
            return response()->json([
                'error' => __('api.orcid.404.authorNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        $user = $this->getRequest()->getUser();
        $currentRoles = array_map(
            function (Role $role) {
                return $role->getId();
            },
            $user->getRoles($context->getId())
        );

        if (!array_intersect([Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER], $currentRoles)) {
            $publicationId = $author->getData('publicationId');
            $submissionId = Repo::publication()->get($publicationId)->getData('submissionId');

            $editorAssignment = StageAssignment::withSubmissionIds([$submissionId])
                ->withRoleIds([Role::ROLE_ID_SUB_EDITOR])
                ->withUserId($user->getId())
                ->first();

            if ($editorAssignment === null) {
                return response()->json([
                    'error' => __('api.orcid.403.editWithoutPermission'),
                ], Response::HTTP_FORBIDDEN);
            }
        }

        try {
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
        $context = $this->getRequest()->getContext();
        if (!OrcidManager::isEnabled($context)) {
            return response()->json([
                'error' => __('api.orcid.403.orcidNotEnabled'),
            ], Response::HTTP_FORBIDDEN);
        }

        $authorId = (int) $illuminateRequest->route('authorId');
        $author = Repo::author()->get($authorId);

        if (empty($author)) {
            return response()->json([
                'error' => __('api.orcid.404.authorNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        $user = $this->getRequest()->getUser();
        $currentRoles = array_map(
            function (Role $role) {
                return $role->getId();
            },
            $user->getRoles($context->getId())
        );

        if (!array_intersect([Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER], $currentRoles)) {
            $publicationId = $author->getData('publicationId');
            $submissionId = Repo::publication()->get($publicationId)->getData('submissionId');

            $editorAssignment = StageAssignment::withSubmissionIds([$submissionId])
                ->withRoleIds([Role::ROLE_ID_SUB_EDITOR])
                ->withUserId($user->getId())
                ->first();

            if ($editorAssignment === null) {
                return response()->json([
                    'error' => __('api.orcid.403.editWithoutPermission'),
                ], Response::HTTP_FORBIDDEN);
            }
        }

        $author->setOrcid(null);
        $author->setOrcidVerified(false);
        OrcidManager::removeOrcidAccessToken($author);
        Repo::author()->edit($author, []);

        return response()->json([], Response::HTTP_OK);
    }
}
