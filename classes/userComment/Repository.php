<?php

/**
 * @file classes/userComment/Repository.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Repository
 *
 * @ingroup userComment
 *
 * @brief A repository to manage comments.
 */

namespace PKP\userComment;

use APP\core\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use PKP\API\v1\comments\resources\UserCommentReportResource;
use PKP\API\v1\comments\resources\UserCommentResource;
use PKP\context\Context;
use PKP\core\PKPApplication;
use PKP\core\SettingsBuilder;
use PKP\security\Role;
use PKP\user\User;
use PKP\userComment\relationships\UserCommentReport;

class Repository
{
    private Request $request;
    private int $perPage;

    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->perPage = $request->getContext()->getData('itemsPerPage');
    }

    /**
     * @param UserComment $userComment - The user comment to add the report to
     * @param User $user - The user reporting the comment
     * @param string $note - The note to attach to the report
     *
     * @return int - The ID of the newly created report
     */
    public function addReport(UserComment $userComment, User $user, string $note): int
    {
        $report = UserCommentReport::query()->create([
            'userCommentId' => $userComment->id,
            'userId' => $user->getId(),
            'note' => $note,
        ]);

        return $report->id;
    }

    /**
     * Accepts a model query builder and returns a paginated collection of the data.
     *
     * @param SettingsBuilder|Builder $query - The query builder used to get the paginated records. The underlying model of the builder must be either UserComment or UserCommentReport.
     */
    public function getPaginatedData(SettingsBuilder|Builder $query): LengthAwarePaginator
    {
        $resourceClass = match (true) {
            $query->getModel() instanceof UserComment => UserCommentResource::class,
            $query->getModel() instanceof UserCommentReport => UserCommentReportResource::class,
            default => throw new \InvalidArgumentException('Unsupported model type: ' . get_class($query->getModel())),
        };

        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $sanitizedPage = $currentPage - 1;
        $offsetRows = $this->perPage * $sanitizedPage;
        $total = $query->count();

        $data = $query
            ->orderBy('created_at', 'DESC')
            ->skip($offsetRows)
            ->take($this->perPage)
            ->get();

        return new LengthAwarePaginator(
            $resourceClass::collection($data),
            $total,
            $this->perPage
        );
    }

    public function setPage(int $page): static
    {
        LengthAwarePaginator::currentPageResolver(fn () => $page);
        return $this;
    }

    /**
     * Check if a user is a moderator (admin/manager).
     */
    public function isModerator(User $user, ?Context $context = null): bool
    {
        $context = $context ?: $this->request->getContext();
        return $user->hasRole([Role::ROLE_ID_MANAGER], $context->getId()) || $user->hasRole([Role::ROLE_ID_SITE_ADMIN], PKPApplication::SITE_CONTEXT_ID);
    }

    /**
     * Get the number of items per page for pagination.
     */
    public function getPerPage(): int
    {
        return $this->perPage;
    }
}
