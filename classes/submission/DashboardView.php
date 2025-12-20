<?php

/**
 * @file classes/submission/DashboardView.php
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2003-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DashboardView
 *
 * @ingroup submission
 *
 * @brief class representing dashboard view which groups/filters submissions
 */

namespace PKP\submission;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PKP\security\Role;
use PKP\submission\Collector as SubmissionCollector;
use PKP\submission\reviewAssignment\Collector as ReviewAssignmentCollector;

class DashboardView
{
    public const TYPE_ASSIGNED = 'assigned-to-me';
    public const TYPE_ACTIVE = 'active';
    public const TYPE_NEEDS_EDITOR = 'needs-editor';
    public const TYPE_SUBMISSION = 'initial-review';
    public const TYPE_NEEDS_REVIEWS = 'needs-reviews';
    public const TYPE_AWAITING_REVIEWS = 'awaiting-reviews';
    public const TYPE_REVIEWS_SUBMITTED = 'reviews-submitted';
    public const TYPE_REVIEWS_OVERDUE = 'reviews-overdue';
    public const TYPE_REVISIONS_REQUESTED = 'revisions-requested';
    public const TYPE_REVISIONS_SUBMITTED = 'revisions-submitted';
    public const TYPE_INCOMPLETE_SUBMISSIONS = 'incomplete-submissions';
    public const TYPE_REVIEW_EXTERNAL = 'external-review';
    public const TYPE_REVIEW_ALL = 'review-all'; // OMP only
    public const TYPE_COPYEDITING = 'copyediting';
    public const TYPE_PRODUCTION = 'production';
    public const TYPE_SCHEDULED = 'scheduled';
    public const TYPE_PUBLISHED = 'published';
    public const TYPE_DECLINED = 'declined';

    public const TYPE_REVIEWER_ACTION_REQUIRED = 'reviewer-action-required';
    public const TYPE_REVIEWER_ASSIGNMENTS_ALL = 'reviewer-assignments-all';
    public const TYPE_REVIEWER_ASSIGNMENTS_COMPLETED = 'reviewer-assignments-completed';
    public const TYPE_REVIEWER_ASSIGNMENTS_DECLINED = 'reviewer-assignments-declined';
    public const TYPE_REVIEWER_ASSIGNMENTS_PUBLISHED = 'reviewer-assignments-published';
    public const TYPE_REVIEWER_ASSIGNMENTS_ARCHIVED = 'reviewer-assignments-archived';

    // The number of submissions in the view
    protected int $count = 0;

    public function __construct(
        protected string $type, // View type, used also as the unique ID of the view's front-end part
        protected string $name, // View name as a localized string
        protected array $roles, // User roles having access to the view
        protected SubmissionCollector|ReviewAssignmentCollector $submissionCollector, // Collector with correspondent filters applied
        protected ?string $op = null, // Dashboard handler operation to retrieve filtered submissions
        protected ?array $queryParams = null // Optional query parameters
    ) {

    }

    /**
     * Get types of views, see self::TYPE_* constants
     */
    public static function getTypes(): Collection
    {
        $reflection = new \ReflectionClass(static::class);
        $constants = $reflection->getConstants(\ReflectionClassConstant::IS_PUBLIC);
        $types = collect();
        foreach ($constants as $name => $value) {
            if (!Str::startsWith($name, 'TYPE_')) {
                continue;
            }

            $types->push($value);
        }
        return $types;
    }

    /**
     * Get the collector with filters applied to retrieve submissions for the view
     */
    public function getCollector(): SubmissionCollector|ReviewAssignmentCollector
    {
        return $this->submissionCollector;
    }

    /**
     * Set the count of the submissions in the view
     */
    public function setCount(int $count): void
    {
        $this->count = $count;
    }

    public function getCount(): int
    {
        return $this->count;
    }

    /**
     * @return array<Role>
     */
    public function getRoles(): array
    {
        return $this->roles;
    }

    /**
     * Get view data as an array
     */
    public function getData(): array
    {
        $data = [
            'id' => $this->type,
            'name' => $this->name,
            'count' => $this->count,
        ];

        if (!is_null($this->op)) {
            $data['op'] = $this->op;
        }

        if (!is_null($this->queryParams)) {
            $data['queryParams'] = $this->queryParams;
        }

        return $data;
    }
}
