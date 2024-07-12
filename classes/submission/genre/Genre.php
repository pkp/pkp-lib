<?php

namespace PKP\submission\genre;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use PKP\submissionFile\SubmissionFile;



class Genre extends Model
{
    const GENRE_CATEGORY_DOCUMENT = 1;
    const GENRE_CATEGORY_ARTWORK = 2;
    const GENRE_CATEGORY_SUPPLEMENTARY = 3;
    protected $table = 'genres';
    protected $primaryKey = 'genre_id';
    public $timestamps = false;

    protected $fillable = [
        'context_id', 'seq', 'enabled', 'category', 'dependent', 
        'supplementary', 'required', 'entry_key'
    ];

    // Relationships
    public function submissionFiles(): HasMany
    {
        return $this->hasMany(SubmissionFile::class, 'genre_id');
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
    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', 1);
    }

    public function scopeDependent(Builder $query, bool $dependent = true): Builder
    {
        return $query->where('dependent', $dependent);
    }

    public function scopeSupplementary(Builder $query, bool $supplementary = true): Builder
    {
        return $query->where('supplementary', $supplementary);
    }

    public function scopeRequired(Builder $query, bool $required = true): Builder
    {
        return $query->where('required', $required);
    }

    public function scopeInContext(Builder $query, int $contextId): Builder
    {
        return $query->where('context_id', $contextId);
    }

    // Business Logic
    public function isDefault(): bool
    {
        $defaultKeys = $this->getDefaultKeys();
        return in_array($this->entry_key, $defaultKeys);
    }

    /**
     * Retrieve a list of default genre keys from the database or configuration.
     */
    protected function getDefaultKeys(): array
    {
        // fetching default keys from a configuration or database
        return DB::table('genre_defaults')->pluck('entry_key')->toArray();
    }
}
