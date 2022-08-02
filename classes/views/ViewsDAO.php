<?php

/**
 * @file classes/views/ViewsDAO.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ViewsDAO
 * @ingroup views
 *
 * @brief Class for keeping track of item views.
 */

namespace PKP\views;

use Illuminate\Support\Facades\DB;

class ViewsDAO extends \PKP\db\DAO
{
    /**
     * Mark an item as viewed.
     *
     * @param int $assocType The associated type for the item being marked.
     * @param string $assocId The id of the object being marked.
     * @param int $userId The id of the user viewing the item.
     *
     */
    public function recordView($assocType, $assocId, $userId): bool
    {
        return DB::table('item_views')->updateOrInsert(
            ['assoc_type' => (int) $assocType, 'assoc_id' => $assocId, 'user_id' => (int) $userId],
            ['date_last_viewed' => date('Y-m-d H:i:s')]
        );
    }

    /**
     * Get the timestamp of the last view.
     *
     * @param int $assocType
     * @param string $assocId
     * @param int $userId
     *
     * @return string|boolean Datetime of last view. False if no view found.
     */
    public function getLastViewDate($assocType, $assocId, $userId = null)
    {
        $params = [(int)$assocType, $assocId];
        if ($userId) {
            $params[] = (int)$userId;
        }
        $result = $this->retrieve(
            'SELECT	date_last_viewed
			FROM	item_views
			WHERE	assoc_type = ?
				AND assoc_id = ?' .
                ($userId ? ' AND user_id = ?' : ''),
            $params
        );
        $row = $result->current();
        return $row ? $row->date_last_viewed : false;
    }

    /**
     * Move views from one assoc object to another.
     *
     * @param int $assocType One of the ASSOC_TYPE_* constants.
     * @param string $oldAssocId
     * @param string $newAssocId
     */
    public function moveViews($assocType, $oldAssocId, $newAssocId)
    {
        return $this->update(
            'UPDATE item_views SET assoc_id = ? WHERE assoc_type = ? AND assoc_id = ?',
            [$newAssocId, (int)$assocType, $oldAssocId]
        );
    }

    /**
     * Delete views of an assoc object.
     *
     * @param int $assocType One of the ASSOC_TYPE_* constants.
     * @param string $assocId
     */
    public function deleteViews($assocType, $assocId)
    {
        return $this->update(
            'DELETE FROM item_views WHERE assoc_type = ? AND assoc_id = ?',
            [(int)$assocType, $assocId]
        );
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\views\ViewsDAO', '\ViewsDAO');
}
