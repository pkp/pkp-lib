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
            'suggesting_user_id'    => 'integer',
            'submission_id'         => 'integer',
            'email'                 => 'string',
            'orcid_id'              => 'string',
            'approved_at'           => 'datetime',
            'approver_id'           => 'integer',
            'reviewer_id'           => 'integer',
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

    public function getSettings(): array
    {
        return [
            'familyName',
            'givenName',
            'affiliation',
            'suggestionReason',
        ];
    }

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

    public function markAsApprove(Carbon $approvedAtTimestamp, ?int $reviewerId = null, ?int $approverId = null): bool
    {
        return (bool)$this->update([
            'approvedAt' => $approvedAtTimestamp,
            'reviewerId' => $reviewerId,
            'approverId' => $approverId,
        ]);
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

    public function scopeWithApproved(Builder $query, bool $hasApproved = true): Builder
    {
        return $query->when(
            $hasApproved,
            fn (Builder $query): Builder => $query->whereNotNull('approved_at'),
            fn (Builder $query): Builder => $query->whereNull('approved_at')
        );
    }
}
