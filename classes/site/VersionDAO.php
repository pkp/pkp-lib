<?php

/**
 * @file classes/site/VersionDAO.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class VersionDAO
 *
 * @see Version
 *
 * @brief Operations for retrieving and modifying Version objects.
 */

namespace PKP\site;

use APP\core\Application;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use PKP\core\Core;
use PKP\plugins\Hook;

class VersionDAO extends \PKP\db\DAO
{
    /**
     * Retrieve the current version.
     */
    public function getCurrentVersion(?string $productType = null, ?string $product = null): ?Version
    {
        if (!$productType || !$product) {
            $application = Application::get();
            $productType = 'core';
            $product = $application->getName();
        }

        $result = $this->retrieve(
            'SELECT * FROM versions WHERE current = 1 AND product_type = ? AND product = ?',
            [$productType, $product]
        );
        $row = (array) $result->current();
        return $row ? $this->_returnVersionFromRow($row) : null;
    }

    /**
     * Retrieve the complete version history, ordered by date (most recent first).
     *
     * @return Version[] Versions
     */
    public function getVersionHistory(?string $productType = null, ?string $product = null): array
    {
        $versions = [];

        if (!$productType || !$product) {
            $application = Application::get();
            $productType = 'core';
            $product = $application->getName();
        }

        $result = $this->retrieve(
            'SELECT * FROM versions WHERE product_type = ? AND product = ? ORDER BY date_installed DESC',
            [$productType, $product]
        );

        foreach ($result as $row) {
            $versions[] = $this->_returnVersionFromRow((array) $row);
        }
        return $versions;
    }

    /**
     * Internal function to return a Version object from a row.
     *
     * @hook VersionDAO::_returnVersionFromRow [[&$version, &$row]]
     */
    public function _returnVersionFromRow($row): Version
    {
        $version = new Version(
            $row['major'],
            $row['minor'],
            $row['revision'],
            $row['build'],
            $this->datetimeFromDB($row['date_installed']),
            $row['current'],
            ($row['product_type'] ?? null),
            ($row['product'] ?? null),
            ($row['product_class_name'] ?? ''),
            ($row['lazy_load'] ?? 0),
            ($row['sitewide'] ?? 0)
        );

        Hook::call('VersionDAO::_returnVersionFromRow', [&$version, &$row]);

        return $version;
    }

    /**
     * Insert a new version.
     */
    public function insertVersion(Version $version, bool $isPlugin = false): int
    {
        $isNewVersion = true;

        if ($version->getCurrent()) {
            // Find out whether the last installed version is the same as the
            // one to be inserted.
            $versionHistory = $this->getVersionHistory($version->getProductType(), $version->getProduct());

            $oldVersion = array_shift($versionHistory);
            if ($oldVersion) {
                if ($version->compare($oldVersion) == 0) {
                    // The old and the new current versions are the same so we need
                    // to update the existing version entry.
                    $isNewVersion = false;
                } elseif ($version->compare($oldVersion) == 1) {
                    // Version to insert is newer than the existing version entry.
                    // We reset existing entry.
                    $this->update('UPDATE versions SET current = 0 WHERE current = 1 AND product = ?', [$version->getProduct()]);
                } else {
                    // We do not support downgrades.
                    throw new \Exception('You are trying to downgrade the product "' . $version->getProduct() . '" from version [' . $oldVersion->getVersionString(false) . '] to version [' . $version->getVersionString(false) . ']. Downgrades are not supported.');
                }
            }
        }

        if ($isNewVersion) {
            // We only change the install date when we insert new
            // version entries.
            if ($version->getDateInstalled() == null) {
                $version->setDateInstalled(Core::getCurrentDate());
            }

            // Insert new version entry
            return $this->update(
                sprintf(
                    'INSERT INTO versions
                    (major, minor, revision, build, date_installed, current, product_type, product, product_class_name, lazy_load, sitewide)
                    VALUES
                    (?, ?, ?, ?, %s, ?, ?, ?, ?, ?, ?)',
                    $this->datetimeToDB($version->getDateInstalled())
                ),
                [
                    (int) $version->getMajor(),
                    (int) $version->getMinor(),
                    (int) $version->getRevision(),
                    (int) $version->getBuild(),
                    (int) $version->getCurrent(),
                    $version->getProductType(),
                    $version->getProduct(),
                    $version->getProductClassName(),
                    ($version->getLazyLoad() ? 1 : 0),
                    ($version->getSitewide() ? 1 : 0)
                ]
            );
        } else {
            // Update existing version entry
            return $this->update(
                'UPDATE versions SET current = ?, product_class_name = ?, lazy_load = ?, sitewide = ?
                    WHERE product_type = ? AND product = ? AND major = ? AND minor = ? AND revision = ? AND build = ?',
                [
                    (int) $version->getCurrent(),
                    $version->getProductClassName(),
                    ($version->getLazyLoad() ? 1 : 0),
                    ($version->getSitewide() ? 1 : 0),
                    $version->getProductType(),
                    $version->getProduct(),
                    (int) $version->getMajor(),
                    (int) $version->getMinor(),
                    (int) $version->getRevision(),
                    (int) $version->getBuild()
                ]
            );
        }
    }

    /**
     * Retrieve all currently enabled products within the
     * given context as a two dimensional array with the
     * first key representing the product type, the second
     * key the product name and the value the product version.
     */
    public function getCurrentProducts(?int $contextId): array
    {
        $versions = DB::table('versions', 'v')
            ->leftJoin(
                'plugin_settings AS ps',
                fn (JoinClause $j) => $j->on('ps.plugin_name', '=', DB::raw('LOWER(v.product_class_name)'))
                    ->where('ps.setting_name', '=', 'enabled')
                    ->when(
                        $contextId !== Application::SITE_CONTEXT_ID_ALL,
                        fn (Builder $q) => $q->where(fn (Builder $q) => $q->whereRaw('COALESCE(context_id, 0) = ?', [(int) $contextId])->orWhere('v.sitewide', '=', 1))
                    )
            )
            ->where('v.current', '=', 1)
            ->where(fn (Builder $q) => $q->where('ps.setting_value', '=', 1)->orWhere('v.lazy_load', '!=', 1))
            ->get();

        $products = [];
        foreach ($versions as $version) {
            $products[$version->product_type][$version->product] = $this->_returnVersionFromRow((array) $version);
        }
        return $products;
    }

    /**
     * Disable a product by setting its 'current' column to 0
     *
     * @param string $productType
     * @param string $product
     */
    public function disableVersion($productType, $product): void
    {
        $this->update(
            'UPDATE versions SET current = 0 WHERE current = 1 AND product_type = ? AND product = ?',
            [$productType, $product]
        );
    }

    /**
     * Get installation date of the given version or the first version used after that
     *
     * @param int $version Version number, without '.' as separator, i.e. in the form major*1000+minor*100+revision*10+build
     */
    public function getInstallationDate(int $version): string
    {
        $product = Application::get()->getName();
        $dateInstalledArray = DB::select(
            "SELECT date_installed
                FROM versions
                WHERE major*1000+minor*100+revision*10+build IN
                    (SELECT MIN(major*1000+minor*100+revision*10+build)
                    FROM versions vt
                    WHERE vt.product_type = 'core' AND vt.product = ? AND vt.major*1000+vt.minor*100+vt.revision*10+vt.build >= ?)
                AND product_type = 'core' AND product = ?
        ",
            [$product, $version, $product]
        );
        return current($dateInstalledArray)->date_installed;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\site\VersionDAO', '\VersionDAO');
}
