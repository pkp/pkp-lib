<?php
/**
 * @file classes/components/listPanels/PKPSelectSubmissionsListPanel.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPSelectSubmissionsListPanel
 * @ingroup classes_components_list
 *
 * @brief A ListPanel component for selecting submissions
 */
namespace PKP\components\listPanels;
use PKP\components\listPanels;

class PKPSelectSubmissionsListPanel extends ListPanel {
	/**
	 * @copydoc ListPanel::getConfig()
	 */
	public function getConfig() {
		$config = parent::getConfig();
		$config['18n'] = array_merge($config['i18n'], [
			'listSeparator' => __('common.commaListSeparator'),
			'viewSubmission' => __('submission.list.viewSubmission'),
			'paginationLabel' => __('common.pagination.label'),
			'goToLabel' => __('common.pagination.goToPage'),
			'pageLabel' => __('common.pageNumber'),
			'nextPageLabel' => __('common.pagination.next'),
			'previousPageLabel' => __('common.pagination.previous'),
		]);
		return $config;
	}
}
