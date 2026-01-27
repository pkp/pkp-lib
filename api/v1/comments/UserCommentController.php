<?php

/**
 * @file api/v1/comments/UserCommentController.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2005 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserCommentController
 *
 * @ingroup api_v1_comments
 *
 * @brief Handle API requests for user comment operations.
 */

namespace PKP\API\v1\comments;

use APP\core\Application;
use APP\facades\Repo;
use APP\notification\NotificationManager;
use PKP\core\PKPRequest;
use PKP\notification\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use PKP\API\v1\comments\formRequests\AddComment;
use PKP\API\v1\comments\formRequests\AddReport;
use PKP\API\v1\comments\resources\UserCommentReportResource;
use PKP\API\v1\comments\resources\UserCommentResource;
use PKP\core\PKPBaseController;
use PKP\core\PKPString;
use PKP\security\Role;
use PKP\userComment\relationships\UserCommentReport;
use PKP\userComment\UserComment;

class UserCommentController extends PKPBaseController
{
    /** @copydoc */
    public function getHandlerPath(): string
    {
        return 'comments';
    }

    /** @copydoc */
    public function getRouteGroupMiddleware(): array
    {
        return ['has.context'];
    }

    /** @copydoc */
    public function getGroupRoutes(): void
    {
        /**
         * This route does not have the 'has.user' middleware to allow unauthenticated users to fetch public comments for a publication. Consequently, authenticated users won't have
         * user context in their requests. Therefore, a separate authenticated endpoint exists for moderators(admins/managers) to fetch comments.
         */
        Route::get('public', $this->getManyPublicComments(...))
            ->name('comment.getManyPublic');

        // Routes accessible only to authenticated users
        Route::middleware([
            'has.user',
        ])->group(function () {
            Route::post('', $this->submit(...))
                ->name('comment.submit');

            Route::delete('{commentId}', $this->delete(...))
                ->name('comment.delete')
                ->whereNumber('commentId');

            Route::post('{commentId}/reports', $this->submitReport(...))
                ->name('comment.submitReport')
                ->whereNumber('commentId');

            // Moderator routes
            Route::middleware([
                self::roleAuthorizer([
                    Role::ROLE_ID_SITE_ADMIN,
                    Role::ROLE_ID_MANAGER
                ]),
            ])->group(function () {
                Route::get('', $this->getMany(...))
                    ->name('comment.getMany');

                Route::get('{commentId}', $this->get(...))
                    ->name('comment.getComment')
                    ->whereNumber('commentId');

                Route::put('{commentId}/setApproval', $this->setApproval(...))
                    ->name('comment.setApproval')
                    ->whereNumber('commentId');

                Route::delete('{commentId}/reports/{reportId}', $this->deleteReport(...))
                    ->name('comment.deleteReport')
                    ->whereNumber('commentId')
                    ->whereNumber('reportId');

                Route::get('{commentId}/reports/{reportId}', $this->getReport(...))
                    ->name('comment.getReport')
                    ->whereNumber('commentId')
                    ->whereNumber('reportId');

                Route::get('{commentId}/reports', $this->getReports(...))
                    ->name('comment.getReports')
                    ->whereNumber('commentId');

                Route::delete('{commentId}/reports', $this->deleteReports(...))
                    ->name('comment.deleteReports')
                    ->whereNumber('commentId');
            });
        });
    }


    /**
     * @copydoc
     */
    public function authorize(PKPRequest $request, array &$args, array $roleAssignments): bool
    {
        // No authorization required for public endpoint
        return true;
    }
    /**
     * Gets the publicly accessible comments for a publication.
     * Accepts the following query parameters:
     * publicationIds(required, array) publication IDs to fetch comments for.
     * page(integer) - The pagination page to retrieve records from.
     */
    public function getManyPublicComments(Request $illuminateRequest): JsonResponse
    {
        $publicationIdsRaw = paramToArray($illuminateRequest->query('publicationIds') ?? []);

        if (empty($publicationIdsRaw)) {
            return response()->json(['error' => __('api.userComments.400.missingPublicationParam')], Response::HTTP_BAD_REQUEST);
        }

        $publicationIds = [];
        foreach ($publicationIdsRaw as $id) {
            if (!filter_var($id, FILTER_VALIDATE_INT)) {
                return response()->json([
                    'error' => __('api.userComments.400.invalidPublicationId', ['publicationId' => $id])
                ], Response::HTTP_BAD_REQUEST);
            }

            $publicationIds[] = (int)$id;
        }

        $query = UserComment::withContextIds([$this->getRequest()->getContext()->getId()])
            ->withIsApproved(true);


        $user = $this->getRequest()->getUser();

        // Allow logged-in user to see their own unapproved comments.
        if ($user) {
            $query->orWhere(function ($query) use ($user) {
                $query->where('user_id', $user->getId())
                    ->where('is_approved', false);
            });
        }

        $query->withPublicationIds($publicationIds);
        $paginatedInfo = Repo::userComment()
            ->setPage($illuminateRequest->query('page') ?? 1)
            ->getPaginatedData($query);

        return response()->json($this->formatPaginatedResponseData($paginatedInfo), Response::HTTP_OK);
    }

    /**
     * Gets a list of comments. Accessible only to moderators(admins/managers).
     * Filters available via query params:
     * ```
     * publicationIds(array) - publication IDs to retrieve comments for.
     * userIds(array) - Include this to filter by user IDs
     * isReported(boolean) - Include this to filter comment based on if they were reported or not.
     * isApproved(boolean) - Include this to filter comments by approval status.
     * page(integer) - The pagination page to retrieve records from.
     * ```
     * Use the 'includeReports' query parameter to include associated reports. Used in UserCommentResource::toArray method.
     */
    public function getMany(Request $illuminateRequest): JsonResponse
    {
        $queryParams = $illuminateRequest->query();
        $query = UserComment::withContextIds([$this->getRequest()->getContext()->getId()]);

        foreach ($queryParams as $param => $value) {
            switch ($param) {
                case 'userIds':
                    $userIdsRaw = paramToArray($value ?? []);
                    $userIds = [];

                    foreach ($userIdsRaw as $userId) {
                        if (!filter_var($userId, FILTER_VALIDATE_INT)) {
                            return response()->json([
                                'error' => __('api.userComments.400.invalidUserId', ['userId' => $userId])
                            ], Response::HTTP_BAD_REQUEST);
                        }

                        $userIds[] = (int)$userId;
                    }

                    $query->withUserIds($userIds);
                    break;
                case 'isApproved':
                    $isApproved = PKPString::strictConvertToBoolean($value);
                    if ($isApproved === null) {
                        return response()->json([
                            'error' => __('api.userComments.400.invalidIsApproved', ['isApproved' => $value])
                        ], Response::HTTP_BAD_REQUEST);
                    }
                    $query->withIsApproved($isApproved);
                    break;
                case 'isReported':
                    $isReported = PKPString::strictConvertToBoolean($value);
                    if ($isReported === null) {
                        return response()->json([
                            'error' => __('api.userComments.400.invalidIsReported', ['$isReported' => $value])
                        ], Response::HTTP_BAD_REQUEST);
                    }
                    $query->withIsReported($isReported);
                    break;
                case 'publicationIds':
                    $publicationIdsRaw = paramToArray($queryParams['publicationIds'] ?? []);
                    $publicationIds = [];

                    foreach ($publicationIdsRaw as $id) {
                        if (!filter_var($id, FILTER_VALIDATE_INT)) {
                            return response()->json([
                                'error' => __('api.publication.400.invalidPublicationId', ['publicationId' => $id])
                            ], Response::HTTP_BAD_REQUEST);
                        }

                        $publicationIds[] = (int)$id;
                    }
                    $query->withPublicationIds($publicationIds);
                    break;
            }
        }

        $paginatedInfo = Repo::userComment()
            ->setPage($queryParams['page'] ?? 1)
            ->getPaginatedData($query);

        return response()->json($this->formatPaginatedResponseData($paginatedInfo), Response::HTTP_OK);
    }

    /**
     * Get a single comment by ID
     * Use the 'includeReports' query parameter to include associated reports. Used in UserCommentResource::toArray method.
     */
    public function get(Request $illuminateRequest): JsonResponse
    {
        $commentId = (int)$illuminateRequest->route('commentId');
        $comment = UserComment::query()->find($commentId);

        if (!$comment) {
            return response()->json(['error' => __('api.404.resourceNotFound')], Response::HTTP_NOT_FOUND);
        }

        return response()->json(new UserCommentResource($comment), Response::HTTP_OK);
    }

    /** Add a new comment */
    public function submit(AddComment $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();
        $context = $request->getContext();
        $currentUser = $request->getUser();
        $requestBody = $illuminateRequest->validated();

        $publicationId = (int)$requestBody['publicationId'];
        $commentText = $requestBody['commentText'];

        $createdComment = UserComment::query()->create(
            [
                'userId' => $currentUser->getId(),
                'contextId' => $context->getId(),
                'publicationId' => $publicationId,
                'commentText' => $commentText,
                'isApproved' => false,
            ]
        );

        $this->notifyModerators(
            assocId: $createdComment->id,
            assocType: Application::ASSOC_TYPE_COMMENT,
            notificationType: Notification::NOTIFICATION_TYPE_USER_COMMENT_POSTED
        );

        return response()->json(new UserCommentResource($createdComment), Response::HTTP_OK);
    }

    /** Delete a comment by ID*/
    public function delete(Request $illuminateRequest): JsonResponse
    {
        $commentId = (int)$illuminateRequest->route('commentId');
        $request = $this->getRequest();
        $user = $request->getUser();
        $comment = UserComment::query()->find($commentId);

        if (!$comment) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        $isCommentOwner = $comment->userId === $user->getId();

        if (!Repo::userComment()->isModerator($user) && !$isCommentOwner) {
            return response()->json([
                'error' => __('api.403.unauthorized'),
            ], Response::HTTP_FORBIDDEN);
        }

        $comment->delete();

        return response()->json([], Response::HTTP_OK);
    }

    /**
     * Set the approval status of a comment.
     */
    public function setApproval(Request $illuminateRequest): JsonResponse
    {
        $isApproved = $illuminateRequest->input('approved');

        if (isset($isApproved) && !is_bool($isApproved)) {
            $isApproved = PKPString::strictConvertToBoolean($isApproved);
        }

        if ($isApproved === null) {
            return response()->json([
                'error' => __('api.userComments.400.invalidIsApproved', ['isApproved' => $illuminateRequest->input('approved')])
            ], Response::HTTP_BAD_REQUEST);
        }

        $commentId = (int)$illuminateRequest->route('commentId');
        $comment = UserComment::query()->find($commentId);

        if (!$comment) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        $comment->isApproved = $isApproved;
        $comment->approvedAt = $isApproved ? now() : null;
        $comment->approvedByUserId = $isApproved ? $this->getRequest()->getUser()->getId() : null;

        $comment->save();

        return response()->json(new UserCommentResource($comment), Response::HTTP_OK);
    }

    /** Report a comment */
    public function submitReport(AddReport $illuminateRequest): JsonResponse
    {
        $requestBody = $illuminateRequest->validated();
        $note = $requestBody['note'];
        $commentId = (int)$illuminateRequest->route('commentId');
        $comment = UserComment::query()->find($commentId);
        $reportId = Repo::userComment()->addReport($comment, $this->getRequest()->getUser(), $note);
        $report = UserCommentReport::query()->find($reportId);


        $this->notifyModerators(
            assocId: $report->id,
            assocType: Application::ASSOC_TYPE_COMMENT_REPORT,
            notificationType: Notification::NOTIFICATION_TYPE_USER_COMMENT_REPORTED
        );
        return response()->json(new UserCommentReportResource($report), Response::HTTP_OK);
    }

    /** Delete a report by ID */
    public function deleteReport(Request $illuminateRequest): JsonResponse
    {
        $reportId = (int)$illuminateRequest->route('reportId');
        $commentId = (int)$illuminateRequest->route('commentId');

        $report = UserCommentReport::withCommentIds([$commentId])
            ->withReportIds([$reportId])
            ->first();

        if (!$report) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        $report->delete();

        return response()->json([], Response::HTTP_OK);
    }

    /**
     * Delete all reports made against a comment.
     */
    public function deleteReports(Request $illuminateRequest): JsonResponse
    {
        $commentId = (int)$illuminateRequest->route('commentId');
        $comment = UserComment::query()->find($commentId);

        if (!$comment) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        UserCommentReport::withCommentIds([$commentId])->delete();

        return response()->json([], Response::HTTP_OK);
    }

    /** Get a single report by ID */
    public function getReport(Request $illuminateRequest): JsonResponse
    {
        $reportId = (int)$illuminateRequest->route('reportId');
        $commentId = (int)$illuminateRequest->route('commentId');

        $report = UserCommentReport::query()
            ->withCommentIds([$commentId])
            ->withReportIds([$reportId])
            ->first();

        if (!$report) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json(new UserCommentReportResource($report), Response::HTTP_OK);
    }

    /**
     * Get all reports for a comment
     * Accepts the following query parameters:
     * ```
     * page(optional, integer) - The pagination page to retrieve records from.
     * ```
     */
    public function getReports(Request $illuminateRequest): JsonResponse
    {
        $commentId = (int)$illuminateRequest->route('commentId');
        $comment = UserComment::query()->find($commentId);

        if (!$comment) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        $query = UserCommentReport::query()->where('user_comment_id', $commentId);
        $paginatedInfo = Repo::userComment()
            ->setPage($illuminateRequest->query('page') ?? 1)
            ->getPaginatedData($query);

        return response()->json($this->formatPaginatedResponseData($paginatedInfo), Response::HTTP_OK);
    }

    /**
     * Notify moderators about a comment-related event.
     */
    private function notifyModerators(int $assocId, int $assocType, int $notificationType): void
    {
        $context = $this->getRequest()->getContext();
        $notificationManager = new NotificationManager();

        $moderators = Repo::user()
            ->getCollector()
            ->filterByRoleIds([
                Role::ROLE_ID_SITE_ADMIN,
                Role::ROLE_ID_MANAGER,
            ])
            ->filterByContextIds([$context->getId()])
            ->getMany();

        foreach ($moderators as $moderator) {
            $notificationManager->createNotification(
                userId: $moderator->getId(),
                notificationType: $notificationType,
                contextId: $context->getId(),
                assocType: $assocType,
                assocId: $assocId,
                level: Notification::NOTIFICATION_LEVEL_TASK
            );
        }
    }
}
