<?php

/**
 * @file classes/testing/scenario/GenreLookup.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class GenreLookup
 *
 * @brief Friendly-string → Genre resolver for the test scenario layer.
 *
 * Sibling of UserGroupLookup. Lets scenario specs reference genres by
 * stable, human-readable handles ('ARTICLE') instead of the raw
 * `entry_key` from registry/genres.xml. Today only the article-text genre
 * is needed (every wizard upload defaults to it); add more keys here as
 * scenarios grow to cover dependent / supplementary file types.
 */

namespace PKP\testing\scenario;

use PKP\db\DAORegistry;
use PKP\submission\Genre;
use PKP\submission\GenreDAO;

class GenreLookup
{
    /**
     * Friendly handles accepted in scenario specs → entry_key column in
     * the genres table (installed per-context from registry/genres.xml).
     */
    public const FRIENDLY_TO_GENRE_KEY = [
        'ARTICLE' => 'SUBMISSION',
    ];

    /**
     * Translate a friendly handle to its registry/genres.xml entry_key.
     * Throws on unknown handles so mistyped specs fail loudly.
     */
    public static function friendlyToGenreKey(string $friendly): string
    {
        if (!isset(self::FRIENDLY_TO_GENRE_KEY[$friendly])) {
            throw new \InvalidArgumentException(
                "Unknown genre handle '{$friendly}'. Known handles: "
                . implode(', ', array_keys(self::FRIENDLY_TO_GENRE_KEY))
            );
        }
        return self::FRIENDLY_TO_GENRE_KEY[$friendly];
    }

    /**
     * Return the Genre row matching the given friendly handle in the
     * given context. Relies on the default genres installed by
     * GenreDAO::installDefaults() at journal creation time.
     */
    public static function genreForKey(int $contextId, string $friendly): Genre
    {
        $entryKey = self::friendlyToGenreKey($friendly);
        /** @var GenreDAO $genreDao */
        $genreDao = DAORegistry::getDAO('GenreDAO');
        $genre = $genreDao->getByKey($entryKey, $contextId);

        if (!$genre) {
            throw new \RuntimeException(
                "Could not find default genre '{$entryKey}' for context {$contextId}. "
                . "Was the journal created through the standard service (which installs genres.xml)?"
            );
        }

        return $genre;
    }
}
