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

use PKP\controllers\grid\DataObjectGridCellProvider;
use PKP\controllers\grid\GridColumn;

class UserSelectGridCellProvider extends DataObjectGridCellProvider
{
    /** @var int User ID of already-selected user */
    public $_userId;

    /**
     * Constructor
     *
     * @param int $userId ID of preselected user.
     */
    public function __construct($userId = null)
    {
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
        assert(is_a($element, 'User'));
        switch ($column->getId()) {
            case 'select': // Displays the radio option
                return ['rowId' => $row->getId(), 'userId' => $this->_userId];

            case 'name': // User's name
                return ['label' => $element->getFullName()];
        }
        assert(false);
    }
}
