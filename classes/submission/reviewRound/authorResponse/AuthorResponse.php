<?php

/**
 * @file classes/submission/reviewRound/authorResponse/AuthorResponse.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AuthorResponse
 *
 * @ingroup submission_reviewRound_authorResponse
 *
 * @brief Model class describing author response to reviewers' comments in a review round.
 */

namespace PKP\submission\reviewRound\authorResponse;

use APP\facades\Repo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use PKP\core\traits\ModelWithSettings;
use PKP\db\DAORegistry;

/**
 * @method static Builder withReviewRoundIds(array $reviewRoundIds) Filter responses by review round IDs.
 * @method static Builder withUserId(int $userId) Filter responses by user ID.
 * @method static Builder withDoiIds(array $doiIds) Filter responses by DOI IDs.
 */
class AuthorResponse extends Model
{
    use ModelWithSettings;

    protected $table = 'review_round_author_responses';
    protected $primaryKey = 'response_id';
    protected string $settingsTable = 'review_round_author_response_settings';

    protected $fillable = [
        'reviewRoundId',
        'userId',
        'authorResponse',
        'doiId'
    ];

    protected $casts = [
        'reviewRoundId' => 'int',
        'userId' => 'int',
        'doiId' => 'int'
    ];

    /**
     * @inheritDoc
     */
    public static function getSchemaName(): ?string
    {
        return null;
    }

    /**
     * @copydoc \PKP\core\traits\ModelWithSettings::getSettings
     */
    public function getSettings(): array
    {
        return [
            'authorResponse',
        ];
    }
    /**
     * @inheritDoc
     */
    public function getMultilingualProps(): array
    {
        return [
            'authorResponse',
        ];
    }
    /**
     * @inheritDoc
     */
    public function getSettingsTable(): string
    {
        return $this->settingsTable;
    }

    /**
     * Review round this response belongs to.
     */
    protected function reviewRound(): Attribute
    {
        return Attribute::make(
            get: function () {
                $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO');
                return $reviewRoundDao->getById($this->reviewRoundId);
            }
        )->shouldCache();
    }

    /**
     * DOI associated with this response.
     */
    protected function doi(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->doiId !== null ? Repo::doi()->get($this->doiId) : null
        )->shouldCache();
    }

    /**
     * User object of assigned author participant who submitted the response.
     */
    protected function submittedBy(): Attribute
    {
        return Attribute::make(
            get: function () {
                return Repo::user()->get($this->userId);
            }
        )->shouldCache();
    }

    /**
     * Whether this author response is directed at publicly available review assignment comments.
     */
    protected function isPublic(): Attribute
    {
        return Attribute::make(
            get: function () {
                $visibility = DB::table('review_assignments')
                    ->where('review_round_id', $this->review_round_id)
                    ->where('declined', 0)
                    ->where('cancelled', 0)
                    ->pluck('is_review_publicly_visible');

                return $visibility->isNotEmpty() && $visibility->every(fn ($visibility) => (bool) $visibility);
            }
        )->shouldCache();
    }

    /**
     * Authors associated with this response.
     */
    protected function associatedAuthors(): Attribute
    {
        return Attribute::make(
            get: function () {
                $authorIds = DB::table('review_round_author_response_authors')
                    ->where('response_id', $this->id)
                    ->pluck('author_id')
                    ->all();

                return array_map(fn ($authorId) => Repo::Author()->get($authorId), $authorIds);
            }
        )->shouldCache();
    }

    public function scopeWithReviewRoundIds(Builder $query, array $reviewRoundIds): Builder
    {
        return $query->whereIn('review_round_id', $reviewRoundIds);
    }

    public function scopeWithUserId(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeWithDoiIds(Builder $query, array $doiIds): Builder
    {
        return $query->whereIn('doi_id', $doiIds);
    }

    public function associateAuthorsToResponse(array $authorIds): bool
    {
        // First delete any existing associations
        DB::table('review_round_author_response_authors')
            ->where('response_id', $this->id)
            ->delete();

        // Then insert the new associations
        $rows = array_map(function ($authorId) {
            return [
                'response_id' => $this->id,
                'author_id' => $authorId,
            ];
        }, $authorIds);

        return DB::table('review_round_author_response_authors')->insert($rows);
    }
}
