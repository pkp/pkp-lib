<?php
/**
 * @file classes/components/listPanelssubmissions/SelectSubmissionsListPanel.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SelectSubmissionsListPanel
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
		$config['i18n']['listSeparator'] = __('common.commaListSeparator');
		$config['i18n']['viewSubmission'] = __('submission.list.viewSubmission');
		$config['i18n']['paginationLabel'] = __('common.pagination.label');
		$config['i18n']['goToLabel'] = __('common.pagination.goToPage');
		$config['i18n']['pageLabel'] = __('common.pageNumber');
		$config['i18n']['nextPageLabel'] = __('common.pagination.next');
		$config['i18n']['previousPageLabel'] = __('common.pagination.previous');
		return $config;
	}
}
