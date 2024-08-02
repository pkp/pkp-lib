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

class Repository
{
    /**
     * Fetch a note by symbolic info, building it if needed.
     */
    public function build(int $assocType, int $assocId, int $userId, ?string $contents, ?string $title): Note
    {
        return Note::withUserId($userId)
            ->withAssoc($assocType, $assocId)
            ->firstOr(fn() => Note::create([
                'assocType' => $assocType,
                'assocId' => $assocId,
                'userId' => $userId,
                'contents' => $contents,
                'title' => $title,
            ]));
    }

    public function transfer(int $oldUserId, int $newUserId): int
    {
        return Note::withUserId($oldUserId)
            ->update(['user_id' => $newUserId]);
    }
}
