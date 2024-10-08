<?php

/**
 * @file lib/pkp/classes/controlledVocab/ControlledVocab.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ControlledVocab
 *
 * @brief ControlledVocab model class
 */

namespace PKP\controlledVocab;

use Eloquence\Behaviours\HasCamelCasing;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Builder;
use PKP\controlledVocab\ControlledVocabEntry;
use PKP\facades\Locale;

class ControlledVocab extends Model
{
    use HasCamelCasing;

    /**
     * List of pre defined vocab symbolic as const in format of CONTROLLED_VOCAB_*
     */
    public const CONTROLLED_VOCAB_SUBMISSION_AGENCY = 'submissionAgency';
    public const CONTROLLED_VOCAB_SUBMISSION_DISCIPLINE = 'submissionDiscipline';
    public const CONTROLLED_VOCAB_SUBMISSION_KEYWORD = 'submissionKeyword';
    public const CONTROLLED_VOCAB_SUBMISSION_LANGUAGE = 'submissionLanguage';
    public const CONTROLLED_VOCAB_SUBMISSION_SUBJECT = 'submissionSubject';

    /**
     * @copydoc \Illuminate\Database\Eloquent\Model::$table
     */
    protected $table = 'controlled_vocabs';

    /**
     * @copydoc \Illuminate\Database\Eloquent\Model::$primaryKey
     */
    protected $primaryKey = 'controlled_vocab_id';

    /**
     * @copydoc \Illuminate\Database\Eloquent\Concerns\GuardsAttributes::$guarded
     */
    protected $guarded = [
        'controlled_vocab_id',
    ];

    /**
     * @copydoc \Illuminate\Database\Eloquent\Concerns\HasTimestamps::$timestamps
     */
    public $timestamps = false;

    /**
     * @copydoc \Illuminate\Database\Eloquent\Concerns\HasAttributes::casts
     */
    protected function casts(): array
    {
        return [
            'symbolic' => 'string',
            'assoc_type' => 'integer',
            'assoc_id' => 'integer',
        ];
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

    /**
     * Get the list of pre defined vocab symbolics
     */
    public static function getDefinedVocabSymbolic(): array
    {
        return [
            static::CONTROLLED_VOCAB_SUBMISSION_AGENCY,
            static::CONTROLLED_VOCAB_SUBMISSION_DISCIPLINE,
            static::CONTROLLED_VOCAB_SUBMISSION_KEYWORD,
            static::CONTROLLED_VOCAB_SUBMISSION_LANGUAGE,
            static::CONTROLLED_VOCAB_SUBMISSION_SUBJECT,
        ];
    }

    /**
     * Check if a provided vocab symbolic defined in pre defined symbolic list
     */
    public static function hasDefinedVocabSymbolic(string $vocab): bool
    {
        return in_array($vocab, static::getDefinedVocabSymbolic());
    }

    /**
     * Get all controlled vocab entries for this controlled vocab
     */
    public function controlledVocabEntries(): HasMany
    {
        return $this->hasMany(ControlledVocabEntry::class, 'controlled_vocab_id', 'controlled_vocab_id');
    }

    /**
     * Scope a query to only include vocabs with a specific symbolic.
     */
    public function scopeWithSymbolic(Builder $query, string $symbolic): Builder
    {
        return $query->where('symbolic', $symbolic);
    }

    /**
     * Scope a query to only include vocabs with a specific assoc type and assoc ID.
     */
    public function scopeWithAssoc(Builder $query, int $assocType, int $assocId): Builder
    {
        return $query
            ->where('assoc_type', $assocType)
            ->where('assoc_id', $assocId);
    }

    /**
     * Scope a query to only include vocabs associated with given context id
     */
    public function scopeWithContextId(Builder $query, int $contextId): Builder
    {
        return $query
            ->where(
                fn ($query) => $query
                    ->select('context_id')
                    ->from('submissions')
                    ->whereColumn(
                        DB::raw(
                            "(SELECT publications.submission_id 
                            FROM publications 
                            INNER JOIN {$this->table} 
                            ON publications.publication_id = {$this->table}.assoc_id 
                            LIMIT 1)"
                        ),
                        '=',
                        'submissions.submission_id'
                    ), 
                $contextId
            );
    }

    /**
     * Get a list of controlled vocabulary options.
     *
     * @return array $controlledVocabEntryId => name
     */
    public function enumerate(?string $settingName = null): array
    {    
        $settingName ??= $this->symbolic;

        return DB::table('controlled_vocab_entries AS e')
            ->leftJoin(
                'controlled_vocab_entry_settings AS l',
                fn (JoinClause $join) => $join
                    ->on('l.controlled_vocab_entry_id', '=', 'e.controlled_vocab_entry_id')
                    ->where('l.setting_name', $settingName)
                    ->where('l.locale', Locale::getLocale())
            )
            ->leftJoin(
                'controlled_vocab_entry_settings AS p',
                fn (JoinClause $join) => $join
                    ->on('p.controlled_vocab_entry_id', '=', 'e.controlled_vocab_entry_id')
                    ->where('p.setting_name', $settingName)
                    ->where('p.locale', Locale::getPrimaryLocale())
            )
            ->leftJoin(
                'controlled_vocab_entry_settings AS n',
                fn (JoinClause $join) => $join
                    ->on('n.controlled_vocab_entry_id', '=', 'e.controlled_vocab_entry_id')
                    ->where('n.setting_name', $settingName)
                    ->where('n.locale', '')
            )
            ->select([
                'e.controlled_vocab_entry_id',
                DB::raw(
                    'COALESCE (l.setting_value, p.setting_value, n.setting_value) as setting_value'
                )
            ])
            ->where('e.controlled_vocab_id', $this->id)
            ->orderBy('e.seq')
            ->get()
            ->pluck('setting_value', 'controlled_vocab_entry_id')
            ->toArray();
    }
}
