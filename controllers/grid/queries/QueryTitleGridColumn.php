<?php

/**
 * @file controllers/grid/queries/QueryTitleGridColumn.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class QueryTitleGridColumn
 * @ingroup controllers_grid_queriess
 *
 * @brief Implements a query tile column.
 */

namespace PKP\controllers\grid\queries;

use PKP\controllers\grid\ColumnBasedGridCellProvider;
use PKP\controllers\grid\GridColumn;
use PKP\controllers\grid\GridHandler;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;

class QueryTitleGridColumn extends GridColumn
{
    /** @var array Action args for link actions */
    public $_actionArgs;

    /**
     * Constructor
     *
     * @param array $actionArgs Action args for link actions
     */
    public function __construct($actionArgs)
    {
        $this->_actionArgs = $actionArgs;

        $cellProvider = new ColumnBasedGridCellProvider();

        parent::__construct(
            'name',
            'common.name',
            null,
            null,
            $cellProvider,
            ['width' => 60, 'alignment' => GridColumn::COLUMN_ALIGNMENT_LEFT]
        );
    }


    //
    // Public methods
    //
    /**
     * Method expected by ColumnBasedGridCellProvider
     * to render a cell in this column.
     *
     * @copydoc ColumnBasedGridCellProvider::getTemplateVarsFromRowColumn()
     */
    public function getTemplateVarsFromRow($row)
    {
        // We do not need any template variables because
        // the only content of this column's cell will be
        // an action. See QueryTitleGridColumn::getCellActions().
        return ['label' => ''];
    }


    //
    // Override methods from GridColumn
    //
    /**
     * @copydoc GridColumn::getCellActions()
     */
    public function getCellActions($request, $row, $position = GridHandler::GRID_ACTION_POSITION_DEFAULT)
    {
        // Retrieve the submission file.
        $query = $row->getData();
        $headNote = $query->getHeadNote();

        // Create the cell action to download a file.
        $router = $request->getRouter();
        $actionArgs = array_merge(
            $this->_actionArgs,
            ['queryId' => $query->getId()]
        );

        return array_merge(
            parent::getCellActions($request, $row, $position),
            [
                new LinkAction(
                    'readQuery',
                    new AjaxModal(
                        $router->url($request, null, null, 'readQuery', null, $actionArgs),
                        $headNote ? $headNote->getTitle() : '&mdash;',
                        'modal_edit'
                    ),
                    ($headNote && $headNote->getTitle() != '') ? htmlspecialchars($headNote->getTitle()) : '&mdash;',
                    null
                )
            ]
        );
    }
}
