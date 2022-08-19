<?php

/**
 * @file controllers/grid/queries/QueryNotesGridCellProvider.php
 *
 * Copyright (c) 2016-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class QueryNotesGridCellProvider
 * @ingroup controllers_grid_queries
 *
 * @brief Base class for a cell provider that can retrieve query note info.
 */

namespace PKP\controllers\grid\queries;

use APP\core\Application;
use APP\facades\Repo;
use PKP\controllers\api\file\linkAction\DownloadFileLinkAction;
use PKP\controllers\grid\DataObjectGridCellProvider;
use PKP\controllers\grid\GridColumn;
use PKP\controllers\grid\GridHandler;
use PKP\core\PKPString;
use PKP\submissionFile\SubmissionFile;

class QueryNotesGridCellProvider extends DataObjectGridCellProvider
{
    /** @var Submission */
    public $_submission;

    /**
     * Constructor
     *
     * @param Submission $submission
     */
    public function __construct($submission)
    {
        parent::__construct();
        $this->_submission = $submission;
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
        $user = $element->getUser();
        $datetimeFormatShort = PKPString::convertStrftimeFormat(Application::get()->getRequest()->getContext()->getLocalizedDateTimeFormatShort());

        switch ($columnId) {
            case 'from':
                return ['label' => ($user ? $user->getUsername() : '&mdash;') . '<br />' . date($datetimeFormatShort, strtotime($element->getDateCreated()))];
        }

        return parent::getTemplateVarsFromRowColumn($row, $column);
    }

    /**
     * @copydoc GridCellProvider::getCellActions()
     */
    public function getCellActions($request, $row, $column, $position = GridHandler::GRID_ACTION_POSITION_DEFAULT)
    {
        switch ($column->getId()) {
            case 'contents':
                $submissionFiles = Repo::submissionFile()
                    ->getCollector()
                    ->filterByAssoc(
                        ASSOC_TYPE_NOTE,
                        [$row->getData()->getId()]
                    )->filterBySubmissionIds([$this->_submission->getId()])
                    ->filterByFileStages([SubmissionFile::SUBMISSION_FILE_QUERY])
                    ->getMany();

                $actions = [];
                foreach ($submissionFiles as $submissionFile) {
                    $actions[] = new DownloadFileLinkAction($request, $submissionFile, $request->getUserVar('stageId'));
                }
                return $actions;
        }
        return parent::getCellActions($request, $row, $column, $position);
    }
}
