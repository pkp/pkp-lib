<?php

/**
 * @file controllers/grid/users/stageParticipant/StageParticipantGridCategoryRow.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class StageParticipantGridCategoryRow
 * @ingroup controllers_grid_users_stageParticipant
 *
 * @brief Stage participant grid category row definition
 */

namespace PKP\controllers\grid\users\stageParticipant;

use PKP\controllers\grid\GridCategoryRow;

// Link actions

class StageParticipantGridCategoryRow extends GridCategoryRow
{
    /** @var Submission */
    public $_submission;

    /** @var int */
    public $_stageId;

    /**
     * Constructor
     */
    public function __construct($submission, $stageId)
    {
        $this->_submission = $submission;
        $this->_stageId = $stageId;
        parent::__construct();
    }

    //
    // Overridden methods from GridCategoryRow
    //
    /**
     * @copydoc GridCategoryRow::getCategoryLabel()
     */
    public function getCategoryLabel()
    {
        $userGroup = $this->getData();
        return $userGroup->getLocalizedName();
    }

    //
    // Private methods
    //
    /**
     * Get the submission for this row (already authorized)
     *
     * @return Submission
     */
    public function getSubmission()
    {
        return $this->_submission;
    }

    /**
     * Get the stage ID for this grid.
     *
     * @return int
     */
    public function getStageId()
    {
        return $this->_stageId;
    }
}
