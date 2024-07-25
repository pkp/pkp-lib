<?php

/**
 * @file classes/notification/Repository.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Repository
 *
 * @see Notification
 *
 * @brief Operations for retrieving and modifying Notification objects.
 */

namespace PKP\notification;

use Carbon\Carbon;

class Repository
{
    /**
     * Fetch a notification by symbolic info, building it if needed.
     */
    public function build(?int $contextId, int $level, int $type, int $assocType, int $assocId, ?int $userId): Notification
    {
        return Notification::withContextId($contextId)
            ->withUserId($userId)
            ->withLevel($level)
            ->withAssoc($assocType, $assocId)
            ->firstOr(fn () => Notification::create([
                'contextId' => $contextId,
                'level' => $level,
                'type' => $type,
                'assocType' => $assocType,
                'assocId' => $assocId,
                'userId' => $userId,
                'dateCreated' => Carbon::now()
            ]));
    }

    public function transfer(int $oldUserId, int $newUserId): int
    {
        return Notification::withUserId($oldUserId)
            ->update(['user_id' => $newUserId]);
    }
}
