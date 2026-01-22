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
     * Get genres by dependent flag in a context, ordered by seq.
     */
    public function getByDependenceAndContextId(bool $dependentFilesOnly, int $contextId): Collection
    {
        return Genre::query()
                    ->withEnabled()
                    ->withContext($contextId)
                    ->withDependent($dependentFilesOnly)
                    ->get();
    }

    /**
     * Get genres by supplementary flag in a context, ordered by seq.
     */
    public function getBySupplementaryAndContextId(bool $supplementaryFilesOnly, int $contextId): Collection
    {
        return Genre::query()
                    ->withEnabled()
                    ->withContext($contextId)
                    ->withSupplementary($supplementaryFilesOnly)
                    ->get();
    }

    /**
     * Get primary genres (neither dependent nor supplementary) in a context, ordered by seq.
     */
    public function getPrimaryByContextId(int $contextId): Collection
    {
        return Genre::query()
                    ->withEnabled()
                    ->withContext($contextId)
                    ->where('dependent', 0)
                    ->where('supplementary', 0)
                    ->get();
    }

    /**
     * Get ALL genres in a given context (regardless of enabled), ordered by seq.
     */
    public function getByContextId(int $contextId): Collection
    {
        return Genre::withContext($contextId)->get();
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
                    ->withEnabled()
                    ->withContext($contextId)
                    ->where('dependent', 0)
                    ->where('supplementary', 0)
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
                    ->withEnabled()
                    ->withContext($contextId)
                    ->withSupplementary(true)
                    ->pluck('genre_id')
                    ->toArray();
    }

    /**
     * Get all genres that are required for submission in a context.
     */
    public function getRequiredToSubmit(int $contextId): Collection
    {
        return Genre::query()
                    ->withEnabled()
                    ->withContext($contextId)
                    ->withRequired(true)
                    ->get();
    }

    /**
     * Permanently delete all genres (and their locale settings) for a given context_id.
     * (Mirrors the old DAO’s deleteByContextId().)
     */
    public function deleteByContextId(int $contextId): void
    {
        $genres = Genre::withContext($contextId)->get();
        foreach ($genres as $genre) {
            $genre->delete();
        }
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
        $data = $xmlDao->parseStruct('registry/genres.xml', ['genre']);
        if (empty($data['genre'])) {
            return;
        }

        $rows = [];
        $seq = 0;
        foreach ($data['genre'] as $entry) {
            $attrs = $entry['attributes'];

            $rows[] = [
                'entry_key' => $attrs['key'],
                'seq' => $seq,
                'context_id' => $contextId,
                'category' => (int) $attrs['category'],
                'dependent' => $attrs['dependent'],
                'supplementary' => $attrs['supplementary'],
                'required' => $attrs['required'] ?? false,
                'enabled' => 1,
            ];

            $seq++;
        }

        Genre::upsert(
            $rows,
            ['context_id', 'entry_key'],
            ['seq', 'category', 'dependent', 'supplementary', 'required', 'enabled']
        );

        foreach ($data['genre'] as $entry) {
            $attrs = $entry['attributes'];

            $genre = Genre::findByKey($attrs['key'], $contextId);
            if (! $genre) {
                continue;
            }

            $allNames = [];
            foreach ($locales as $locale) {
                $allNames[$locale] = __($attrs['localeKey'], [], $locale);
            }
            $genre->fill(['name' => $allNames]);
            $genre->save();
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
