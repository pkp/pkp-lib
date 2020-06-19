<?php

/**
 * @file classes/install/Upgrade.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Upgrade
 * @ingroup install
 *
 * @brief Perform system upgrade.
 */

import('lib.pkp.classes.install.Installer');

class Upgrade extends Installer {
	/**
	 * Constructor.
	 * @param $params array upgrade parameters
	 */
	function __construct($params, $installFile = 'upgrade.xml', $isPlugin = false) {
		parent::__construct($installFile, $params, $isPlugin);
	}


	/**
	 * Returns true iff this is an upgrade process.
	 * @return boolean
	 */
	function isUpgrade() {
		return true;
	}

	//
	// Upgrade actions
	//

	/**
	 * Rebuild the search index.
	 * @return boolean
	 */
	function rebuildSearchIndex() {
		$submissionSearchIndex = Application::getSubmissionSearchIndex();
		$submissionSearchIndex->rebuildIndex();
		return true;
	}

	/**
	 * Clear the CSS cache files (needed when changing LESS files)
	 * @return boolean
	 */
	function clearCssCache() {
		$request = Application::get()->getRequest();
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->clearCssCache();
		return true;
	}

	/**
	 * Submissions with stage_id=WORKFLOW_STAGE_ID_SUBMISSION should be changed to stage_id=WORKFLOW_STAGE_ID_PRODUCTION, which is the only stage in OPS
	 * @return boolean
	 */
	function changeSubmissionStageToProduction() {
		$submissioDao = DAORegistry::getDAO('SubmissionDAO');
		$submissioDao->update('UPDATE submissions SET stage_id = ? WHERE stage_id = ?', array(WORKFLOW_STAGE_ID_PRODUCTION, WORKFLOW_STAGE_ID_SUBMISSION));

		return true;
	}
}
