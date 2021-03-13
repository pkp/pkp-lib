<?php

/**
 * @file pages/sitemap/SitemapHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SitemapHandler
 * @ingroup pages_sitemap
 *
 * @brief Produce a sitemap in XML format for submitting to search engines.
 */

import('lib.pkp.pages.sitemap.PKPSitemapHandler');

class SitemapHandler extends PKPSitemapHandler {

	/**
	 * @copydoc PKPSitemapHandler_createContextSitemap()
	 */
	function _createContextSitemap($request) {
		$doc = parent::_createContextSitemap($request);
		$root = $doc->documentElement;

		$server = $request->getServer();
		$serverId = $server->getId();

		// Search
		$root->appendChild($this->_createUrlTree($doc, $request->url($server->getPath(), 'search')));

		// Preprints
		import('classes.submission.Submission'); // Import status constants
		$submissionIds = Services::get('submission')->getIds([
			'status' => STATUS_PUBLISHED,
			'contextId' => $server->getId(),
		]);
		foreach ($submissionIds as $submissionId) {
			$root->appendChild($this->_createUrlTree($doc, $request->url($server->getPath(), 'preprint', 'view', array($submissionId))));
		}

		$doc->appendChild($root);

		// Enable plugins to change the sitemap
		HookRegistry::call('SitemapHandler::createServerSitemap', array(&$doc));

		return $doc;
	}

}


