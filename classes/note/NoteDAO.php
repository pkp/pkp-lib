<?php

/**
 * @file classes/note/NoteDAO.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NoteDAO
 *
 * @ingroup note
 *
 * @see Note
 *
 * @brief Operations for retrieving and modifying Note objects.
 */

namespace PKP\note;

use APP\core\Application;
use APP\facades\Repo;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use PKP\core\Core;
use PKP\db\DAOResultFactory;
use PKP\plugins\Hook;
use PKP\submissionFile\SubmissionFile;

class NoteDAO extends \PKP\db\DAO
{
    public const NOTE_ORDER_DATE_CREATED = 1;
    public const NOTE_ORDER_ID = 2;

    /**
     * Create a new data object
     *
     * @return Note
     */
    public function newDataObject()
    {
        return new Note();
    }

    /**
     * Retrieve Note by note id
     *
     * @param int $noteId Note ID
     *
     * @return Note|null object
     */
    public function getById($noteId)
    {
        $result = $this->retrieve(
            'SELECT * FROM notes WHERE note_id = ?',
            [(int) $noteId]
        );
        $row = $result->current();
        return $row ? $this->_fromRow((array) $row) : null;
    }

    /**
     * Retrieve Notes by user id
     *
     * @param int $userId User ID
     * @param ?\PKP\db\DBResultRange $rangeInfo Optional
     *
     * @return DAOResultFactory<Note> Object containing matching Note objects
     */
    public function getByUserId($userId, $rangeInfo = null)
    {
        $result = $this->retrieveRange(
            'SELECT * FROM notes WHERE user_id = ? ORDER BY date_created DESC',
            [(int) $userId],
            $rangeInfo
        );

        return new DAOResultFactory($result, $this, '_fromRow');
    }

    /**
     * Retrieve Notes by assoc id/type
     *
     * @param int $assocId Application::ASSOC_TYPE_...
     * @param int $assocType Assoc ID (per $assocType)
     * @param int $userId Optional user ID
     * @param int $orderBy Optional sorting field constant: self::NOTE_ORDER_...
     * @param int $sortDirection Optional sorting order constant: DAO::SORT_DIRECTION_...
     *
     * @return LazyCollection<int,Note>
     */
    public function getByAssoc(
        int $assocType,
        int $assocId,
        ?int $userId = null,
        int $orderBy = self::NOTE_ORDER_DATE_CREATED,
        int $sortDirection = self::SORT_DIRECTION_DESC
    ): LazyCollection {
        $query = DB::table('notes')
            ->where('assoc_id', '=', $assocId)
            ->where('assoc_type', '=', $assocType);

        if ($userId) {
            $query->where('user_id', '=', $userId);
        }

        // Sanitize sort ordering
        switch ($orderBy) {
            case self::NOTE_ORDER_ID:
                $orderSanitized = 'note_id';
                break;
            case self::NOTE_ORDER_DATE_CREATED:
            default:
                $orderSanitized = 'date_created';
        }
        switch ($sortDirection) {
            case self::SORT_DIRECTION_ASC:
                $directionSanitized = 'ASC';
                break;
            case self::SORT_DIRECTION_DESC:
            default:
                $directionSanitized = 'DESC';
        }

        $query->orderBy($orderSanitized, $directionSanitized);

        $rows = $query->get();

        return LazyCollection::make(function () use ($rows) {
            foreach ($rows as $row) {
                yield $row->note_id => $this->_fromRow(get_object_vars($row));
            }
        });
    }

    /**
     * Retrieve if note exists
     *
     * @param int $assocId
     * @param int $assocType
     * @param int $userId
     *
     * @return bool
     */
    public function notesExistByAssoc($assocType, $assocId, $userId = null)
    {
        $params = [(int) $assocId, (int) $assocType];
        if ($userId) {
            $params[] = (int) $userId;
        }

        $result = $this->retrieve(
            'SELECT	COUNT(*) AS row_count
			FROM	notes
			WHERE	assoc_id = ? AND assoc_type = ?
			' . ($userId ? ' AND user_id = ?' : ''),
            $params
        );
        $row = $result->current();
        return $row ? (bool) $row->row_count : false;
    }

    /**
     * Creates and returns an note object from a row
     *
     * @param array $row
     *
     * @return Note object
     */
    public function _fromRow($row)
    {
        $note = $this->newDataObject();
        $note->setId($row['note_id']);
        $note->setUserId($row['user_id']);
        $note->setDateCreated($this->datetimeFromDB($row['date_created']));
        $note->setDateModified($this->datetimeFromDB($row['date_modified']));
        $note->setContents($row['contents']);
        $note->setTitle($row['title']);
        $note->setAssocType($row['assoc_type']);
        $note->setAssocId($row['assoc_id']);

        Hook::call('NoteDAO::_fromRow', [&$note, &$row]);

        return $note;
    }

    /**
     * Inserts a new note into notes table
     *
     * @param Note $note object
     *
     * @return int Note Id
     */
    public function insertObject($note)
    {
        if (!$note->getDateCreated()) {
            $note->setDateCreated(Core::getCurrentDate());
        }
        $this->update(
            sprintf(
                'INSERT INTO notes
				(user_id, date_created, date_modified, title, contents, assoc_type, assoc_id)
				VALUES
				(?, %s, %s, ?, ?, ?, ?)',
                $this->datetimeToDB($note->getDateCreated()),
                $this->datetimeToDB(Core::getCurrentDate())
            ),
            [
                (int) $note->getUserId(),
                $note->getTitle(),
                $note->getContents(),
                (int) $note->getAssocType(),
                (int) $note->getAssocId()
            ]
        );

        $note->setId($this->getInsertId());
        return $note->getId();
    }

    /**
     * Update a note in the notes table
     *
     * @param Note $note object
     *
     * @return int Note Id
     */
    public function updateObject($note)
    {
        return $this->update(
            sprintf(
                'UPDATE	notes SET
					user_id = ?,
					date_created = %s,
					date_modified = %s,
					title = ?,
					contents = ?,
					assoc_type = ?,
					assoc_id = ?
				WHERE	note_id = ?',
                $this->datetimeToDB($note->getDateCreated()),
                $this->datetimeToDB(Core::getCurrentDate())
            ),
            [
                (int) $note->getUserId(),
                $note->getTitle(),
                $note->getContents(),
                (int) $note->getAssocType(),
                (int) $note->getAssocId(),
                (int) $note->getId()
            ]
        );
    }

    /**
     * Delete a note by note object.
     *
     * @param Note $note
     */
    public function deleteObject($note)
    {
        $this->deleteById($note->getId());
    }

    /**
     * Delete Note by note id
     *
     * @param int $noteId
     * @param int $userId optional
     */
    public function deleteById($noteId, $userId = null)
    {
        Repo::submissionFile()
            ->getCollector()
            ->filterByAssoc(Application::ASSOC_TYPE_NOTE, [(int) $noteId])
            ->getMany()
            ->each(function (SubmissionFile $file) {
                Repo::submissionFile()->delete($file);
            });

        DB::table('notes')
            ->where('note_id', '=', (int) $noteId)
            ->when(!is_null($userId), function (Builder $q) use ($userId) {
                $q->where('user_id', '=', $userId);
            })
            ->delete();
    }

    /**
     * Delete notes by association
     *
     * @param int $assocType Application::ASSOC_TYPE_...
     * @param int $assocId Foreign key, depending on $assocType
     */
    public function deleteByAssoc($assocType, $assocId)
    {
        $notes = $this->getByAssoc($assocType, $assocId);

        foreach ($notes as $note) {
            $this->deleteObject($note);
        }
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\note\NoteDAO', '\NoteDAO');
    define('NOTE_ORDER_DATE_CREATED', NoteDAO::NOTE_ORDER_DATE_CREATED);
    define('NOTE_ORDER_ID', NoteDAO::NOTE_ORDER_ID);
}
