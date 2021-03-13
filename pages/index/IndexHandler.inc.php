<?php

/**
 * @file pages/index/IndexHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class IndexHandler
 * @ingroup pages_index
 *
 * @brief Handle site index requests.
 */

import('lib.pkp.pages.index.PKPIndexHandler');

class IndexHandler extends PKPIndexHandler {
	//
	// Public handler operations
	//
	/**
	 * If no server is selected, display list of servers.
	 * Otherwise, display the index page for the selected server.
	 * @param $args array
	 * @param $request Request
	 */
	function index($args, $request) {
		$this->validate(null, $request);
		$server = $request->getServer();

		if (!$server) {
			$server = $this->getTargetContext($request, $hasNoContexts);
			if ($server) {
				// There's a target context but no server in the current request. Redirect.
				$request->redirect($server->getPath());
			}
			if ($hasNoContexts && Validation::isSiteAdmin()) {
				// No contexts created, and this is the admin.
				$request->redirect(null, 'admin', 'contexts');
			}
		}

		$this->setupTemplate($request);
		$router = $request->getRouter();
		$templateMgr = TemplateManager::getManager($request);
		if ($server) {

			// OPS: sections
			$sectionDao = DAORegistry::getDAO('SectionDAO'); /* @var $sectionDao SectionDAO */
			$sections = $sectionDao->getByContextId($server->getId());

			// OPS: categories
			$categoryDao = DAORegistry::getDAO('CategoryDAO'); /* @var $categoryDao CategoryDAO */
			$categories = $categoryDao->getByContextId($server->getId());			

			// Latest preprints
			import('classes.submission.Submission');
			$submissionService = Services::get('submission');
			$params = array(
				'contextId' => $server->getId(),
				'count' => '10',
				'orderBy' => 'datePublished',
				'status' => STATUS_PUBLISHED,
			);
			$publishedSubmissions = $submissionService->getMany($params);

			// Assign header and content for home page
			$templateMgr->assign(array(
				'additionalHomeContent' => $server->getLocalizedData('additionalHomeContent'),
				'homepageImage' => $server->getLocalizedData('homepageImage'),
				'homepageImageAltText' => $server->getLocalizedData('homepageImageAltText'),
				'serverDescription' => $server->getLocalizedData('description'),
				'sections' => $sections,
				'categories' => $categories,
				'pubIdPlugins' => PluginRegistry::loadCategory('pubIds', true),
				'publishedSubmissions' => $publishedSubmissions,
			));

			$this->_setupAnnouncements($server, $templateMgr);

			$templateMgr->display('frontend/pages/indexServer.tpl');
		} else {
			$serverDao = DAORegistry::getDAO('ServerDAO'); /* @var $serverDao ServerDAO */
			$site = $request->getSite();

			if ($site->getRedirect() && ($server = $serverDao->getById($site->getRedirect())) != null) {
				$request->redirect($server->getPath());
			}

			$templateMgr->assign(array(
				'pageTitleTranslated' => $site->getLocalizedTitle(),
				'about' => $site->getLocalizedAbout(),
				'serverFilesPath' => $request->getBaseUrl() . '/' . Config::getVar('files', 'public_files_dir') . '/contexts/',
				'servers' => $serverDao->getAll(true),
				'site' => $site,
			));
			$templateMgr->setCacheability(CACHEABILITY_PUBLIC);
			$templateMgr->display('frontend/pages/indexSite.tpl');
		}
	}
}


