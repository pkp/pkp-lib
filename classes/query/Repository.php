<?php

/**
 * @file classes/query/Repository.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Repository
 *
 * @see Query
 *
 * @brief Operations for retrieving and modifying Query objects.
 */

namespace PKP\query;

use PKP\db\DAO;
use PKP\note\Note;

class Repository
{
    /**
     * Fetch a query by symbolic info, building it if needed.
     */
    public function build(int $assocType, int $assocId, int $userId, int $stageId, float $seq, int $closed): Query
    {
        return Query::withUserId($userId)
            ->withAssoc($assocType, $assocId)
            ->firstOr(fn() => Query::create([
                'assocType' => $assocType,
                'assocId' => $assocId,
                'userId' => $userId,
                'stageId' => $stageId,
                'seq' => $seq,
                'closed' => $closed
            ]));
    }

    /**
     * Sequentially renumber queries in their sequence order.
     *
     * @param int $assocType Application::ASSOC_TYPE_...
     * @param int $assocId Assoc ID per assocType
     */
    public function resequence($assocType, $assocId)
    {
        $result = $this->withAssoc($assocType, $assocId)
            ->orderBy('seq')
            ->get();

        for ($i = 1; $row = $result->current(); $i++) {
            $this->where('queryId', $row->queryId)
                ->update(['seq' => $i]);
            $result->next();
        }
    }


}
