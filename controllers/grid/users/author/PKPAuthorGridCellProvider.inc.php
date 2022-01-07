<?php

/**
 * @file controllers/grid/users/author/PKPAuthorGridCellProvider.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DataObjectGridCellProvider
 * @ingroup controllers_grid_users_author
 *
 * @brief Base class for a cell provider that can retrieve labels for submission contributors
 */

use PKP\controllers\grid\DataObjectGridCellProvider;
use PKP\controllers\grid\GridColumn;

class PKPAuthorGridCellProvider extends DataObjectGridCellProvider
{
    /** @var Publication The publication this author is related to */
    private $_publication;

    /**
     * Constructor
     *
     * @param Publication $publication
     */
    public function __construct($publication)
    {
        $this->_publication = $publication;
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
        $columnId = $column->getId();
        assert($element instanceof \PKP\core\DataObject && !empty($columnId));
        switch ($columnId) {
            case 'name':
                return ['label' => $element->getFullName()];
            case 'role':
                return ['label' => $element->getLocalizedUserGroupName()];
            case 'email':
                return parent::getTemplateVarsFromRowColumn($row, $column);
            case 'principalContact':
                return ['isPrincipalContact' => $this->_publication->getData('primaryContactId') === $element->getId()];
            case 'includeInBrowse':
                return ['includeInBrowse' => $element->getIncludeInBrowse()];
        }
    }
}
