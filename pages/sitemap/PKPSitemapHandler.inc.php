<?php

/**
 * @file pages/sitemap/PKPSitemapHandler.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPSitemapHandler
 * @ingroup pages_sitemap
 *
 * @brief Produce a sitemap in XML format for submitting to search engines.
 */

import('classes.handler.Handler');

define('SITEMAP_XSD_URL', 'https://www.sitemaps.org/schemas/sitemap/0.9');

class PKPSitemapHandler extends Handler {
	/**
	 * Generate an XML sitemap for webcrawlers
	 * Creates a sitemap index if in site context, else creates a sitemap
	 * @param $args array
	 * @param $request Request
	 */
	function index($args, $request) {
		$context = $request->getContext();
		if (!$context) {
			$doc = $this->_createSitemapIndex($request);
			header("Content-Type: application/xml");
			header("Cache-Control: private");
			header("Content-Disposition: inline; filename=\"sitemap_index.xml\"");
			echo $doc->saveXml();
		} else {
			$doc = $this->_createContextSitemap($request);
			header("Content-Type: application/xml");
			header("Cache-Control: private");
			header("Content-Disposition: inline; filename=\"sitemap.xml\"");
			echo $doc->saveXml();
		}
	}

	/**
	 * Construct a sitemap index listing each context's individual sitemap
	 * @param $request Request
	 * @return DOMDocument
	 */
	function _createSitemapIndex($request) {
		$contextDao = Application::getContextDAO();

		$doc = new DOMDocument('1.0', 'utf-8');
		$root = $doc->createElement('sitemapindex');
		$root->setAttribute('xmlns', SITEMAP_XSD_URL);

		$contexts = $contextDao->getAll(true);
		while ($context = $contexts->next()) {
			$sitemapUrl = $request->url($context->getPath(), 'sitemap');
			$sitemap = $doc->createElement('sitemap');
			$sitemap->appendChild($doc->createElement('loc', htmlspecialchars($sitemapUrl, ENT_COMPAT, 'UTF-8')));
			$root->appendChild($sitemap);
		}

		$doc->appendChild($root);
		return $doc;
	}

	 /**
	 * Construct the sitemap
	 * @param $request Request
	 * @return DOMDocument
	 */
	function _createContextSitemap($request) {
		$context = $request->getContext();
		$contextId = $context->getId();

		$doc = new DOMDocument('1.0', 'utf-8');

		$root = $doc->createElement('urlset');
		$root->setAttribute('xmlns', SITEMAP_XSD_URL);

		// Context home
		$root->appendChild($this->_createUrlTree($doc, $request->url($context->getPath())));
		// User register
		if ($context->getData('disableUserReg') != 1) {
			$root->appendChild($this->_createUrlTree($doc, $request->url($context->getPath(), 'user', 'register')));
		}
		// User login
		$root->appendChild($this->_createUrlTree($doc, $request->url($context->getPath(), 'login')));
		// Announcements
		if ($context->getData('enableAnnouncements') == 1) {
			$root->appendChild($this->_createUrlTree($doc, $request->url($context->getPath(), 'announcement')));
			$announcementDao = DAORegistry::getDAO('AnnouncementDAO');
			$contextAssocType = Application::getContextAssocType();
			$announcementsResult = $announcementDao->getByAssocId($contextAssocType, $contextId);
			while ($announcement = $announcementsResult->next()) {
				$root->appendChild($this->_createUrlTree($doc, $request->url($context->getPath(), 'announcement', 'view', $announcement->getId())));
			}
		}
		// About: context
		if (!empty($context->getData('about'))) {
			$root->appendChild($this->_createUrlTree($doc, $request->url($context->getPath(), 'about')));
		}
		// About: submissions
		$root->appendChild($this->_createUrlTree($doc, $request->url($context->getPath(), 'about', 'submissions')));
		// About: editorial team
		if (!empty($context->getData('editorialTeam'))) {
			$root->appendChild($this->_createUrlTree($doc, $request->url($context->getPath(), 'about', 'editorialTeam')));
		}
		// About: contact
		if (!empty($context->getData('mailingAddress')) || !empty($context->getData('contactName'))) {
			$root->appendChild($this->_createUrlTree($doc, $request->url($context->getPath(), 'about', 'contact')));
		}
		// Custom pages (navigation menu items)
		$navigationMenuItemDao = DAORegistry::getDAO('NavigationMenuItemDAO');
		$menuItemsResult = $navigationMenuItemDao->getByType(NMI_TYPE_CUSTOM, $contextId);
		while ($menuItem = $menuItemsResult->next()) {
			$root->appendChild($this->_createUrlTree($doc, $request->url($context->getPath(), $menuItem->getPath())));
		}

		$doc->appendChild($root);

		return $doc;
	}

	/**
	 * Create a url entry with children
	 * @param $doc DOMDocument Reference to the XML document object
	 * @param $loc string URL of page (required)
	 * @param $lastmod string Last modification date of page (optional)
	 * @param $changefreq Frequency of page modifications (optional)
	 * @param $priority string Subjective priority assessment of page (optional)
	 * @return DOMNode
	 */
	protected function _createUrlTree($doc, $loc, $lastmod = null, $changefreq = null, $priority = null) {
		$url = $doc->createElement('url');
		$url->appendChild($doc->createElement('loc', htmlspecialchars($loc, ENT_COMPAT, 'UTF-8')));
		if ($lastmod) {
			$url->appendChild($doc->createElement('lastmod', $lastmod));
		}
		if ($changefreq) {
			$url->appendChild($doc->createElement('changefreq', $changefreq));
		}
		if ($priority) {
			$url->appendChild($doc->createElement('priority', $priority));
		}
		return $url;
	}

}


