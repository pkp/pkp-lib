<?php

/**
 * @file classes/submission/genre/Genre.php
 *
 * Copyright (c) 2014-2014 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class \PKP\genre\Genre
 *
 * @brief Eloquent Model for Genre
 */


namespace PKP\submission\genre;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use PKP\core\traits\ModelWithSettings;
use PKP\submissionFile\SubmissionFile;
use Illuminate\Database\Eloquent\Casts\Attribute;
use PKP\services\PKPSchemaService;
use PKP\db\XMLDAO;

class Genre extends Model
{
    use ModelWithSettings;

    public const GENRE_CATEGORY_DOCUMENT = 1;
    public const GENRE_CATEGORY_ARTWORK = 2;
    public const GENRE_CATEGORY_SUPPLEMENTARY = 3;

    protected $table = 'genres';
    protected $primaryKey = 'genre_id';
    public    $timestamps = false;
    protected $guarded = ['genreId','id'];

    public function getSettingsTable(): string
    {
        return 'genre_settings';
    }

    public static function getSchemaName(): ?string
    {
        return PKPSchemaService::SCHEMA_GENRE;
    }

    public function submissionFiles(): HasMany
    {
        return $this->hasMany(SubmissionFile::class, 'genre_id', 'genre_id');
    }


    // Accessors and Mutators
    protected function entryKey(): Attribute
    {
        return Attribute::make(
            get: fn($value) => strtoupper($value),
            set: fn($value) => strtolower($value)
        );
    }

    protected function enabled(): Attribute
    {
        return Attribute::make(
            get: fn($value) => (bool) $value,
            set: fn($value) => (int) $value
        );
    }

    // Scopes

    public function scopeEnabled(EloquentBuilder $query): EloquentBuilder
    {
        return $query->where('enabled', 1);
    }

    public function scopeDependent(EloquentBuilder $query, bool $dependent = true): EloquentBuilder
    {
        return $query->where('dependent', (int) $dependent);
    }

    public function scopeSupplementary(EloquentBuilder $query, bool $supplementary = true): EloquentBuilder
    {
        return $query->where('supplementary', (int) $supplementary);
    }

    public function scopeRequired(EloquentBuilder $query, bool $required = true): EloquentBuilder
    {
        return $query->where('required', (int) $required);
    }

    public function scopeInContext(EloquentBuilder $query, int $contextId): EloquentBuilder
    {
        return $query->where('context_id', $contextId);
    }

    // Business Logic

    /**
     * Find a Genre by its ID, optionally restricting to one context_id.
     */
    public static function findById(int $id, ?int $contextId = null): ?self
    {
        $query = self::where('genre_id', $id);
        if ($contextId !== null) {
            $query->inContext($contextId);
        }
        return $query->first();
    }

    /**
     * Find a Genre by its entry_key, optionally restricting to one context_id.
     */
    public static function findByKey(string $key, ?int $contextId = null): ?self
    {
        $lowerKey = strtolower($key);
        $query = self::where('entry_key', $lowerKey);
        if ($contextId !== null) {
            $query->inContext($contextId);
        }
        return $query->first();
    }


    /**
     * Check if this is one of the default genres
     */
    public function isDefault(): bool
    {
        $defaultKeys = $this->getDefaultKeys();
        return in_array($this->getAttribute('entry_key'), $defaultKeys, true);
    }

    /**
     * Load the built‐in default genre keys from registry/genres.xml.
     * */
    protected function getDefaultKeys(): array
    {
        $xmlDao = new XMLDAO();
        $data   = $xmlDao->parseStruct('registry/genres.xml', ['genre']);
        if (empty($data['genre'])) {
            return [];
        }
        $keys = [];
        foreach ($data['genre'] as $entry) {
            $attrs = $entry['attributes'];
            $keys[] = $attrs['key'];
        }
        return $keys;
    }
}
