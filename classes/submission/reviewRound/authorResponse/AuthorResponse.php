<?php

namespace PKP\submission\reviewRound\authorResponse;

use APP\facades\Repo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use PKP\core\traits\ModelWithSettings;
use PKP\db\DAORegistry;

class AuthorResponse extends Model
{
    use ModelWithSettings;

    protected $table = 'review_round_author_responses';
    protected $primaryKey = 'response_id';
    protected string $settingsTable = 'review_round_author_response_settings';

    protected $fillable = [
        'reviewRoundId',
        'userId',
        'authorResponse'
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
     * Authors associated with this response (via review_author_response_authors table).
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
