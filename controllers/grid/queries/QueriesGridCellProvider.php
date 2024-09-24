<?php

/**
 * @file controllers/grid/queries/QueriesGridCellProvider.php
 *
 * Copyright (c) 2016-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class QueriesGridCellProvider
 *
 * @ingroup controllers_grid_queries
 *
 * @brief Base class for a cell provider that can retrieve labels for queries.
 */

namespace PKP\controllers\grid\queries;

use APP\core\Application;
use APP\facades\Repo;
use APP\submission\Submission;
use Illuminate\Database\Eloquent\Model;
use PKP\controllers\grid\DataObjectGridCellProvider;
use PKP\controllers\grid\GridColumn;
use PKP\controllers\grid\GridHandler;
use PKP\core\DataObject;
use PKP\core\PKPApplication;
use PKP\core\PKPString;
use PKP\facades\Locale;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxAction;
use PKP\note\Note;
use PKP\query\Query;

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
        assert(($element instanceof DataObject || $element instanceof Model) && !empty($columnId));
        /** @var Query $element */
        $headNote = Repo::note()->getHeadNote($element->id);
        $user = $headNote?->user;
        $notes = Note::withAssoc(PKPApplication::ASSOC_TYPE_QUERY, $element->id)
            ->withSort(Note::NOTE_ORDER_ID)
            ->lazy();
        $context = Application::get()->getRequest()->getContext();
        $datetimeFormatShort = PKPString::convertStrftimeFormat($context->getLocalizedDateTimeFormatShort());

        switch ($columnId) {
            case 'replies':
                return ['label' => max(0, $notes->count() - 1)];
            case 'from':
                return ['label' => ($user?->getUsername() ?? '&mdash;') . '<br />' . $headNote?->dateCreated->format($datetimeFormatShort)];
            case 'lastReply':
                $latestReply = $notes->first();
                if ($latestReply && $latestReply->id != $headNote->id) {
                    $repliedUser = $latestReply->user;
                    return ['label' => ($repliedUser?->getUsername() ?? '&mdash;') . '<br />' . $latestReply->dateCreated->format($datetimeFormatShort)];
                } else {
                    return ['label' => '-'];
                }
                // no break
            case 'closed':
                return [
                    'selected' => $element->closed,
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
                    $enabled = !$element->closed;
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
