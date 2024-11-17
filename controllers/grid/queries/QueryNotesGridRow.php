<?php

/**
 * @file controllers/grid/queries/QueryNotesGridRow.php
 *
 * Copyright (c) 2016-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class QueryNotesGridRow
 *
 * @ingroup controllers_grid_queries
 *
 * @brief Base class for query grid row definition
 */

namespace PKP\controllers\grid\queries;

use APP\facades\Repo;
use PKP\controllers\grid\GridRow;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\RemoteActionConfirmationModal;
use PKP\query\Query;

class QueryNotesGridRow extends GridRow
{
    /** @var array */
    public $_actionArgs;

    /** @var Query */
    public $_query;

    /** @var QueryNotesGridHandler */
    public $_queryNotesGrid;

    /**
     * Constructor
     *
     * @param array $actionArgs Action arguments
     * @param Query $query
     * @param QueryNotesGridHandler $queryNotesGrid The notes grid containing this row
     */
    public function __construct($actionArgs, $query, $queryNotesGrid)
    {
        $this->_actionArgs = $actionArgs;
        $this->_query = $query;
        $this->_queryNotesGrid = $queryNotesGrid;

        parent::__construct();
    }

    //
    // Overridden methods from GridRow
    //
    /**
     * @copydoc GridRow::initialize()
     *
     * @param null|mixed $template
     */
    public function initialize($request, $template = null)
    {
        // Do the default initialization
        parent::initialize($request, $template);

        // Is this a new row or an existing row?
        $rowId = abs($this->getId());
        $headNote = Repo::note()->getHeadNote($this->getQuery()->id);
        if ($rowId > 0 && $headNote?->id != $rowId) {
            // Only add row actions if this is an existing row
            $router = $request->getRouter();
            $actionArgs = array_merge(
                $this->_actionArgs,
                ['noteId' => $rowId]
            );

            // Add row-level actions
            if ($this->_queryNotesGrid->getCanManage($this->getData())) {
                $this->addAction(
                    new LinkAction(
                        'deleteNote',
                        new RemoteActionConfirmationModal(
                            $request->getSession(),
                            __('common.confirmDelete'),
                            __('grid.action.delete'),
                            $router->url($request, null, null, 'deleteNote', null, $actionArgs),
                            'negative'
                        ),
                        __('grid.action.delete'),
                        'delete'
                    )
                );
            }
        }
    }

    /**
     * Get the query
     *
     * @return Query
     */
    public function getQuery()
    {
        return $this->_query;
    }

    /**
     * Get the base arguments that will identify the data in the grid.
     *
     * @return array
     */
    public function getRequestArgs()
    {
        return $this->_actionArgs;
    }
}
