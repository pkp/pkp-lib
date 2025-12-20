<?php

/**
 * @file controllers/grid/users/userSelect/UserSelectGridCellProvider.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserSelectGridCellProvider
 *
 * @ingroup controllers_grid_users_userSelect
 *
 * @brief Base class for a cell provider that retrieves data for selecting a user
 */

namespace PKP\controllers\grid\users\userSelect;

use APP\facades\Repo;
use APP\submission\Submission;
use PKP\controllers\grid\DataObjectGridCellProvider;
use PKP\controllers\grid\GridColumn;
use PKP\user\User;

class UserSelectGridCellProvider extends DataObjectGridCellProvider
{
    /** @var int ID of the current context */
    public $_contextId;

    /** @var int User ID of already-selected user */
    public $_userId;

    /**
     * Constructor
     *
     * @param int $userId ID of preselected user.
     */
    public function __construct($contextId, $userId = null)
    {
        $this->_contextId = $contextId;
        $this->_userId = $userId;
    }

    //
    // Template methods from GridCellProvider
    //
    /**
     * Extracts variables for a given column from a data element
     * so that they may be assigned to template before rendering.
     *
     * @param \PKP\controllers\grid\GridRow $row
     * @param GridColumn $column
     *
     * @return array
     */
    public function getTemplateVarsFromRowColumn($row, $column)
    {
        $element = $row->getData();
        assert($element instanceof User);
        switch ($column->getId()) {
            case 'select': // Displays the radio option
                return ['rowId' => $row->getId(), 'userId' => $this->_userId];

            case 'name': // User's name
                return ['label' => $element->getFullName()];

            case 'assignments': //User's assignments count
                $countUserAssignments = $this->getCountUserAssignments($element->getId());
                return ['label' => $countUserAssignments];

            case 'affiliation': // User's affiliations
                return ['label' => $element->getLocalizedAffiliation()];

            case 'interests': // User's interests
                $interests = implode(', ', Repo::userInterest()->getInterestsForUser($element));
                return ['label' => $interests];
        }
        assert(false);
    }

    private function getCountUserAssignments($userId)
    {
        $countAssignedSubmissions = Repo::submission()->getCollector()
            ->filterByContextIds([$this->_contextId])
            ->filterByStatus([Submission::STATUS_QUEUED])
            ->assignedTo([$userId])
            ->getCount();

        return $countAssignedSubmissions;
    }
}
