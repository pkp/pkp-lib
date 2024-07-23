<?php

/**
 * @file classes/site/Version.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Version
 *
 * @ingroup site
 *
 * @see VersionDAO
 *
 * @brief Describes system version history.
 */

namespace PKP\site;

use APP\core\Application;
use Composer\Semver\Semver;
use PKP\core\Core;

class Version extends \PKP\core\DataObject
{
    /**
     * Constructor.
     */
    public function __construct(
        $major,
        $minor,
        $revision,
        $build,
        $dateInstalled,
        $current,
        $productType,
        $product,
        $productClassName,
        $lazyLoad,
        $sitewide
    ) {
        parent::__construct();

        // Initialize object
        $this->setMajor($major);
        $this->setMinor($minor);
        $this->setRevision($revision);
        $this->setBuild($build);
        $this->setDateInstalled($dateInstalled);
        $this->setCurrent($current);
        $this->setProductType($productType);
        $this->setProduct($product);
        $this->setProductClassName($productClassName);
        $this->setLazyLoad($lazyLoad);
        $this->setSitewide($sitewide);
    }

    /**
     * Compare this version with another version.
     * Returns:
     *     < 0 if this version is lower
     *     0 if they are equal
     *     > 0 if this version is higher
     *
     * @param string|Version $version the version to compare against
     */
    public function compare(string|Version $version): int
    {
        if ($version instanceof Version) {
            return $this->compare($version->getVersionString());
        }
        return version_compare($this->getVersionString(), $version);
    }

    /**
     * Static method to return a new version from a version string of the form "W.X.Y.Z".
     */
    public static function fromString(string $versionString, ?string $productType = null, ?string $product = null, ?string $productClass = '', bool $lazyLoad = false, bool $sitewide = true): Version
    {
        $versionArray = explode('.', $versionString);

        if (!$product && !$productType) {
            $application = Application::get();
            $product = $application->getName();
            $productType = 'core';
        }

        $version = new Version(
            (isset($versionArray[0]) ? (int) $versionArray[0] : 0),
            (isset($versionArray[1]) ? (int) $versionArray[1] : 0),
            (isset($versionArray[2]) ? (int) $versionArray[2] : 0),
            (isset($versionArray[3]) ? (int) $versionArray[3] : 0),
            Core::getCurrentDate(),
            1,
            $productType,
            $product,
            $productClass,
            $lazyLoad,
            $sitewide
        );

        return $version;
    }

    //
    // Get/set methods
    //

    /**
     * Get major version.
     */
    public function getMajor(): int
    {
        return $this->getData('major');
    }

    /**
     * Set major version.
     */
    public function setMajor(int $major): void
    {
        $this->setData('major', $major);
    }

    /**
     * Get minor version.
     */
    public function getMinor(): int
    {
        return $this->getData('minor');
    }

    /**
     * Set minor version.
     */
    public function setMinor(int $minor): void
    {
        $this->setData('minor', $minor);
    }

    /**
     * Get revision version.
     */
    public function getRevision(): int
    {
        return $this->getData('revision');
    }

    /**
     * Set revision version.
     */
    public function setRevision(int $revision): void
    {
        $this->setData('revision', $revision);
    }

    /**
     * Get build version.
     */
    public function getBuild(): int
    {
        return $this->getData('build');
    }

    /**
     * Set build version.
     */
    public function setBuild(int $build): void
    {
        $this->setData('build', $build);
    }

    /**
     * Get date installed.
     */
    public function getDateInstalled(): string
    {
        return $this->getData('dateInstalled');
    }

    /**
     * Set date installed.
     */
    public function setDateInstalled(string $dateInstalled): void
    {
        $this->setData('dateInstalled', $dateInstalled);
    }

    /**
     * Check if current version.
     */
    public function getCurrent(): bool
    {
        return $this->getData('current');
    }

    /**
     * Set if current version.
     */
    public function setCurrent(bool $current): void
    {
        $this->setData('current', $current);
    }

    /**
     * Get product type.
     */
    public function getProductType(): string
    {
        return $this->getData('productType');
    }

    /**
     * Set product type.
     */
    public function setProductType(string $productType): void
    {
        $this->setData('productType', $productType);
    }

    /**
     * Get product name.
     */
    public function getProduct(): string
    {
        return $this->getData('product');
    }

    /**
     * Set product name.
     */
    public function setProduct(string $product): void
    {
        $this->setData('product', $product);
    }

    /**
     * Get the product's class name
     */
    public function getProductClassName(): string
    {
        return $this->getData('productClassName');
    }

    /**
     * Set the product's class name
     */
    public function setProductClassName(string $productClassName): void
    {
        $this->setData('productClassName', $productClassName);
    }

    /**
     * Get the lazy load flag for this product
     */
    public function getLazyLoad(): bool
    {
        return $this->getData('lazyLoad');
    }

    /**
     * Set the lazy load flag for this product
     */
    public function setLazyLoad(bool $lazyLoad): void
    {
        $this->setData('lazyLoad', $lazyLoad);
    }

    /**
     * Get the sitewide flag for this product
     */
    public function getSitewide(): bool
    {
        return $this->getData('sitewide');
    }

    /**
     * Set the sitewide flag for this product
     */
    public function setSitewide(bool $sitewide): void
    {
        $this->setData('sitewide', $sitewide);
    }

    /**
     * Return complete version string.
     *
     * @param bool True (default) iff a numeric (comparable) version is to be returned.
     *
     */
    public function getVersionString(bool $numeric = true): string
    {
        $numericVersion = sprintf('%d.%d.%d.%d', $this->getMajor(), $this->getMinor(), $this->getRevision(), $this->getBuild());
        if (!$numeric && $this->getProduct() == 'omp' && preg_match('/^0\.9\.9\./', $numericVersion)) {
            return ('1.0 Beta');
        }
        if (!$numeric && $this->getProduct() == 'ojs2' && preg_match('/^2\.9\.0\./', $numericVersion)) {
            return ('3.0 Alpha 1');
        }
        if (!$numeric && $this->getProduct() == 'ojs2' && preg_match('/^2\.9\.9\.0/', $numericVersion)) {
            return ('3.0 Beta 1');
        }
        if (!$numeric && $this->getProduct() == 'ops' && preg_match('/^3\.2\.0\.0/', $numericVersion)) {
            return ('3.2.0 Beta');
        }

        return $numericVersion;
    }

    /**
     * Checks if the Version is compatible with the given string of constraints for the version,
     * formatted per composer/semver specifications;
     * c.f. https://getcomposer.org/doc/articles/versions.md#writing-version-constraints
     */
    public function isCompatible(string $constraints): bool
    {
        $semver = new semver();
        $version = $this->getVersionString();

        return $semver->satisfies($version, $constraints);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\site\Version', '\Version');
}
