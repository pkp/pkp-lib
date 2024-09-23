<?php

/**
 * @file classes/note/Repository.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Repository
 *
 * @see Note
 *
 * @brief Operations for retrieving and modifying Note objects.
 */

namespace PKP\note;

use PKP\db\DAO;
use PKPApplication;

class Repository
{
    public function transfer(int $oldUserId, int $newUserId): int
    {
        return Note::withUserId($oldUserId)
            ->update(['user_id' => $newUserId]);
    }

    /**
     * Get the "head" (first) note for a Query.
     */
    public function getHeadNote(int $queryId): ?Note
    {
        return Note::withAssoc(PKPApplication::ASSOC_TYPE_QUERY, $queryId)
            ->withSort(Note::NOTE_ORDER_DATE_CREATED, DAO::SORT_DIRECTION_ASC)
            ->first();
    }
}
