<?php

/**
 * @file controllers/grid/users/stageParticipant/StageParticipantGridCellProvider.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DataObjectGridCellProvider
 * @ingroup controllers_grid_users_submissionContributor
 *
 * @brief Cell provider to retrieve the user's name from the stage assignment
 */

use PKP\controllers\grid\DataObjectGridCellProvider;
use PKP\controllers\grid\GridColumn;

class StageParticipantGridCellProvider extends DataObjectGridCellProvider
{
    //
    // Template methods from GridCellProvider
    //
    /**
     * Extracts variables for a given column from a data element
     * so that they may be assigned to template before rendering.
     *
     * @param $row \PKP\controllers\grid\GridRow
     * @param $column GridColumn
     *
     * @return array
     */
    public function getTemplateVarsFromRowColumn($row, $column)
    {
        switch ($column->getId()) {
            case 'participants':
                $stageAssignment = $row->getData();
                $userDao = DAORegistry::getDAO('UserDAO'); /** @var UserDAO $userDao */
                $user = $userDao->getById($stageAssignment->getUserId());
                assert($user);
                return ['label' => $user ? $user->getFullName() : ''];
            default:
                assert(false);
        }
    }
}
