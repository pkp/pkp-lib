<?php

/**
 * @file classes/submission/GenreDAO.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class GenreDAO
 * @ingroup submission
 *
 * @see Genre
 *
 * @brief Operations for retrieving and modifying Genre objects.
 */

namespace PKP\submission;

use PKP\db\DAO;
use PKP\db\DAOResultFactory;
use PKP\db\XMLDAO;

use PKP\plugins\HookRegistry;

class GenreDAO extends DAO
{
    /**
     * Retrieve a genre by type id.
     *
     * @param int $genreId
     * @param null|mixed $contextId
     *
     * @return Genre
     */
    public function getById($genreId, $contextId = null)
    {
        $params = [(int) $genreId];
        if ($contextId) {
            $params[] = (int) $contextId;
        }

        $result = $this->retrieve(
            'SELECT * FROM genres WHERE genre_id = ?' .
            ($contextId ? ' AND context_id = ?' : '') .
            ' ORDER BY seq',
            $params
        );
        $row = $result->current();
        return $row ? $this->_fromRow((array) $row) : null;
    }

    /**
     * Retrieve all genres
     *
     * @param int $contextId
     * @param object $rangeInfo optional
     *
     * @return DAOResultFactory containing matching genres
     */
    public function getEnabledByContextId($contextId, $rangeInfo = null)
    {
        $result = $this->retrieveRange(
            'SELECT * FROM genres
			WHERE	enabled = ? AND context_id = ?
			ORDER BY seq',
            [1, (int) $contextId],
            $rangeInfo
        );

        return new DAOResultFactory($result, $this, '_fromRow', ['id']);
    }

    /**
     * Retrieve genres based on whether they are dependent or not.
     *
     * @param bool $dependentFilesOnly
     * @param int $contextId
     * @param object $rangeInfo optional
     *
     * @return DAOResultFactory containing matching genres
     */
    public function getByDependenceAndContextId($dependentFilesOnly, $contextId, $rangeInfo = null)
    {
        $result = $this->retrieveRange(
            'SELECT * FROM genres
			WHERE enabled = ? AND context_id = ? AND dependent = ?
			ORDER BY seq',
            [1, (int) $contextId, (int) $dependentFilesOnly],
            $rangeInfo
        );

        return new DAOResultFactory($result, $this, '_fromRow', ['id']);
    }

    /**
     * Retrieve genres based on whether they are supplementary or not.
     *
     * @param bool $supplementaryFilesOnly
     * @param int $contextId
     * @param object $rangeInfo optional
     *
     * @return DAOResultFactory
     */
    public function getBySupplementaryAndContextId($supplementaryFilesOnly, $contextId, $rangeInfo = null)
    {
        $result = $this->retrieveRange(
            'SELECT * FROM genres
			WHERE enabled = ? AND context_id = ? AND supplementary = ?
			ORDER BY seq',
            [1, (int) $contextId, (int) $supplementaryFilesOnly],
            $rangeInfo
        );

        return new DAOResultFactory($result, $this, '_fromRow', ['id']);
    }

    /**
     * Retrieve genres that are not supplementary or dependent.
     *
     * @param int $contextId
     * @param object $rangeInfo optional
     *
     * @return DAOResultFactory
     */
    public function getPrimaryByContextId($contextId, $rangeInfo = null)
    {
        $result = $this->retrieveRange(
            'SELECT * FROM genres
			WHERE enabled = ? AND context_id = ? AND dependent = ? AND supplementary = ?
			ORDER BY seq',
            [1, (int) $contextId, 0, 0],
            $rangeInfo
        );

        return new DAOResultFactory($result, $this, '_fromRow', ['id']);
    }

    /**
     * Retrieve all genres
     *
     * @param int $contextId
     * @param object $rangeInfo optional
     *
     * @return DAOResultFactory containing matching genres
     */
    public function getByContextId($contextId, $rangeInfo = null)
    {
        $result = $this->retrieveRange(
            'SELECT * FROM genres WHERE context_id = ? ORDER BY seq',
            [(int) $contextId],
            $rangeInfo
        );

        return new DAOResultFactory($result, $this, '_fromRow', ['id']);
    }

    /**
     * Retrieves the genre associated with a key.
     *
     * @param string $key the entry key
     * @param int $contextId Optional context ID
     *
     * @return Genre
     */
    public function getByKey($key, $contextId = null)
    {
        $params = [$key];
        if ($contextId) {
            $params[] = (int) $contextId;
        }

        $result = $this->retrieve(
            'SELECT * FROM genres WHERE entry_key = ? ' .
            ($contextId ? ' AND context_id = ?' : ''),
            $params
        );

        $row = $result->current();
        return $row ? $this->_fromRow((array) $row) : null;
    }

    /**
     * Get a list of field names for which data is localized.
     *
     * @return array
     */
    public function getLocaleFieldNames()
    {
        return ['name'];
    }

    /**
     * Update the settings for this object
     *
     * @param object $genre
     */
    public function updateLocaleFields($genre)
    {
        $this->updateDataObjectSettings(
            'genre_settings',
            $genre,
            ['genre_id' => $genre->getId()]
        );
    }

    /**
     * Construct a new data object corresponding to this DAO.
     *
     * @return Genre
     */
    public function newDataObject()
    {
        return new Genre();
    }

    /**
     * Internal function to return a Genre object from a row.
     *
     * @param array $row
     *
     * @return Genre
     */
    public function _fromRow($row)
    {
        $genre = $this->newDataObject();
        $genre->setId((int) $row['genre_id']);
        $genre->setKey($row['entry_key']);
        $genre->setContextId($row['context_id']);
        $genre->setCategory((int) $row['category']);
        $genre->setDependent($row['dependent']);
        $genre->setSupplementary($row['supplementary']);
        $genre->setSequence($row['seq']);
        $genre->setEnabled($row['enabled']);

        $this->getDataObjectSettings('genre_settings', 'genre_id', $row['genre_id'], $genre);

        HookRegistry::call('GenreDAO::_fromRow', [&$genre, &$row]);

        return $genre;
    }

    /**
     * Insert a new genre.
     *
     * @param Genre $genre
     *
     * @return int Inserted genre ID
     */
    public function insertObject($genre)
    {
        $this->update(
            'INSERT INTO genres
				(entry_key, seq, context_id, category, dependent, supplementary)
			VALUES
				(?, ?, ?, ?, ?, ?)',
            [
                $genre->getKey(),
                (float) $genre->getSequence(),
                (int) $genre->getContextId(),
                (int) $genre->getCategory(),
                $genre->getDependent() ? 1 : 0,
                $genre->getSupplementary() ? 1 : 0,
            ]
        );

        $genre->setId($this->getInsertId());
        $this->updateLocaleFields($genre);
        return $genre->getId();
    }

    /**
     * Update an existing genre.
     *
     * @param Genre $genre
     */
    public function updateObject($genre)
    {
        $this->update(
            'UPDATE genres
			SET	entry_key = ?,
				seq = ?,
				dependent = ?,
				supplementary = ?,
				enabled = ?,
				category = ?
			WHERE	genre_id = ?',
            [
                $genre->getKey(),
                (float) $genre->getSequence(),
                $genre->getDependent() ? 1 : 0,
                $genre->getSupplementary() ? 1 : 0,
                $genre->getEnabled() ? 1 : 0,
                $genre->getCategory(),
                (int) $genre->getId(),
            ]
        );
        $this->updateLocaleFields($genre);
    }

    /**
     * Delete a genre by id.
     *
     * @param Genre $genre
     */
    public function deleteObject($genre)
    {
        return $this->deleteById($genre->getId());
    }

    /**
     * Soft delete a genre by id.
     *
     * @param int $genreId Genre ID
     */
    public function deleteById($genreId)
    {
        return $this->update(
            'UPDATE genres SET enabled = ? WHERE genre_id = ?',
            [0, (int) $genreId]
        );
    }

    /**
     * Delete the genre entries associated with a context.
     * Called when deleting a Context in ContextDAO.
     *
     * @param int $contextId Context ID
     */
    public function deleteByContextId($contextId)
    {
        $genres = $this->getByContextId($contextId);
        while ($genre = $genres->next()) {
            $this->update('DELETE FROM genre_settings WHERE genre_id = ?', [(int) $genre->getId()]);
        }
        $this->update(
            'DELETE FROM genres WHERE context_id = ?',
            [(int) $contextId]
        );
    }

    /**
     * Get the ID of the last inserted genre.
     *
     * @return int Inserted genre ID
     */
    public function getInsertId()
    {
        return $this->_getInsertId('genres', 'genre_id');
    }

    /**
     * Install default data for settings.
     *
     * @param int $contextId Context ID
     * @param array $locales List of locale codes
     */
    public function installDefaults($contextId, $locales)
    {
        $xmlDao = new XMLDAO();
        $data = $xmlDao->parseStruct('registry/genres.xml', ['genre']);
        if (!isset($data['genre'])) {
            return false;
        }
        $seq = 0;

        foreach ($data['genre'] as $entry) {
            $attrs = $entry['attributes'];
            // attempt to retrieve an installed Genre with this key.
            // Do this to preserve the genreId.
            $genre = $this->getByKey($attrs['key'], $contextId);
            if (!$genre) {
                $genre = $this->newDataObject();
            }
            $genre->setContextId($contextId);
            $genre->setKey($attrs['key']);
            $genre->setCategory($attrs['category']);
            $genre->setDependent($attrs['dependent']);
            $genre->setSupplementary($attrs['supplementary']);
            $genre->setSequence($seq++);
            foreach ($locales as $locale) {
                $genre->setName(__($attrs['localeKey'], [], $locale), $locale);
            }

            if ($genre->getId() > 0) { // existing genre.
                $genre->setEnabled(1);
                $this->updateObject($genre);
            } else {
                $this->insertObject($genre);
            }
        }
    }

    /**
     * Get default keys.
     *
     * @return array List of default keys
     */
    public function getDefaultKeys()
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
     * If a key exists for a context.
     *
     * @param string $key
     * @param int $contextId
     * @param int $genreId (optional) Current genre to be ignored
     *
     * @return bool
     */
    public function keyExists($key, $contextId, $genreId = null)
    {
        $params = [$key, (int) $contextId];
        if ($genreId) {
            $params[] = (int) $genreId;
        }
        $result = $this->retrieveRange(
            'SELECT COUNT(*) AS row_count FROM genres WHERE entry_key = ? AND context_id = ?' . (isset($genreId) ? ' AND genre_id <> ?' : ''),
            $params
        );
        $row = $result->current();
        return $row ? (bool) $row->row_count : false;
    }

    /**
     * Remove all settings associated with a locale
     *
     * @param string $locale Locale code
     */
    public function deleteSettingsByLocale($locale)
    {
        $this->update('DELETE FROM genre_settings WHERE locale = ?', [$locale]);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\submission\GenreDAO', '\GenreDAO');
}
