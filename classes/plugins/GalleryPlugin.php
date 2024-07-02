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

class GalleryPlugin extends \PKP\core\DataObject
{
    public const PLUGIN_GALLERY_STATE_AVAILABLE = 0;
    public const PLUGIN_GALLERY_STATE_INCOMPATIBLE = 0;
    public const PLUGIN_GALLERY_STATE_UPGRADABLE = 1;
    public const PLUGIN_GALLERY_STATE_CURRENT = 2;
    public const PLUGIN_GALLERY_STATE_NEWER = 3;

    /**
     * Get the localized name of the plugin
     */
    public function getLocalizedName(?string $preferredLocale = null): ?string
    {
        return $this->getLocalizedData('name', $preferredLocale);
    }

    /**
     * Set the name of the plugin
     */
    public function setName(array|string $name, ?string $locale = null): void
    {
        $this->setData('name', $name, $locale);
    }

    /**
     * Get the name of the plugin
     */
    public function getName(?string $locale = null): null|string|array
    {
        return $this->getData('name', $locale);
    }

    /**
     * Get the homepage for this plugin
     */
    public function getHomepage(): string
    {
        return $this->getData('homepage');
    }

    /**
     * Set the homepage for this plugin
     */
    public function setHomepage(string $homepage): void
    {
        $this->setData('homepage', $homepage);
    }

    /**
     * Get the product (symbolic name) for this plugin
     *
     */
    public function getProduct(): string
    {
        return $this->getData('product');
    }

    /**
     * Set the product (symbolic name) for this plugin
     */
    public function setProduct(string $product): void
    {
        $this->setData('product', $product);
    }

    /**
     * Get the category for this plugin
     */
    public function getCategory(): string
    {
        return $this->getData('category');
    }

    /**
     * Set the category for this plugin
     */
    public function setCategory(string $category): void
    {
        $this->setData('category', $category);
    }

    /**
     * Get the newest compatible version of this plugin
     *
     * @param bool $pad True iff returned version numbers should be
     *  padded to 4 terms, e.g. 1.0.0.0 instead of just 1.0
     */
    public function getVersion(bool $pad = false): string
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
     */
    public function setVersion(string $version): void
    {
        $this->setData('version', $version);
    }

    /**
     * Get the release date of this plugin
     */
    public function getDate(): int
    {
        return $this->getData('date');
    }

    /**
     * Set the release date for this plugin
     */
    public function setDate(int $date): void
    {
        $this->setData('date', $date);
    }

    /**
     * Get the contact name for this plugin
     */
    public function getContactName(): string
    {
        return $this->getData('contactName');
    }

    /**
     * Set the contact name for this plugin
     */
    public function setContactName(string $contactName): void
    {
        $this->setData('contactName', $contactName);
    }

    /**
     * Get the contact institution name for this plugin
     */
    public function getContactInstitutionName(): string
    {
        return $this->getData('contactInstitutionName');
    }

    /**
     * Set the contact institution name for this plugin
     */
    public function setContactInstitutionName(string $contactInstitutionName): void
    {
        $this->setData('contactInstitutionName', $contactInstitutionName);
    }

    /**
     * Get the contact email for this plugin
     */
    public function getContactEmail(): string
    {
        return $this->getData('contactEmail');
    }

    /**
     * Set the contact email for this plugin
     */
    public function setContactEmail(string $contactEmail): void
    {
        $this->setData('contactEmail', $contactEmail);
    }

    /**
     * Get plugin summary.
     */
    public function getSummary(?string $locale = null): null|string|array
    {
        return $this->getData('summary', $locale);
    }

    /**
     * Set plugin summary.
     */
    public function setSummary(array|string $summary, ?string $locale = null): void
    {
        $this->setData('summary', $summary, $locale);
    }

    /**
     * Get plugin description.
     */
    public function getDescription(?string $locale = null): null|string|array
    {
        return $this->getData('description', $locale);
    }

    /**
     * Set plugin description.
     */
    public function setDescription(array|string $description, ?string $locale = null): void
    {
        $this->setData('description', $description, $locale);
    }

    /**
     * Get plugin installation instructions.
     */
    public function getInstallationInstructions(?string $locale = null): null|string|array
    {
        return $this->getData('installation', $locale);
    }

    /**
     * Set plugin installation instructions.
     */
    public function setInstallationInstructions(array|string $installation, ?string $locale = null): void
    {
        $this->setData('installation', $installation, $locale);
    }

    /**
     * Get release description.
     */
    public function getReleaseDescription(?string $locale = null): null|string|array
    {
        return $this->getData('releaseDescription', $locale);
    }

    /**
     * Set plugin release description.
     */
    public function setReleaseDescription(array|string $releaseDescription, ?string $locale = null): void
    {
        $this->setData('releaseDescription', $releaseDescription, $locale);
    }

    /**
     * Get release MD5 checksum.
     */
    public function getReleaseMD5(): string
    {
        return $this->getData('releaseMD5');
    }

    /**
     * Set plugin release MD5.
     */
    public function setReleaseMD5(string $releaseMD5): void
    {
        $this->setData('releaseMD5', $releaseMD5);
    }

    /**
     * Get the certifications for this plugin release
     */
    public function getReleaseCertifications(): array
    {
        return $this->getData('releaseCertifications');
    }

    /**
     * Set the certifications for this plugin release
     */
    public function setReleaseCertifications(array $certifications)
    {
        $this->setData('releaseCertifications', $certifications);
    }

    /**
     * Get the package URL for this plugin release
     */
    public function getReleasePackage(): string
    {
        return $this->getData('releasePackage');
    }

    /**
     * Set the package URL for this plugin release
     */
    public function setReleasePackage(string $releasePackage): void
    {
        $this->setData('releasePackage', $releasePackage);
    }

    /**
     * Get the localized summary of the plugin.
     */
    public function getLocalizedSummary(): null|string
    {
        return $this->getLocalizedData('summary');
    }

    /**
     * Get the localized installation instructions of the plugin.
     */
    public function getLocalizedInstallationInstructions(): null|string
    {
        return $this->getLocalizedData('installation');
    }

    /**
     * Get the localized description of the plugin.
     */
    public function getLocalizedDescription(): null|string
    {
        return $this->getLocalizedData('description');
    }

    /**
     * Get the localized release description of the plugin.
     */
    public function getLocalizedReleaseDescription(): null|string
    {
        return $this->getLocalizedData('releaseDescription');
    }

    /**
     * Determine the version of this plugin that is currently installed,
     * if any
     */
    public function getInstalledVersion(): ?Version
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
    public function getCurrentStatus(): int
    {
        $installedVersion = $this->getInstalledVersion();
        if ($this->getVersion() === null) {
            return self::PLUGIN_GALLERY_STATE_INCOMPATIBLE;
        }
        if (!$installedVersion) {
            return self::PLUGIN_GALLERY_STATE_AVAILABLE;
        }
        if ($installedVersion->compare($this->getVersion(true)) > 0) {
            return self::PLUGIN_GALLERY_STATE_NEWER;
        }
        if ($installedVersion->compare($this->getVersion(true)) < 0) {
            return self::PLUGIN_GALLERY_STATE_UPGRADABLE;
        }

        $targetPath = Core::getBaseDir() . '/plugins/' . $this->getCategory() . '/' . $this->getProduct();
        if (!is_dir($targetPath)) {
            return self::PLUGIN_GALLERY_STATE_UPGRADABLE;
        }

        return self::PLUGIN_GALLERY_STATE_CURRENT;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\plugins\GalleryPlugin', '\GalleryPlugin');
}
