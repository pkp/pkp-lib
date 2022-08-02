<?php

/**
 * @file controllers/listbuilder/users/UserListbuilderGridCellProvider.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserListbuilderGridCellProvider
 * @ingroup controllers_grid
 *
 * @brief Base class for a cell provider that can retrieve labels from arrays
 */

namespace PKP\controllers\listbuilder\users;

use PKP\controllers\grid\GridCellProvider;
use PKP\controllers\grid\GridColumn;

class UserListbuilderGridCellProvider extends GridCellProvider
{
    //
    // Template methods from GridCellProvider
    //
    /**
     * This implementation assumes a simple data element array that
     * has column ids as keys.
     *
     * @see GridCellProvider::getTemplateVarsFromRowColumn()
     *
     * @param \PKP\controllers\grid\GridRow $row
     * @param GridColumn $column
     *
     * @return array
     */
    public function getTemplateVarsFromRowColumn($row, $column)
    {
        $user = & $row->getData();
        $columnId = $column->getId();
        // Allow for either Users or Authors (both have a getFullName method).
        assert((is_a($user, 'User') || is_a($user, 'Author')) && !empty($columnId));

        return ['labelKey' => $user->getId(), 'label' => $user->getFullName() . ' <' . $user->getEmail() . '>'];
    }
}
