<?php

/**
 * @file classes/controlledVocab/ControlledVocabEntry.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ControlledVocabEntry
 *
 * @brief ControlledVocabEntry model class
 */

namespace PKP\controlledVocab;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use PKP\user\interest\UserInterest;
use PKP\core\traits\ModelWithSettings;
use Illuminate\Database\Eloquent\Model;
use PKP\controlledVocab\ControlledVocab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ControlledVocabEntry extends Model
{
    use ModelWithSettings;
    
    /**
     * @copydoc \Illuminate\Database\Eloquent\Model::$table
     */
    protected $table = 'controlled_vocab_entries';

    /**
     * @copydoc \Illuminate\Database\Eloquent\Model::$primaryKey
     */
    protected $primaryKey = 'controlled_vocab_entry_id';

    // TODO: Investigate why defining any guarded props causing no data to store in settings table
    /**
     * @copydoc \Illuminate\Database\Eloquent\Concerns\GuardsAttributes::$guarded
     */
    protected $guarded = [
        // 'id',
    ];

    /**
     * @copydoc \Illuminate\Database\Eloquent\Concerns\HasTimestamps::$timestamps
     */
    public $timestamps = false;

    /**
     * @inheritDoc
     */
    public function getSettingsTable(): string
    {
        return 'controlled_vocab_entry_settings';
    }

    /**
     * @copydoc \Illuminate\Database\Eloquent\Concerns\HasAttributes::casts
     */
    protected function casts(): array
    {
        return [
            'controlled_vocab_entry_id' => 'string',
            'controlled_vocab_id' => 'int',
            'seq' => 'float',
        ];
    }

    /**
     * @inheritDoc
     */
    public static function getSchemaName(): ?string
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function getMultilingualProps(): array
    {
        return array_merge(
            $this->multilingualProps,
            ControlledVocab::getDefinedVocabSymbolic(),
            Arr::wrap(UserInterest::CONTROLLED_VOCAB_INTEREST)
        );
    }

    /**
     * @inheritDoc
     */
    public function getSettings(): array
    {
        return array_merge(
            $this->settings,
            ControlledVocab::getDefinedVocabSymbolic(),
            Arr::wrap(UserInterest::CONTROLLED_VOCAB_INTEREST)
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

    /**
     * The controlled vocab associated with this controlled vocab entry
     */
    public function controlledVocab(): BelongsTo
    {
        return $this->belongsTo(ControlledVocab::class, 'controlled_vocab_id', 'controlled_vocab_id');
    }

    /**
     * The user interest associated with this controlled vocab entry
     */
    public function userInterest(): BelongsTo
    {
        return $this->belongsTo(UserInterest::class, 'controlled_vocab_entry_id', 'controlled_vocab_entry_id');
    }

    /**
     * Scope a query to only include entries for a specific controlled vocab id
     */
    public function scopeWithControlledVocabId(Builder $query, int $controlledVocabId): Builder
    {
        return $query->where('controlled_vocab_id', $controlledVocabId);
    }

    /**
     * Scope a query to only include entries for a specific locale/s
     */
    public function scopeWithLocale(Builder $query, string|array $locale): Builder
    {
        if (is_array($locale)) {
            return $query->whereIn(
                DB::raw(
                    "(SELECT locale 
                    FROM {$this->getSettingsTable()} 
                    WHERE {$this->getSettingsTable()}.{$this->primaryKey} = {$this->table}.{$this->primaryKey} 
                    LIMIT 1)"
                ),
                $locale
            );
        }

        return $query->where(
            fn ($query) => $query
                ->select("locale")
                ->from("{$this->getSettingsTable()}")
                ->whereColumn(
                    "{$this->getSettingsTable()}.{$this->primaryKey}",
                    "{$this->table}.{$this->primaryKey}"
                )
                ->limit(1),
            $locale
        );
    }

    /**
     * Scope a query to only include entries for a specific setting name and value/s
     */
    public function scopeWithSetting(Builder $query, string $settingName, string|array $settingValue, bool $partial = true): Builder
    {
        if (is_array($settingValue)) {
            return $query->whereIn(
                DB::raw(
                    "(SELECT setting_value 
                    FROM {$this->getSettingsTable()} 
                    WHERE setting_name = '{$settingName}'
                    AND {$this->getSettingsTable()}.{$this->primaryKey} = {$this->table}.{$this->primaryKey}
                    LIMIT 1)"
                ),
                $settingValue
            );
        }

        return $query->where(
            fn ($query) => $query
                ->select('setting_value')
                ->from("{$this->getSettingsTable()}")
                ->where('setting_name', $settingName)
                ->whereColumn(
                    "{$this->getSettingsTable()}.{$this->primaryKey}",
                    "{$this->table}.{$this->primaryKey}"
                )
                ->limit(1),
            ($partial ? 'LIKE' : '='),
            ($partial ? "%{$settingValue}%" : $settingValue)
        );
    }
}
