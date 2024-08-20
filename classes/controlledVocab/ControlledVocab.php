<?php

/**
 * @file classes/controlledVocab/ControlledVocab.php
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
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Builder;
use PKP\facades\Locale;

class ControlledVocab extends Model
{
    use HasCamelCasing;

    protected $table = 'controlled_vocabs';
    protected $primaryKey = 'controlled_vocab_id';

    protected $guarded = [
        'controlled_vocab_id',
    ];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

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
        );
    }

    /**
     * Compatibility function for including note IDs in grids.
     *
     * @deprecated 3.5.0 Use $model->id instead. Can be removed once the DataObject pattern is removed.
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Compatibility function for including notes in grids.
     *
     * @deprecated 3.5. Use $model or $model->$field instead. Can be removed once the DataObject pattern is removed.
     */
    public function getData(?string $field): mixed
    {
        return $field ? $this->$field : $this;
    }

    /**
     * Scope a query to only include notes with a specific user ID.
     */
    public function scopeWithSymbolic(Builder $query, string $symbolic): Builder
    {
        return $query->where(DB::raw('LOWER(symbolic)'), strtolower($symbolic));
    }

    /**
     * Scope a query to only include notes with a specific assoc type and assoc ID.
     */
    public function scopeWithAssoc(Builder $query, int $assocType, int $assocId): Builder
    {
        return $query
            ->where('assoc_type', $assocType)
            ->where('assoc_id', $assocId);
    }

    /**
     * Get a list of controlled vocabulary options.
     *
     * @param string $settingName optional
     * @return array $controlledVocabEntryId => name
     */
    public function enumerate(string $settingName = 'name'): array
    {    
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
                ),
                DB::raw(
                    'COALESCE (l.setting_type, p.setting_type, n.setting_type) as setting_type'
                ),
            ])
            ->where('e.controlled_vocab_id', $this->id)
            ->orderBy('e.seq')
            ->get()
            ->pluck('setting_value', 'controlled_vocab_entry_id')
            ->toArray();
    }
}
