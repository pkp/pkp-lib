<?php

/**
 * @file classes/plugin/GalleryPlugin.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class GalleryPlugin
 *
 * @ingroup plugins
 *
 * @brief Class describing a plugin in the Plugin Gallery.
 */

namespace PKP\plugins;

use PKP\core\Core;
use PKP\db\DAORegistry;
use PKP\site\Version;
use PKP\site\VersionDAO;

define('PLUGIN_GALLERY_STATE_AVAILABLE', 0);
define('PLUGIN_GALLERY_STATE_INCOMPATIBLE', 0);
define('PLUGIN_GALLERY_STATE_UPGRADABLE', 1);
define('PLUGIN_GALLERY_STATE_CURRENT', 2);
define('PLUGIN_GALLERY_STATE_NEWER', 3);

class GalleryPlugin extends \PKP\core\DataObject
{
    /**
     * Get the localized name of the plugin
     *
     * @param string $preferredLocale
     *
     * @return string
     */
    public function getLocalizedName($preferredLocale = null)
    {
        return $this->getLocalizedData('name', $preferredLocale);
    }

    /**
     * Set the name of the plugin
     *
     * @param string $name
     * @param string $locale optional
     */
    public function setName($name, $locale = null)
    {
        $this->setData('name', $name, $locale);
    }

    /**
     * Get the name of the plugin
     *
     * @param string $locale optional
     *
     * @return string
     */
    public function getName($locale = null)
    {
        return $this->getData('name', $locale);
    }

    /**
     * Get the homepage for this plugin
     *
     * @return string
     */
    public function getHomepage()
    {
        return $this->getData('homepage');
    }

    /**
     * Set the homepage for this plugin
     *
     * @param string $homepage
     */
    public function setHomepage($homepage)
    {
        $this->setData('homepage', $homepage);
    }

    /**
     * Get the product (symbolic name) for this plugin
     *
     * @return string
     */
    public function getProduct()
    {
        return $this->getData('product');
    }

    /**
     * Set the product (symbolic name) for this plugin
     *
     * @param string $product
     */
    public function setProduct($product)
    {
        $this->setData('product', $product);
    }

    /**
     * Get the category for this plugin
     *
     * @return string
     */
    public function getCategory()
    {
        return $this->getData('category');
    }

    /**
     * Set the category for this plugin
     *
     * @param string $category
     */
    public function setCategory($category)
    {
        $this->setData('category', $category);
    }

    /**
     * Get the newest compatible version of this plugin
     *
     * @param bool $pad True iff returned version numbers should be
     *  padded to 4 terms, e.g. 1.0.0.0 instead of just 1.0
     *
     * @return string
     */
    public function getVersion($pad = false)
    {
        $version = $this->getData('version');
        if ($pad) {
            // Ensure there are 4 terms (3 separators)
            $separators = substr_count($version, '.');
            if ($separators < 3) {
                $version .= str_repeat('.0', 3 - $separators);
            }
        }
        return $version;
    }

    /**
     * Set the version for this plugin
     *
     * @param string $version
     */
    public function setVersion($version)
    {
        $this->setData('version', $version);
    }

    /**
     * Get the release date of this plugin
     *
     * @return int
     */
    public function getDate()
    {
        return $this->getData('date');
    }

    /**
     * Set the release date for this plugin
     *
     * @param int $date
     */
    public function setDate($date)
    {
        $this->setData('date', $date);
    }

    /**
     * Get the contact name for this plugin
     *
     * @return string
     */
    public function getContactName()
    {
        return $this->getData('contactName');
    }

    /**
     * Set the contact name for this plugin
     *
     * @param string $contactName
     */
    public function setContactName($contactName)
    {
        $this->setData('contactName', $contactName);
    }

    /**
     * Get the contact institution name for this plugin
     *
     * @return string
     */
    public function getContactInstitutionName()
    {
        return $this->getData('contactInstitutionName');
    }

    /**
     * Set the contact institution name for this plugin
     *
     * @param string $contactInstitutionName
     */
    public function setContactInstitutionName($contactInstitutionName)
    {
        $this->setData('contactInstitutionName', $contactInstitutionName);
    }

    /**
     * Get the contact email for this plugin
     *
     * @return string
     */
    public function getContactEmail()
    {
        return $this->getData('contactEmail');
    }

    /**
     * Set the contact email for this plugin
     *
     * @param string $contactEmail
     */
    public function setContactEmail($contactEmail)
    {
        $this->setData('contactEmail', $contactEmail);
    }

    /**
     * Get plugin summary.
     *
     * @param string $locale optional
     *
     * @return string
     */
    public function getSummary($locale = null)
    {
        return $this->getData('summary', $locale);
    }

    /**
     * Set plugin summary.
     *
     * @param string $summary
     * @param string $locale optional
     */
    public function setSummary($summary, $locale = null)
    {
        $this->setData('summary', $summary, $locale);
    }

    /**
     * Get plugin description.
     *
     * @param string $locale optional
     *
     * @return string
     */
    public function getDescription($locale = null)
    {
        return $this->getData('description', $locale);
    }

    /**
     * Set plugin description.
     *
     * @param string $description
     * @param string $locale optional
     */
    public function setDescription($description, $locale = null)
    {
        $this->setData('description', $description, $locale);
    }

    /**
     * Get plugin installation instructions.
     *
     * @param string $locale optional
     *
     * @return string
     */
    public function getInstallationInstructions($locale = null)
    {
        return $this->getData('installation', $locale);
    }

    /**
     * Set plugin installation instructions.
     *
     * @param string $installation
     * @param string $locale optional
     */
    public function setInstallationInstructions($installation, $locale = null)
    {
        $this->setData('installation', $installation, $locale);
    }

    /**
     * Get release description.
     *
     * @param string $locale optional
     *
     * @return string
     */
    public function getReleaseDescription($locale = null)
    {
        return $this->getData('releaseDescription', $locale);
    }

    /**
     * Set plugin release description.
     *
     * @param string $releaseDescription
     * @param string $locale optional
     */
    public function setReleaseDescription($releaseDescription, $locale = null)
    {
        $this->setData('releaseDescription', $releaseDescription, $locale);
    }

    /**
     * Get release MD5 checksum.
     *
     * @return string
     */
    public function getReleaseMD5()
    {
        return $this->getData('releaseMD5');
    }

    /**
     * Set plugin release MD5.
     *
     * @param string $releaseMD5
     */
    public function setReleaseMD5($releaseMD5)
    {
        $this->setData('releaseMD5', $releaseMD5);
    }

    /**
     * Get the certifications for this plugin release
     *
     * @return array
     */
    public function getReleaseCertifications()
    {
        return $this->getData('releaseCertifications');
    }

    /**
     * Set the certifications for this plugin release
     *
     * @param array $certifications
     */
    public function setReleaseCertifications($certifications)
    {
        $this->setData('releaseCertifications', $certifications);
    }

    /**
     * Get the package URL for this plugin release
     *
     * @return string
     */
    public function getReleasePackage()
    {
        return $this->getData('releasePackage');
    }

    /**
     * Set the package URL for this plugin release
     */
    public function setReleasePackage($releasePackage)
    {
        $this->setData('releasePackage', $releasePackage);
    }

    /**
     * Get the localized summary of the plugin.
     *
     * @return string
     */
    public function getLocalizedSummary()
    {
        return $this->getLocalizedData('summary');
    }

    /**
     * Get the localized installation instructions of the plugin.
     *
     * @return string
     */
    public function getLocalizedInstallationInstructions()
    {
        return $this->getLocalizedData('installation');
    }

    /**
     * Get the localized description of the plugin.
     *
     * @return string
     */
    public function getLocalizedDescription()
    {
        return $this->getLocalizedData('description');
    }

    /**
     * Get the localized release description of the plugin.
     *
     * @return string
     */
    public function getLocalizedReleaseDescription()
    {
        return $this->getLocalizedData('releaseDescription');
    }

    /**
     * Determine the version of this plugin that is currently installed,
     * if any
     *
     * @return Version|null
     */
    public function getInstalledVersion()
    {
        $versionDao = DAORegistry::getDAO('VersionDAO'); /** @var VersionDAO $versionDao */
        return $versionDao->getCurrentVersion('plugins.' . $this->getCategory(), $this->getProduct());
    }

    /**
     * Get the current state of the gallery plugin with respect to this
     * installation.
     *
     * @return int PLUGIN_GALLERY_STATE_...
     */
    public function getCurrentStatus()
    {
        $installedVersion = $this->getInstalledVersion();
        if ($this->getVersion() === null) {
            return PLUGIN_GALLERY_STATE_INCOMPATIBLE;
        }
        if (!$installedVersion) {
            return PLUGIN_GALLERY_STATE_AVAILABLE;
        }
        if ($installedVersion->compare($this->getVersion(true)) > 0) {
            return PLUGIN_GALLERY_STATE_NEWER;
        }
        if ($installedVersion->compare($this->getVersion(true)) < 0) {
            return PLUGIN_GALLERY_STATE_UPGRADABLE;
        }

        $targetPath = Core::getBaseDir() . '/plugins/' . $this->getCategory() . '/' . $this->getProduct();
        if (!is_dir($targetPath)) {
            return PLUGIN_GALLERY_STATE_UPGRADABLE;
        }

        return PLUGIN_GALLERY_STATE_CURRENT;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\plugins\GalleryPlugin', '\GalleryPlugin');
}
