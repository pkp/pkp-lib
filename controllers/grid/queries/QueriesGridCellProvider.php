<?php

/**
 * @file controllers/grid/queries/QueriesGridCellProvider.php
 *
 * Copyright (c) 2016-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class QueriesGridCellProvider
 * @ingroup controllers_grid_queries
 *
 * @brief Base class for a cell provider that can retrieve labels for queries.
 */

namespace PKP\controllers\grid\queries;

use APP\core\Application;
use PKP\controllers\grid\DataObjectGridCellProvider;
use PKP\controllers\grid\GridColumn;
use PKP\controllers\grid\GridHandler;
use PKP\core\PKPString;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxAction;
use PKP\note\NoteDAO;

class QueriesGridCellProvider extends DataObjectGridCellProvider
{
    /** @var Submission */
    public $_submission;

    /** @var int */
    public $_stageId;

    /** @var QueriesAccessHelper */
    public $_queriesAccessHelper;

    /**
     * Constructor
     *
     * @param Submission $submission
     * @param int $stageId
     * @param QueriesAccessHelper $queriesAccessHelper
     */
    public function __construct($submission, $stageId, $queriesAccessHelper)
    {
        parent::__construct();
        $this->_submission = $submission;
        $this->_stageId = $stageId;
        $this->_queriesAccessHelper = $queriesAccessHelper;
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

        $headNote = $element->getHeadNote();
        $user = $headNote ? $headNote->getUser() : null;
        $notes = $element->getReplies(null, NoteDAO::NOTE_ORDER_ID, \PKP\db\DAO::SORT_DIRECTION_DESC);
        $context = Application::get()->getRequest()->getContext();
        $datetimeFormatShort = PKPString::convertStrftimeFormat($context->getLocalizedDateTimeFormatShort());

        switch ($columnId) {
            case 'replies':
                return ['label' => max(0, $notes->getCount() - 1)];
            case 'from':
                return ['label' => ($user ? $user->getUsername() : '&mdash;') . '<br />' . ($headNote ? date($datetimeFormatShort, strtotime($headNote->getDateCreated())) : '')];
            case 'lastReply':
                $latestReply = $notes->next();
                if ($latestReply && $latestReply->getId() != $headNote->getId()) {
                    $repliedUser = $latestReply->getUser();
                    return ['label' => ($repliedUser ? $repliedUser->getUsername() : '&mdash;') . '<br />' . date($datetimeFormatShort, strtotime($latestReply->getDateCreated()))];
                } else {
                    return ['label' => '-'];
                }
                // no break
            case 'closed':
                return [
                    'selected' => $element->getIsClosed(),
                    'disabled' => !$this->_queriesAccessHelper->getCanOpenClose($element),
                ];
        }
        return parent::getTemplateVarsFromRowColumn($row, $column);
    }

    /**
     * @copydoc GridCellProvider::getCellActions()
     */
    public function getCellActions($request, $row, $column, $position = GridHandler::GRID_ACTION_POSITION_DEFAULT)
    {
        $element = $row->getData();
        $router = $request->getRouter();
        $actionArgs = $this->getRequestArgs($row);
        switch ($column->getId()) {
            case 'closed':
                if ($this->_queriesAccessHelper->getCanOpenClose($element)) {
                    $enabled = !$element->getIsClosed();
                    if ($enabled) {
                        return [new LinkAction(
                            'close-' . $row->getId(),
                            new AjaxAction($router->url($request, null, null, 'closeQuery', null, $actionArgs)),
                            null,
                            null
                        )];
                    } else {
                        return [new LinkAction(
                            'open-' . $row->getId(),
                            new AjaxAction($router->url($request, null, null, 'openQuery', null, $actionArgs)),
                            null,
                            null
                        )];
                    }
                }
                break;
        }
        return parent::getCellActions($request, $row, $column, $position);
    }

    /**
     * Get request arguments.
     *
     * @param \PKP\controllers\grid\GridRow $row
     *
     * @return array
     */
    public function getRequestArgs($row)
    {
        return [
            'submissionId' => $this->_submission->getId(),
            'stageId' => $this->_stageId,
            'queryId' => $row->getId(),
        ];
    }
}
