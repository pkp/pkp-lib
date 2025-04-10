<?php

/**
 * @file classes/migration/upgrade/v3_5_0/I11241_MissingDecisionConstantsUpdate.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I11241_MissingDecisionConstantsUpdate
 *
 * @brief Fixed the missing decisions data in stages
 *
 * @see https://github.com/pkp/pkp-lib/issues/11241 
 *      https://github.com/pkp/pkp-lib/issues/9533
 */

namespace APP\migration\upgrade\v3_5_0;

class I11241_MissingDecisionConstantsUpdate extends \PKP\migration\upgrade\v3_4_0\I7725_DecisionConstantsUpdate
{
    /**
     * Get the decisions constants mappings
     */
    public function getDecisionMappings(): array
    {
        return [
            // \PKP\decision\Decision::INITIAL_DECLINE
            [
                'stage_id' => WORKFLOW_STAGE_ID_PRODUCTION,
                'current_value' => 9,
                'updated_value' => 8,
            ],
            // \PKP\decision\Decision::REVERT_DECLINE to \PKP\decision\Decision::REVERT_INITIAL_DECLINE
            // \PKP\decision\Decision::REVERT_DECLINE removed in 3.4
            [
                'stage_id' => WORKFLOW_STAGE_ID_PRODUCTION,
                'current_value' => 17,
                'updated_value' => 16,
            ]
        ];
    }
}
