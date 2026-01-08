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

    public function getSettings(): array
    {
        return ['name'];
    }

    public function getMultilingualProps(): array
    {
        return ['name'];
    }

    public static function getSchemaName(): ?string
    {
        return null;
    }

    public function submissionFiles(): HasMany
    {
        return $this->hasMany(SubmissionFile::class, 'genre_id', 'genre_id');
    }

    public function getSequence(): int
    {
        return (int) $this->getAttribute('seq');
    }

    public function setSequence(int $seq): void
    {
        $this->setAttribute('seq', $seq);
    }

    /**
     * Backwards compatible layer for DataObjectGridCellProvider, etc.
     */
    public function getData(string $key)
    {
        return $this->getAttribute($key);
    }

    public function setData(string $key, $value): void
    {
        $this->setAttribute($key, $value);
    }

    // Accessors and Mutators
    protected function entryKey(): Attribute
    {
        return Attribute::make(
            get: fn($value) => strtoupper($value),
            set: fn($value) => strtolower($value)
        );
    }
    
    protected static function booted(): void
    {
        parent::booted();

        // always order by seq
        static::addGlobalScope('orderBySeq', function (EloquentBuilder $builder) {
            $builder->orderBy('seq');
        });

        // ensure seq is set on insert
        static::creating(function ($genre) {
            if ($genre->getAttribute('seq') === null) {
                $maxSeq = (int) self::where(
                    'context_id',
                    $genre->getAttribute('context_id')
                )->max('seq');

                $genre->setAttribute('seq', $maxSeq + 1);
            }
        });
    }

    protected function enabled(): Attribute
    {
        return Attribute::make(
            get: fn($value) => (bool) $value,
            set: fn($value) => !empty($value) ? 1 : 0,
        );
    }

    protected function dependent(): Attribute
    {
        return Attribute::make(
            get: fn($value) => (bool) $value,
            set: fn($value) => !empty($value) ? 1 : 0,
        );
    }

    protected function supplementary(): Attribute
    {
        return Attribute::make(
            get: fn($value) => (bool) $value,
            set: fn($value) => !empty($value) ? 1 : 0,
        );
    }

    protected function required(): Attribute
    {
        return Attribute::make(
            get: fn($value) => (bool) $value,
            set: fn($value) => !empty($value) ? 1 : 0,
        );
    }

    // Scopes

    public function scopeWithEnabled(EloquentBuilder $query): EloquentBuilder
    {
        return $query->where('enabled', 1);
    }

    public function scopeWithDependent(EloquentBuilder $query, bool $dependent = true): EloquentBuilder
    {
        return $query->where('dependent', $dependent);
    }

    public function scopeWithSupplementary(EloquentBuilder $query, bool $supplementary = true): EloquentBuilder
    {
        return $query->where('supplementary', $supplementary);
    }

    public function scopeWithRequired(EloquentBuilder $query, bool $required = true): EloquentBuilder
    {
        return $query->where('required', $required);
    }

    public function scopeWithContext(EloquentBuilder $query, int $contextId): EloquentBuilder
    {
        return $query->where('context_id', $contextId);
    }

    public function scopeWithKey(EloquentBuilder $query, string $key): EloquentBuilder
    {
        return $query->where('entry_key', strtolower($key));
    }

    public function scopeWithoutIds(EloquentBuilder $query, array $excludeIds): EloquentBuilder
    {
        return $query->whereNotIn('genre_id', $excludeIds);
    }

    // Business Logic

    /**
     * Find a Genre by its ID, optionally restricting to one context_id.
     */
    public static function findById(int $id, ?int $contextId = null): ?self
    {
        $query = self::where('genre_id', $id);
        if ($contextId !== null) {
            $query->withContext($contextId);
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
            $query->withContext($contextId);
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
