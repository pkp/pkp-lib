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

use APP\facades\Repo;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use PKP\core\traits\ModelWithSettings;

class ReviewerSuggestion extends Model
{
    use ModelWithSettings;

    protected $table = 'reviewer_suggestions';
    protected $primaryKey = 'reviewer_suggestion_id';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'suggestingUserId'  => 'integer',
            'submissionId'      => 'integer',
            'email'             => 'string',
            'orcidId'           => 'string',
            'approvedAt'        => 'datetime',
            'stageId'           => 'integer',
            'approverId'        => 'integer',
            'reviewerId'        => 'integer',
        ];
    }

    public function getSettingsTable(): string
    {
        return 'reviewer_suggestion_settings';
    }

    public static function getSchemaName(): ?string
    {
        return null;
    }

    // TODO Add instution details as setting 
    public function getSettings(): array
    {
        return [
            'familyName',
            'givenName',
            'affiliation',
            'suggestionReason',
        ];
    }

    // TODO should the instution details be a multigingual prop
    public function getMultilingualProps(): array
    {
        return [
            'fullName',
            'familyName',
            'givenName',
            'affiliation',
            'suggestionReason',
        ];
    }

    public function hasApproved(): ?Carbon
    {
        return $this->approvedAt;
    }

    /**
     * Get the full name
     */
    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes) {
                $familyName = $this->familyName;
                return collect($this->givenName)
                    ->map(fn ($givenName, $locale) => $givenName. ' ' . $familyName[$locale])
                    ->toArray();
            }
        );
    }

    /**
     * Accessor and Mutator for primary key => id
     */
    protected function id(): Attribute
    {
        return Attribute::make(
            get: fn($value, $attributes) => $attributes[$this->primaryKey] ?? null,
            set: fn($value) => [$this->primaryKey => $value],
        )->shouldCache();
    }

    protected function submission(): Attribute
    {
        return Attribute::make(
            get: fn () => Repo::submission()->get($this->submissionId, true),
        )->shouldCache();
    }

    protected function suggestingUser(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->suggestingUserId
                ? Repo::user()->get($this->suggestingUserId, true)
                : null
        )->shouldCache();
    }

    protected function approver(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->approverId ? Repo::user()->get($this->approverId, true) : null,
        )->shouldCache();
    }

    protected function reviewer(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->reviewerId ? Repo::user()->get($this->reviewerId, true) : null,
        )->shouldCache();
    }

    public function scopeWithContextId(Builder $query, int $contextId): Builder
    {
        return $query
            ->whereIn('submission_id', fn (Builder $query) => $query
                ->select('submission_id')
                ->from('submissions')
                ->where('context_id', $contextId)
            );
    }

    public function scopeWithSubmissionIds(Builder $query, int|array $submissionIds): Builder
    {
        return $query->whereIn('submission_id', Arr::wrap($submissionIds));
    }

    public function scopeWithSuggestingUserIds(Builder $query, int|array $userIds): Builder
    {
        return $query->whereIn('suggesting_user_id', Arr::wrap($userIds));
    }

    public function scopeWithStageIds(Builder $query, int|array $stageIds): Builder
    {
        return $query->whereIn('stage_id', Arr::wrap($stageIds));
    }
}
