<?php
/**
 * @file classes/submission/genre/Repository.php
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2000-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Repository
 *
 * @brief A repository to find and manage genre.
 */

namespace PKP\submission\genre;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class Repository
{
    /**
     * Retrieve a Genre by its ID (and optionally by context).
     */
    public function get(int $genreId, ?int $contextId = null): ?Genre
    {
        return Genre::findById($genreId, $contextId);
    }

    /**
     * Retrieve a Genre by its entry_key (and optionally by context) using the model’s own finder.
     */
    public function getByKey(string $key, ?int $contextId = null): ?Genre
    {
        return Genre::findByKey($key, $contextId);
    }

    /**
     * Does a Genre with this key already exist? Exclude one ID if needed.
     */
    public function keyExists(string $key, int $contextId, ?int $excludeGenreId = null): bool
    {
        $query = Genre::where('entry_key', strtolower($key))
                      ->where('context_id', $contextId);

        if ($excludeGenreId !== null) {
            $query->where('genre_id', '<>', $excludeGenreId);
        }
        return $query->exists();
    }

    /**
     * Get all enabled genres in a given context, ordered by seq.
     */
    public function getEnabledByContextId(int $contextId): Collection
    {
        return Genre::query()
                    ->enabled()
                    ->inContext($contextId)
                    ->orderBy('seq')
                    ->get();
    }

    /**
     * Get genres by dependent flag in a context, ordered by seq.
     */
    public function getByDependenceAndContextId(bool $dependentFilesOnly, int $contextId): Collection
    {
        return Genre::query()
                    ->enabled()
                    ->inContext($contextId)
                    ->dependent($dependentFilesOnly)
                    ->orderBy('seq')
                    ->get();
    }

    /**
     * Get genres by supplementary flag in a context, ordered by seq.
     */
    public function getBySupplementaryAndContextId(bool $supplementaryFilesOnly, int $contextId): Collection
    {
        return Genre::query()
                    ->enabled()
                    ->inContext($contextId)
                    ->supplementary($supplementaryFilesOnly)
                    ->orderBy('seq')
                    ->get();
    }

    /**
     * Get primary genres (neither dependent nor supplementary) in a context, ordered by seq.
     */
    public function getPrimaryByContextId(int $contextId): Collection
    {
        return Genre::query()
                    ->enabled()
                    ->inContext($contextId)
                    ->where('dependent', 0)
                    ->where('supplementary', 0)
                    ->orderBy('seq')
                    ->get();
    }

    /**
     * Get ALL genres in a given context (regardless of enabled), ordered by seq.
     */
    public function getByContextId(int $contextId): Collection
    {
        return Genre::inContext($contextId)
                    ->orderBy('seq')
                    ->get();
    }

    /**
     * Get _only_ the IDs of primary genres (i.e. enabled, in‐context, dependent=0, supplementary=0).
     *
     * @param int $contextId
     * @return int[]   Plain array of genre_id integers
     */
    public function getPrimaryIdsByContextId(int $contextId): array
    {
        return Genre::query()
                    ->enabled()
                    ->inContext($contextId)
                    ->where('dependent', 0)
                    ->where('supplementary', 0)
                    ->orderBy('seq')
                    ->pluck('genre_id')
                    ->toArray();
    }

    /**
     * Get _only_ the IDs of supplementary genres (i.e. enabled, in‐context, supplementary=1).
     *
     * @param int $contextId
     * @return int[]   Plain array of genre_id integers
     */
    public function getSupplementaryIdsByContextId(int $contextId): array
    {
        return Genre::query()
                    ->enabled()
                    ->inContext($contextId)
                    ->supplementary(true)
                    ->orderBy('seq')
                    ->pluck('genre_id')
                    ->toArray();
    }

    /**
     * Get all genres that are required for submission in a context.
     */
    public function getRequiredToSubmit(int $contextId): Collection
    {
        return Genre::query()
                    ->enabled()
                    ->inContext($contextId)
                    ->required(true)
                    ->get();
    }

    /**
     * Soft‐delete a genre: set enabled = 0.
     */
    public function deleteById(int $genreId): bool
    {
        $affected = Genre::where('genre_id', $genreId)
                         ->update(['enabled' => 0]);

        return ($affected > 0);
    }

    /**
     * Permanently delete all genres (and their locale settings) for a given context_id.
     * (Mirrors the old DAO’s deleteByContextId().)
     */
    public function deleteByContextId(int $contextId): void
    {
        // First, remove all locale‐specific rows from genre_settings:
        $all = Genre::inContext($contextId)->get();
        foreach ($all as $genre) {
            DB::table('genre_settings')
              ->where('genre_id', $genre->genre_id)
              ->delete();
        }

        // Then delete the genre records themselves:
        Genre::where('context_id', $contextId)->delete();
    }

    /**
     * Install default data (from registry/genres.xml) into genres & genre_settings tables,
     * just as the old DAO’s installDefaults() did.
     *
     * @param int   $contextId
     * @param array $locales  List of locale codes (e.g. ['en_US','fr_CA', …])
     */
    public function installDefaults(int $contextId, array $locales): void
    {
        $xmlDao = new \PKP\db\XMLDAO();
        $data   = $xmlDao->parseStruct('registry/genres.xml', ['genre']);
        if (empty($data['genre'])) {
            return;
        }

        $seq = 0;
        foreach ($data['genre'] as $entry) {
            $attrs = $entry['attributes'];

            // Try to find an existing Genre by (entry_key, context_id):
            $existing = Genre::findByKey($attrs['key'], $contextId);

            if (! $existing) {
                // Create a brand‐new Genre model (ModelWithSettings will insert into genre_settings automatically)
                $genre = new Genre([
                    'entry_key'     => $attrs['key'],
                    'seq'           => $seq,
                    'context_id'    => $contextId,
                    'category'      => (int) $attrs['category'],
                    'dependent'     => (bool) $attrs['dependent'],
                    'supplementary' => (bool) $attrs['supplementary'],
                    'required'      => (bool) ($attrs['required'] ?? false),
                    'enabled'       => 1,
                ]);
                $genre->save();
            } else {
                // Already exists: re‐enable and update any changed flags (keep the same ID):
                $existing->enabled       = 1;
                $existing->category      = (int) $attrs['category'];
                $existing->dependent     = (bool) $attrs['dependent'];
                $existing->supplementary = (bool) $attrs['supplementary'];
                $existing->required      = (bool) ($attrs['required'] ?? false);
                $existing->seq           = $seq;
                $existing->save();
                $genre = $existing;
            }

            // Write each locale’s name into genre_settings via ModelWithSettings:
            foreach ($locales as $locale) {
                $localizedName = __($attrs['localeKey'], [], $locale);
                $genre->fill([
                    'name' => [ $locale => $localizedName ],
                ]);
            }
            $genre->save();

            $seq++;
        }
    }

    /**
     * Remove all rows from genre_settings for a given $locale
     */
    public function deleteSettingsByLocale(string $locale): void
    {
        DB::table('genre_settings')
          ->where('locale', $locale)
          ->delete();
    }
}
