<?php
/**
 * @defgroup controllers_grid_files_fileList_linkAction File List Link Actions
 */

/**
 * @file controllers/grid/files/fileList/linkAction/DownloadAllLinkAction.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DownloadAllLinkAction
 * @ingroup controllers_grid_files_fileList_linkAction
 *
 * @brief An action to download all files in a submission file grid.
 */

import('lib.pkp.classes.linkAction.LinkAction');

class DownloadAllLinkAction extends LinkAction {

	/**
	 * Constructor
	 * @param $request Request
	 * @param $actionArgs array
	 * @param $files array Files to be downloaded.
	 */
	function __construct($request, $actionArgs) {
		// Instantiate the redirect action request.
		$router = $request->getRouter();
		import('lib.pkp.classes.linkAction.request.PostAndRedirectAction');
		$redirectRequest = new PostAndRedirectAction($router->url($request, null, 'api.file.FileApiHandler', 'recordDownload', null, $actionArgs),
			$router->url($request, null, 'api.file.FileApiHandler', 'downloadAllFiles', null, $actionArgs));

		// Configure the link action.
		parent::__construct('downloadAll', $redirectRequest, __('submission.files.downloadAll'), 'getPackage');
	}
}


