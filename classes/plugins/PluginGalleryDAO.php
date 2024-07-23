<?php

/**
 * @file classes/plugins/PluginGalleryDAO.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PluginGalleryDAO
 *
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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use PKP\controllers\grid\plugins\PluginGalleryGridHandler;
use PKP\core\PKPApplication;
use PKP\db\DAORegistry;
use PKP\site\VersionDAO;
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
     * @return GalleryPlugin[]
     */
    public function getNewestCompatible(PKPApplication $application, ?string $category = null, ?string $search = null): array
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
                ($search == '' || Str::position(Str::lower(serialize($plugin)), Str::lower($search)) !== false)
            ) {
                $plugins[$index] = $plugin;
            }
        }

        return $plugins;
    }

    /**
     * Get the external Plugin XML document
     */
    protected function getExternalDocument(): ?string
    {
        $application = Application::get();
        $client = $application->getHttpClient();
        /** @var VersionDAO */
        $versionDao = DAORegistry::getDAO('VersionDAO');
        $currentVersion = $versionDao->getCurrentVersion();
        try {
            $response = $client->request(
                'GET',
                static::PLUGIN_GALLERY_XML_URL,
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
     */
    protected function getCachedDocument(): ?string
    {
        return Cache::remember('pluginGallery', 60 * 60 * 24, fn () => $this->getExternalDocument());

    }

    /**
     * Get the DOM document for the plugin gallery.
     */
    private function _getDocument(): DOMDocument
    {
        $doc = new DOMDocument('1.0', 'utf-8');
        $doc->loadXML($this->getCachedDocument());

        return $doc;
    }

    /**
     * Construct a new data object.
     */
    public function newDataObject(): GalleryPlugin
    {
        return new GalleryPlugin();
    }

    /**
     * Build a GalleryPlugin from a DOM element, using the newest compatible
     * release with the supplied Application.
     */
    protected function _compatibleFromElement(DOMElement $element, PKPApplication $application): ?GalleryPlugin
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
     */
    public function _handleMaintainer(DOMElement $element, GalleryPlugin $plugin): void
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
     */
    public function _handleRelease(DOMElement $element, GalleryPlugin $plugin, PKPApplication $application): bool
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
            $plugin->setReleaseCertifications($release['certification'] ?? []);
            $plugin->setReleasePackage($release['package']);
            return true;
        }
        return false;
    }

    /**
     * Handle a compatibility element, fishing out the most recent statement
     * of compatibility.
     *
     * @return bool True iff a compatibility statement matched this app
     */
    public function _handleCompatibility(DOMElement $element, GalleryPlugin $plugin, PKPApplication $application): bool
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
    define('PLUGIN_GALLERY_XML_URL', PluginGalleryDAO::PLUGIN_GALLERY_XML_URL);
}
