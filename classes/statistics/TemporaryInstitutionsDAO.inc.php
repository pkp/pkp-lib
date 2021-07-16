<?php

/**
 * @file classes/statistics/TemporaryInstitutionsDAO.inc.php
 *
 * Copyright (c) 2013-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class TemporaryInstitutionsDAO
 * @ingroup statistics
 *
 * @brief Operations for retrieving and adding the normalized data for institutions usage stats temporary records.
 */

namespace PKP\statistics;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TemporaryInstitutionsDAO
{
    /** The name of the table */
    public string $table = 'usage_stats_institution_temporary_records';

    /**
     * Insert the institution ids to normalize the data in temporary tables.
     */
    public function insert(array $institutionIds, int $lineNumber, string $loadId): void
    {
        foreach ($institutionIds as $institutionId) {
            DB::table($this->table)->insert([
                'load_id' => $loadId,
                'line_number' => $lineNumber,
                'institution_id' => $institutionId
            ]);
        }
    }

    /**
     * Delete all records associated
     * with the passed load id.
     */
    public function deleteByLoadId(string $loadId): void
    {
        DB::table($this->table)->where('load_id', '=', $loadId)->delete();
    }

    /**
     * Retrieve all distinct institution IDs for the given load id.
     */
    public function getInstitutionIdsByLoadId(string $loadId): Collection
    {
        $institutionIds = DB::table($this->table)
            ->select('institution_id')
            ->distinct()
            ->where('load_id', '=', $loadId)
            ->pluck('institution_id');
        return $institutionIds;
    }
}
