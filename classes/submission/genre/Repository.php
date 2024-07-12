<?php

namespace PKP\submission\genre;

use PKP\submission\genre\Genre;
use Illuminate\Database\Eloquent\Collection;
use PKP\db\XMLDAO;


class Repository
{

    /**
     * Get enabled genres by context ID.
     *
     * @param int $contextId
     * @return Collection
     */
    public function getEnabledByContextId(int $contextId, $rangeInfo = null): Collection
    {
        $query = Genre::where('context_id', $contextId)
                      ->where('enabled', true)
                      ->orderBy('seq');
    
        if ($rangeInfo) {
            return $query->paginate($rangeInfo->perPage, ['*'], 'page', $rangeInfo->currentPage);
        }
    
        return $query->get();
    }
    /**
     * Retrieve genres based on whether they are dependent or not.
     *
     * @param bool $dependent
     * @param int $contextId
     * @return Collection|Genre[]
     */
    public function getByDependenceAndContextId(bool $dependent, int $contextId): Collection
    {
        return Genre::where('context_id', $contextId)
                    ->where('dependent', $dependent)
                    ->where('enabled', true)
                    ->orderBy('seq')
                    ->get();
    }

    /**
     * Retrieve genres based on supplementary criteria within a specific context.
     *
     * @param bool $supplementaryFilesOnly
     * @param int $contextId
     * @return Collection|Genre[]
     */
    public function getBySupplementaryAndContextId(bool $supplementaryFilesOnly, int $contextId): Collection
    {
        return Genre::where('context_id', $contextId)
                    ->where('supplementary', $supplementaryFilesOnly)
                    ->orderBy('seq')
                    ->get();
    }

    /**
     * Retrieve genres that are neither supplementary nor dependent.
     *
     * @param int $contextId
     * @return Collection|Genre[]
     */
    public function getPrimaryByContextId(int $contextId): Collection
    {
        return Genre::where('context_id', $contextId)
                    ->where('dependent', false)
                    ->where('supplementary', false)
                    ->where('enabled', true)
                    ->orderBy('seq')
                    ->get();
    }

    /**
     * Retrieve genres that are required for a new submission in a specific context.
     *
     * @param int $contextId
     * @return Collection|Genre[]
     */
    public function getRequiredToSubmit(int $contextId): Collection
    {
        return Genre::where('context_id', $contextId)
                    ->where('required', true)
                    ->get();
    }

    /**
     * Check if a genre key exists within a context
     *
     * @param string $key Key to check.
     * @param int $contextId Context ID.
     * @param int|null $genreId Genre ID to exclude (optional).
     * @return bool True if key exists, otherwise false.
     */
    public function keyExists(string $key, int $contextId, ?int $genreId = null): bool
    {
        $query = Genre::where('entry_key', $key)
                    ->where('context_id', $contextId);

        if ($genreId !== null) {
            $query->where('genre_id', '<>', $genreId);
        }

        return $query->exists();
    }

    /**
     * Retrieves the genre associated with a specific entry key, optionally within a context.
     *
     * @param string $key
     * @param int|null $contextId
     * @return Genre|null
     */
    public function getByKey(string $key, ?int $contextId = null)
    {
        $query = Genre::where('entry_key', $key);
        if ($contextId !== null) {
            $query->where('context_id', $contextId);
        }
        return $query->first();
    }

    /**
     * Get default keys used in the system.
     *
     * @return array
     */
    public function getDefaultKeys(): array
    {
        $defaultKeys = [];
        $xmlDao = new XMLDAO();
        $data = $xmlDao->parseStruct('registry/genres.xml', ['genre']);
        if (isset($data['genre'])) {
            foreach ($data['genre'] as $entry) {
                $attrs = $entry['attributes'];
                $defaultKeys[] = $attrs['key'];
            }
        }
        return $defaultKeys;
    }

    /**
     * Install default genres based on a predefined set of attributes and context.
     * Implementation pending on handling of genre settings.
     *
     * @param int $contextId
     * @param array $locales
     */
    public function installDefaults(int $contextId, array $locales)
    {
        // TODO: Implement once settings handling is completed.
    }

    /**
     * Get a list of field names for which data is localized.
     * Implementation pending on handling of genre settings.
     *
     * @return array
     */
    public function getLocaleFieldNames()
    {
        // TODO: Implement based on settings table management.
        return [];
    }

    /**
     * Update the locale-specific settings for a genre.
     * Implementation pending on handling of genre settings.
     *
     * @param Genre $genre
     */
    public function updateLocaleFields(Genre $genre)
    {
        // TODO: Define how locale fields should be updated.
    }

    /**
     * Delete settings associated with a specific locale.
     * Functionality related to settings cleanup based on locale.
     *
     * @param string $locale
     */
    public function deleteSettingsByLocale(string $locale)
    {
        // TODO: Implement deletion logic 
    }

}
