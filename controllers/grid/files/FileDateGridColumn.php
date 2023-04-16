<?php

/**
 * @file controllers/grid/files/FileDateGridColumn.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 * Borrowed from FileDateGridColumn.php
 *
 * @class FileDateGridColumn
 *
 * @ingroup controllers_grid_files
 *
 * @brief Implements a file name column.
 */

namespace PKP\controllers\grid\files;

use APP\core\Application;
use PKP\controllers\grid\ColumnBasedGridCellProvider;
use PKP\controllers\grid\GridColumn;
use PKP\core\PKPString;

class FileDateGridColumn extends GridColumn
{
    /** @var ?int */
    public $_stageId;

    /** @var bool */
    public $_includeNotes;

    /**
     * Constructor
     *
     * @param bool $includeNotes
     * without the history tab.
     */
    public function __construct($includeNotes = true)
    {
        $this->_includeNotes = $includeNotes;

        $cellProvider = new ColumnBasedGridCellProvider();

        parent::__construct(
            'date',
            'common.date',
            null,
            null,
            $cellProvider,
            ['width' => 10, 'alignment' => GridColumn::COLUMN_ALIGNMENT_LEFT, 'anyhtml' => true]
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
        $submissionFileData = $row->getData();
        $submissionFile = $submissionFileData['submissionFile'];
        assert($submissionFile instanceof \PKP\submissionFile\SubmissionFile);
        $mtimestamp = strtotime($submissionFile->getData('updatedAt'));
        $dateFormatLong = PKPString::convertStrftimeFormat(Application::get()->getRequest()->getContext()->getLocalizedDateFormatLong());
        $date = date($dateFormatLong, $mtimestamp);
        // File age
        $age = (int)floor((date('U') - $mtimestamp) / 86400);
        switch (true) {
            case $age <= 7:
                $cls = ' pkp_helpers_text_warn';
                break;
            case $age <= 28:
                $cls = ' pkp_helpers_text_primary';
                break;
            default:
                $cls = '';
                break;
        }
        return ['label' => sprintf(
            "<span class='label%s'>%s</span>",
            $cls,
            htmlspecialchars($date)
        )];
    }

    //
    // Private methods
    //
    /**
     * Determine whether or not submission note status should be included.
     */
    public function _getIncludeNotes()
    {
        return $this->_includeNotes;
    }

    /**
     * Get stage id, if any.
     *
     * @return mixed int or null
     */
    public function _getStageId()
    {
        return $this->_stageId;
    }
}
