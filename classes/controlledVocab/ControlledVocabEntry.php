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
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use PKP\user\interest\UserInterest;
use PKP\core\traits\ModelWithSettings;
use PKP\controlledVocab\ControlledVocab;
use PKP\controlledVocab\ControlledVocabEntryMatch;

class ControlledVocabEntry extends Model
{
    use ModelWithSettings;

    public const CONTROLLED_VOCAB_ENTRY_IDENTIFIER = 'identifier';
    public const CONTROLLED_VOCAB_ENTRY_SOURCE = 'source';
    
    /**
     * @copydoc \Illuminate\Database\Eloquent\Model::$table
     */
    protected $table = 'controlled_vocab_entries';

    /**
     * @copydoc \Illuminate\Database\Eloquent\Model::$primaryKey
     */
    protected $primaryKey = 'controlled_vocab_entry_id';

    /**
     * @copydoc \Illuminate\Database\Eloquent\Concerns\GuardsAttributes::$guarded
     */
    protected $guarded = [
        'controlledVocabEntryId',
    ];

    /**
     * @copydoc \Illuminate\Database\Eloquent\Concerns\HasTimestamps::$timestamps
     */
    public $timestamps = false;

    /**
     * @copydoc \PKP\core\traits\ModelWithSettings::getSettingsTable
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
            'controlled_vocab_entry_id' => 'integer',
            'controlled_vocab_id' => 'integer',
            'seq' => 'float',
            self::CONTROLLED_VOCAB_ENTRY_IDENTIFIER => 'string',
            self::CONTROLLED_VOCAB_ENTRY_SOURCE => 'string',
        ];
    }

    /**
     * @copydoc \PKP\core\traits\ModelWithSettings::getSchemaName
     */
    public static function getSchemaName(): ?string
    {
        return null;
    }

    /**
     * @copydoc \PKP\core\traits\ModelWithSettings::getMultilingualProps
     */
    public function getMultilingualProps(): array
    {
        return array_merge($this->multilingualProps, ['name']);
    }

    /**
     * @copydoc \PKP\core\traits\ModelWithSettings::getSettings
     */
    public function getSettings(): array
    {
        return array_merge($this->settings, ['name', self::CONTROLLED_VOCAB_ENTRY_IDENTIFIER, self::CONTROLLED_VOCAB_ENTRY_SOURCE]);
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
     * Scope a query to only include entries for a specific locales
     */
    public function scopeWithLocales(Builder $query, array $locales): Builder
    {
        return $query->whereExists(
            fn ($query) => $query
                ->select($this->primaryKey)
                ->from($this->getSettingsTable())
                ->whereColumn(
                    "{$this->getSettingsTable()}.{$this->primaryKey}",
                    "{$this->getTable()}.{$this->primaryKey}"
                )
                ->whereIn(DB::raw("{$this->getSettingsTable()}.locale"), $locales)
        );
    }

    /**
     * Scope a query to only include entries for a specific setting name and values
     */
    public function scopeWithSettings(Builder $query, string $settingName, array $settingValue): Builder
    {
        return $query->whereExists(
            fn ($query) => $query
                ->select($this->primaryKey)
                ->from($this->getSettingsTable())
                ->whereColumn(
                    "{$this->getSettingsTable()}.{$this->primaryKey}",
                    "{$this->getTable()}.{$this->primaryKey}"
                )
                ->where(
                    "{$this->getSettingsTable()}.setting_name",
                    '=',
                    $settingName
                )
                ->whereIn(
                    DB::raw("{$this->getSettingsTable()}.setting_value"),
                    $settingValue
                )
        );
    }

    /**
     * Scope a query to only include entries for a specific setting name and value with exact or partial match
     */
    public function scopeWithSetting(
        Builder $query,
        string $settingName,
        string $settingValue,
        ControlledVocabEntryMatch $match = ControlledVocabEntryMatch::EXACT
    ): Builder
    {
        return $query->whereExists(
            fn ($query) => $query
                ->select($this->primaryKey)
                ->from($this->getSettingsTable())
                ->whereColumn(
                    "{$this->getSettingsTable()}.{$this->primaryKey}",
                    "{$this->getTable()}.{$this->primaryKey}"
                )
                ->where(
                    "{$this->getSettingsTable()}.setting_name",
                    '=',
                    $settingName
                )
                ->where(
                    "{$this->getSettingsTable()}.setting_value",
                    $match->operator(),
                    $match->searchKeyword($settingValue)
                )
        );
    }

    /**
     * Get entry related data
     */
    public function getEntryData(string $locale = null): ?array
    {
        $multilingualProps = array_flip($this->getMultilingualProps());
        $attributes = Arr::mapWithKeys($this->getSettings(), function (string $prop) use ($locale, $multilingualProps): array {
            $propData = $this->getAttribute($prop);
            $data = isset($multilingualProps[$prop]) && $locale ? $propData[$locale] ?? null : $propData;
            return $data ? [$prop => $data] : [];
        });

        if (!isset($attributes['name'])) {
            return null;
        }

        return $attributes;
    }
}
