<?php

/**
 * @file pages/sitemap/PKPSitemapHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPSitemapHandler
 *
 * @ingroup pages_sitemap
 *
 * @brief Produce a sitemap in XML format for submitting to search engines.
 */

namespace PKP\pages\sitemap;

use APP\core\Application;
use APP\core\Request;
use APP\handler\Handler;
use DOMDocument;
use DOMNode;
use PKP\announcement\Announcement;
use PKP\db\DAORegistry;
use PKP\navigationMenu\NavigationMenuItem;
use PKP\navigationMenu\NavigationMenuItemDAO;

define('SITEMAP_XSD_URL', 'http://www.sitemaps.org/schemas/sitemap/0.9');

class PKPSitemapHandler extends Handler
{
    /**
     * Generate an XML sitemap for webcrawlers
     * Creates a sitemap index if in site context, else creates a sitemap
     *
     * @param array $args
     * @param Request $request
     */
    public function index($args, $request)
    {
        $context = $request->getContext();
        if (!$context) {
            $doc = $this->_createSitemapIndex($request);
            header('Content-Type: application/xml');
            header('Cache-Control: private');
            header('Content-Disposition: inline; filename=sitemap_index.xml');
            echo $doc->saveXml();
        } else {
            $doc = $this->_createContextSitemap($request);
            header('Content-Type: application/xml');
            header('Cache-Control: private');
            header('Content-Disposition: inline; filename=sitemap.xml');
            echo $doc->saveXml();
        }
    }

    /**
     * Construct a sitemap index listing each context's individual sitemap
     *
     * @param Request $request
     *
     * @return DOMDocument
     */
    public function _createSitemapIndex($request)
    {
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
    *
    * @param Request $request
    *
    * @return DOMDocument
    */
    public function _createContextSitemap($request)
    {
        $context = $request->getContext();

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
            $announcementIds = Announcement::withContextIds([$context->getId()])->pluck((new Announcement())->getKeyName())->toArray();

            foreach ($announcementIds as $announcementId) {
                $root->appendChild($this->_createUrlTree($doc, $request->url($context->getPath(), 'announcement', 'view', [$announcementId])));
            }
        }
        // About: context
        if (!empty($context->getData('about'))) {
            $root->appendChild($this->_createUrlTree($doc, $request->url($context->getPath(), 'about')));
        }
        // About: submissions
        $root->appendChild($this->_createUrlTree($doc, $request->url($context->getPath(), 'about', 'submissions')));
        // About: contact
        if (!empty($context->getData('mailingAddress')) || !empty($context->getData('contactName'))) {
            $root->appendChild($this->_createUrlTree($doc, $request->url($context->getPath(), 'about', 'contact')));
        }
        // Custom pages (navigation menu items)
        $navigationMenuItemDao = DAORegistry::getDAO('NavigationMenuItemDAO'); /** @var NavigationMenuItemDAO $navigationMenuItemDao */
        $menuItemsResult = $navigationMenuItemDao->getByType(NavigationMenuItem::NMI_TYPE_CUSTOM, $context->getId());
        while ($menuItem = $menuItemsResult->next()) {
            $root->appendChild($this->_createUrlTree($doc, $request->url($context->getPath(), $menuItem->getPath())));
        }

        $doc->appendChild($root);

        return $doc;
    }

    /**
     * Create a url entry with children
     *
     * @param DOMDocument $doc Reference to the XML document object
     * @param string $loc URL of page (required)
     * @param string $lastmod Last modification date of page (optional)
     * @param string $changefreq Frequency of page modifications (optional)
     * @param string $priority Subjective priority assessment of page (optional)
     *
     * @return DOMNode
     */
    protected function _createUrlTree($doc, $loc, $lastmod = null, $changefreq = null, $priority = null)
    {
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
