<?php

/**
 * @file classes/plugins/PluginGalleryDAO.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PluginGalleryDAO
 * @ingroup plugins
 *
 * @see DAO
 *
 * @brief Operations for retrieving content from the PKP plugin gallery.
 */

namespace PKP\plugins;

use APP\core\Application;
use DOMDocument;
use DOMElement;
use PKP\cache\CacheManager;

use PKP\cache\FileCache;
use PKP\controllers\grid\plugins\PluginGalleryGridHandler;
use PKP\core\PKPString;
use PKP\db\DAORegistry;

use Throwable;

class PluginGalleryDAO extends \PKP\db\DAO
{
    public const PLUGIN_GALLERY_XML_URL = 'https://pkp.sfu.ca/ojs/xml/plugins.xml';

    /**
     * The default timeout (in seconds) to wait plugins.xml request
     *
     * @see https://docs.guzzlephp.org/en/6.5/request-options.html#timeout
     */
    public const DEFAULT_TIMEOUT = 10;

    /**
     * TTL's Cache in seconds
     */
    public const TTL_CACHE_SECONDS = 86400;

    /**
     * Get a set of GalleryPlugin objects describing the available
     * compatible plugins in their newest versions.
     *
     * @param PKPApplication $application
     * @param string $category Optional category name to use as filter
     * @param string $search Optional text to use as filter
     *
     * @return array GalleryPlugin objects
     */
    public function getNewestCompatible($application, $category = null, $search = null)
    {
        $doc = $this->_getDocument();
        $plugins = [];

        foreach ($doc->getElementsByTagName('plugin') as $index => $element) {
            $plugin = $this->_compatibleFromElement($element, $application);
            // May be null if no compatible version exists; also
            // apply search filters if any supplied.
            if (
                $plugin &&
                ($category == '' || $category == PluginGalleryGridHandler::PLUGIN_GALLERY_ALL_CATEGORY_SEARCH_VALUE || $plugin->getCategory() == $category) &&
                ($search == '' || PKPString::strpos(PKPString::strtolower(serialize($plugin)), PKPString::strtolower($search)) !== false)
            ) {
                $plugins[$index] = $plugin;
            }
        }

        return $plugins;
    }

    /**
     * Get the external Plugin XML document
     *
     * @return ?string
     */
    protected function getExternalDocument(): ?string
    {
        $application = Application::get();
        $client = $application->getHttpClient();
        $versionDao = DAORegistry::getDAO('VersionDAO');
        $currentVersion = $versionDao->getCurrentVersion();
        try {
            $response = $client->request(
                'GET',
                PLUGIN_GALLERY_XML_URL,
                [
                    'query' => [
                        'application' => $application->getName(),
                        'version' => $currentVersion->getVersionString()
                    ],
                    'timeout' => self::DEFAULT_TIMEOUT,
                ]
            );

            return $response->getBody();
        } catch (Throwable $e) {
            error_log($e->getMessage());

            return null;
        }
    }

    /**
     * Get the cached Plugin XML document
     *
     * @return ?string
     */
    protected function getCachedDocument(): ?string
    {
        $cacheManager = CacheManager::getManager();
        $cache = $cacheManager->getCache(
            'loadPluginsXML',
            Application::CONTEXT_SITE,
            function (FileCache $cache) {
                $cache->setEntireCache($this->getExternalDocument());
            }
        );

        $cacheTime = $cache->getCacheTime();

        // Checking if the cache is older than 1 day, or its null
        if ($cacheTime === null || (time() - $cacheTime > self::TTL_CACHE_SECONDS)) {
            // This cache is out of date; so, lets request a new version.
            $response = $this->getExternalDocument();

            // The plugins.xml request wasnt empty, so lets replace it
            if ($response !== null) {
                $cache->setEntireCache($response);
            }
        }

        return $cache->getContents();
    }

    /**
     * Get the DOM document for the plugin gallery.
     *
     * @return DOMDocument
     */
    private function _getDocument()
    {
        $doc = new DOMDocument('1.0');
        $doc->loadXML($this->getCachedDocument());

        return $doc;
    }

    /**
     * Construct a new data object.
     *
     * @return GalleryPlugin
     */
    public function newDataObject()
    {
        return new GalleryPlugin();
    }

    /**
     * Build a GalleryPlugin from a DOM element, using the newest compatible
     * release with the supplied Application.
     *
     * @param DOMElement $element
     * @param Application $application
     *
     * @return GalleryPlugin|null, if no compatible plugin was available
     */
    protected function _compatibleFromElement($element, $application)
    {
        $plugin = $this->newDataObject();
        $plugin->setCategory($element->getAttribute('category'));
        $plugin->setProduct($element->getAttribute('product'));
        $doc = $element->ownerDocument;
        $foundRelease = false;
        for ($n = $element->firstChild; $n; $n = $n->nextSibling) {
            if (!($n instanceof DOMElement)) {
                continue;
            }
            switch ($n->tagName) {
                case 'name':
                    $plugin->setName($n->nodeValue, $n->getAttribute('locale'));
                    break;
                case 'homepage':
                    $plugin->setHomepage($n->nodeValue);
                    break;
                case 'description':
                    $plugin->setDescription($n->nodeValue, $n->getAttribute('locale'));
                    break;
                case 'installation':
                    $plugin->setInstallationInstructions($n->nodeValue, $n->getAttribute('locale'));
                    break;
                case 'summary':
                    $plugin->setSummary($n->nodeValue, $n->getAttribute('locale'));
                    break;
                case 'maintainer':
                    $this->_handleMaintainer($n, $plugin);
                    break;
                case 'release':
                    // If a compatible release couldn't be
                    // found, return null.
                    if ($this->_handleRelease($n, $plugin, $application)) {
                        $foundRelease = true;
                    }
                    break;
                default:
                // Not erroring out here so that future
                // additions won't break old releases.
                }
        }
        if (!$foundRelease) {
            // No compatible release was found.
            return null;
        }
        return $plugin;
    }

    /**
     * Handle a maintainer element
     *
     * @param GalleryPlugin $plugin
     */
    public function _handleMaintainer($element, $plugin)
    {
        for ($n = $element->firstChild; $n; $n = $n->nextSibling) {
            if (!($n instanceof DOMElement)) {
                continue;
            }
            switch ($n->tagName) {
                case 'name':
                    $plugin->setContactName($n->nodeValue);
                    break;
                case 'institution':
                    $plugin->setContactInstitutionName($n->nodeValue);
                    break;
                case 'email':
                    $plugin->setContactEmail($n->nodeValue);
                    break;
                default:
                // Not erroring out here so that future
                // additions won't break old releases.
                }
        }
    }

    /**
     * Handle a release element
     *
     * @param GalleryPlugin $plugin
     * @param PKPApplication $application
     */
    public function _handleRelease($element, $plugin, $application)
    {
        $release = [
            'date' => strtotime($element->getAttribute('date')),
            'version' => $element->getAttribute('version'),
            'md5' => $element->getAttribute('md5'),
        ];

        $compatible = false;
        for ($n = $element->firstChild; $n; $n = $n->nextSibling) {
            if (!($n instanceof DOMElement)) {
                continue;
            }
            switch ($n->tagName) {
                case 'description':
                    $release[$n->tagName][$n->getAttribute('locale')] = $n->nodeValue;
                    break;
                case 'package':
                    $release['package'] = $n->nodeValue;
                    break;
                case 'compatibility':
                    // If a compatible release couldn't be
                    // found, return null.
                    if ($this->_handleCompatibility($n, $plugin, $application)) {
                        $compatible = true;
                    }
                    break;
                case 'certification':
                    $release[$n->tagName][] = $n->getAttribute('type');
                    break;
                default:
                // Not erroring out here so that future
                // additions won't break old releases.
                }
        }

        if ($compatible && (!$plugin->getData('version') || version_compare($plugin->getData('version'), $release['version'], '<'))) {
            // This release is newer than the one found earlier, or
            // this is the first compatible release we've found.
            $plugin->setDate($release['date']);
            $plugin->setVersion($release['version']);
            $plugin->setReleaseMD5($release['md5']);
            $plugin->setReleaseDescription($release['description']);
            $plugin->setReleaseCertifications($release['certification']);
            $plugin->setReleasePackage($release['package']);
            return true;
        }
        return false;
    }

    /**
     * Handle a compatibility element, fishing out the most recent statement
     * of compatibility.
     *
     * @param GalleryPlugin $plugin
     * @param PKPApplication $application
     *
     * @return bool True iff a compatibility statement matched this app
     */
    public function _handleCompatibility($element, $plugin, $application)
    {
        // Check that the compatibility statement refers to this app
        if ($element->getAttribute('application') != $application->getName()) {
            return false;
        }

        for ($n = $element->firstChild; $n; $n = $n->nextSibling) {
            if (!($n instanceof DOMElement)) {
                continue;
            }
            switch ($n->tagName) {
                case 'version':
                    $installedVersion = $application->getCurrentVersion();
                    if ($installedVersion->isCompatible($n->nodeValue)) {
                        // Compatibility was determined.
                        return true;
                    }
                    break;
            }
        }

        // No applicable compatibility statement found.
        return false;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\plugins\PluginGalleryDAO', '\PluginGalleryDAO');
    define('PLUGIN_GALLERY_XML_URL', \PluginGalleryDAO::PLUGIN_GALLERY_XML_URL);
}
