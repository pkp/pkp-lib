<?php
/**
 * @file controllers/grid/files/SelectableSubmissionFileListCategoryGridRow.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SelectableSubmissionFileListCategoryGridRow
 * @ingroup controllers_grid_files
 *
 * @brief Selectable submission file list category grid row definition.
 */

namespace PKP\controllers\grid\files;

use PKP\controllers\grid\GridCategoryRow;
use PKP\workflow\WorkflowStageDAO;

class SelectableSubmissionFileListCategoryGridRow extends GridCategoryRow
{
    //
    // Overridden methods from GridCategoryRow
    //
    /**
     * @copydoc GridCategoryRow::getCategoryLabel()
     */
    public function getCategoryLabel()
    {
        $stageId = $this->getData();
        $stageTranslationKey = WorkflowStageDAO::getTranslationKeyFromId($stageId);

        return __($stageTranslationKey);
    }
}
