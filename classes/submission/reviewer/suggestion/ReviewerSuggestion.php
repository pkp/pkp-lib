<?php

/**
 * @file classes/submission/reviewer/suggestion/ReviewerSuggestion.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewerSuggestion
 *
 * @brief 
 */

namespace PKP\submission\reviewer\suggestion;

use Illuminate\Database\Eloquent\Model;
use APP\facades\Repo;
use Carbon\Carbon;
use Eloquence\Behaviours\HasCamelCasing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;


class ReviewerSuggestion extends Model
{
    use HasCamelCasing;

    protected $table = 'reviewer_suggestions';
    protected $primaryKey = 'reviewer_suggestion_id';

    protected $fillable = [
        
    ];

    protected function casts(): array
    {
        return [
            'userId'        => 'int',
            'submissionId'  => 'int',
            'email'         => 'string',
            'orcidId'       => 'string',
            'approvedAt'    => 'datetime',
            'stageId'       => 'int',
            'approverId'    => 'int',
            'reviewerId'    => 'int',
        ];
    }

    public function hasApproved(): ?Carbon
    {
        return $this->approvedAt;
    }

    /**
     * Accessor and Mutator for primary key => id
     */
    protected function id(): Attribute
    {
        return Attribute::make(
            get: fn($value, $attributes) => $attributes[$this->primaryKey] ?? null,
            set: fn($value) => [$this->primaryKey => $value],
        );
    }

    /**
     * Accessor for submission.
     * Should replace with relationship once Submission is converted to an Eloquent Model.
     */
    protected function submission(): Attribute
    {
        return Attribute::make(
            get: fn () => Repo::submission()->get($this->submissionId, true),
        );
    }

    /**
     * Accessor for user.
     * Should replace with relationship once User is converted to an Eloquent Model.
     */
    protected function user(): Attribute
    {
        return Attribute::make(
            get: fn () => Repo::user()->get($this->userId, true),
        );
    }

    /**
     * Accessor for user.
     * Should replace with relationship once User is converted to an Eloquent Model.
     */
    protected function approver(): ?Attribute
    {
        return Attribute::make(
            get: fn () => $this->approverId ? Repo::user()->get($this->approverId, true) : null,
        );
    }

    /**
     * Accessor for user.
     * Should replace with relationship once User is converted to an Eloquent Model.
     */
    protected function reviewer(): ?Attribute
    {
        return Attribute::make(
            get: fn () => $this->reviewerId ? Repo::user()->get($this->reviewerId, true) : null,
        );
    }

    public function scopeWithContextId(Builder $query, int $contextId): Builder
    {
        return $query->whereIn("submission_id", fn (Builder $query) => $query
            ->select("submission_id")
            ->from("submissions")
            ->where("context_id", $contextId)
        );
    }

    public function scopeWithSubmissionId(Builder $query, int $submissionId): Builder
    {
        return $query->where("submission_id", $submissionId);
    }

    public function scopeWithUserId(Builder $query, int $userId): Builder
    {
        return $query->where("user_id", $userId);
    }

    public function scopeWithStageId(Builder $query, int $stageId): Builder
    {
        return $query->where("stage_id", $stageId);
    }
}
