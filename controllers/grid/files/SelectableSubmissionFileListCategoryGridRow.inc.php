<?php
/**
 * @file controllers/grid/files/SelectableSubmissionFileListCategoryGridRow.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SelectableSubmissionFileListCategoryGridRow
 * @ingroup controllers_grid_files
 *
 * @brief Selectable submission file list category grid row definition.
 */

import('lib.pkp.classes.controllers.grid.GridCategoryRow');

class SelectableSubmissionFileListCategoryGridRow extends GridCategoryRow {

	/**
	 * Constructor
	 */
	function SelectableSubmissionFileListCategoryGridRow() {
		parent::GridCategoryRow();
	}

	//
	// Overridden methods from GridCategoryRow
	//
	/**
	 * @copydoc GridCategoryRow::getCategoryLabel()
	 */
	function getCategoryLabel() {
		$stageId = $this->getData();
		$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
		$stageTranslationKey = $userGroupDao->getTranslationKeyFromId($stageId);

		return __($stageTranslationKey);
	}
}

?>
