<?php

/**
 * @file classes/plugins/PluginHelper.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PluginHelper
 * @ingroup classes_plugins
 *
 * @brief Helper class implementing plugin administration functions.
 */

namespace PKP\plugins;

use APP\install\Install;
use APP\install\Upgrade;
use DirectoryIterator;
use Exception;
use Illuminate\Support\Arr;
use PharData;
use PKP\config\Config;
use PKP\core\Core;
use PKP\db\DAORegistry;
use PKP\file\FileManager;
use PKP\site\SiteDAO;
use PKP\site\Version;
use PKP\site\VersionCheck;
use PKP\site\VersionDAO;
use Throwable;

class PluginHelper
{
    public const PLUGIN_ACTION_UPLOAD = 'upload';
    public const PLUGIN_ACTION_UPGRADE = 'upgrade';

    public const PLUGIN_VERSION_FILE = 'version.xml';
    public const PLUGIN_INSTALL_FILE = 'install.xml';
    public const PLUGIN_UPGRADE_FILE = 'upgrade.xml';

    /**
     * Extract and validate a plugin (prior to installation)
     *
     * @param string $filePath Full path to plugin archive
     * @param string $originalFileName Original filename of plugin archive
     *
     * @return string Directory where the plugin was extracted
     */
    private function extractPlugin(string $filePath, string $originalFileName): string
    {
        $fileManager = new FileManager();
        $extension = $this->sanitizeFilename($fileManager->parseFileExtension($originalFileName));
        $baseName = $this->sanitizeFilename(basename($originalFileName, ".{$extension}")) ?: 'plugin';

        // If the extension doesn't match the original one, copy (we don't know the original file) the file to another location to avoid issues with the PharData class
        $filePathWithExtension = null;
        if ($fileManager->parseFileExtension($filePath) !== $extension) {
            $filePathWithExtension = ($fileManager->getTemporaryFile($baseName, ".{$extension}"))->getPathname();
            $fileManager->copyFile($filePath, $filePathWithExtension) || throw new Exception('Failed to copy plugin file');
        }
        $extractPath = null;
        try {
            // Create a random directory to avoid symlink attacks.
            $extractPath = rtrim(sys_get_temp_dir(), '\\/') . "/{$baseName}" . substr(md5(mt_rand()), 0, 10) . '/';
            $fileManager->mkdir($extractPath) || throw new Exception("Could not create directory {$extractPath}");

            // Extract files
            (new PharData($filePathWithExtension ?? $filePath))->extractTo($extractPath, null, true);

            // Ensure there's a file named "version.xml" at the main directory or at the direct sub-directories
            foreach(new DirectoryIterator($extractPath) as $current) {
                if ($current->isDir() && $current->getBasename() !== '..' && is_file(($path = "{$current->getPathname()}/") . static::PLUGIN_VERSION_FILE)) {
                    return $path;
                }
            }
            throw new Exception(__('manager.plugins.invalidPluginArchive'));
        } catch (Throwable $e) {
            // Cleanup the extracted folder on failure and rethrow
            if ($extractPath) {
                $fileManager->rmtree($extractPath);
            }
            throw $e;
        } finally {
            // Cleanup the temporary archive file in case it was created
            if ($filePathWithExtension) {
                unlink($filePathWithExtension);
            }
        }
    }

    /**
     * Installs an extracted plugin
     *
     * @param string $path path to plugin archive
     * @param string $originalFileName Original filename of plugin archive
     *
     * @return Version Version of installed plugin on success
     */
    public function installPlugin(string $path, string $originalFileName): Version
    {
        $fileManager = new FileManager();
        $sourcePath = $this->extractPlugin($path, $originalFileName);
        try {
            $versionFile = $sourcePath . static::PLUGIN_VERSION_FILE;
            $pluginVersion = VersionCheck::getValidPluginVersionInfo($versionFile);
            /** @var VersionDAO */
            $versionDao = DAORegistry::getDAO('VersionDAO');
            $installedPlugin = $versionDao->getCurrentVersion($pluginVersion->getProductType(), $pluginVersion->getProduct(), true);
            $baseDir = Core::getBaseDir() . '/';
            $destinyPath = $baseDir . strtr($pluginVersion->getProductType(), '.', '/') . "/{$pluginVersion->getProduct()}";

            if ($installedPlugin && is_dir($destinyPath)) {
                throw new Exception(
                    $installedPlugin->compare($pluginVersion) < 0
                        ? __('manager.plugins.pleaseUpgrade')
                        : __('manager.plugins.installedVersionNewest')
                );
            }

            // Copy the plug-in from the temporary folder to the target folder.
            $fileManager->copyDir($sourcePath, $destinyPath) || throw new Exception('Failed to copy plugin to destination folder');

            try {
                // Upgrade the database with the new plug-in.
                $installFile = Arr::first(
                    ["{$destinyPath}/" . static::PLUGIN_INSTALL_FILE, $baseDir . PKP_LIB_PATH . '/xml/defaultPluginInstall.xml'],
                    fn (string $path) => is_file($path)
                )
                    ?? throw new Exception('Missing installation file');

                $siteDao = DAORegistry::getDAO('SiteDAO'); /** @var SiteDAO $siteDao */
                $site = $siteDao->getSite();
                $params = $this->_getConnectionParams();
                $params['locale'] = $site->getPrimaryLocale();
                $params['additionalLocales'] = $site->getSupportedLocales();
                $installer = new Install($params, $installFile, true);
                $installer->setCurrentVersion($pluginVersion);
                $installer->execute() || throw new Exception(__('manager.plugins.installFailed', ['errorString' => $installer->getErrorString()]));
                $versionDao->insertVersion($pluginVersion, true);
                return $pluginVersion;
            } catch (Throwable $e) {
                // Delete the plugin files on failure
                $fileManager->rmtree($destinyPath);
                throw $e;
            }
        } finally {
            // Delete the extracted plugin files
            $fileManager->rmtree($sourcePath);
        }
    }

    /**
     * Load database connection parameters into an array (needed for upgrade).
     */
    protected function _getConnectionParams(): array
    {
        return [
            'connectionCharset' => Config::getVar('i18n', 'connection_charset'),
            'databaseDriver' => Config::getVar('database', 'driver'),
            'databaseHost' => Config::getVar('database', 'host'),
            'databasePort' => Config::getVar('database', 'port'),
            'unixSocket' => Config::getVar('database', 'unix_socket'),
            'databaseUsername' => Config::getVar('database', 'username'),
            'databasePassword' => Config::getVar('database', 'password'),
            'databaseName' => Config::getVar('database', 'name')
        ];
    }

    /**
     * Upgrade a plugin to a newer version from the user's filesystem
     *
     * @param string $category
     * @param string $plugin
     * @param string $path path to plugin archive
     * @param string $originalFileName Original filename of plugin archive
     *
     * @return Version
     */
    public function upgradePlugin(string $category, string $plugin, string $path, string $originalFileName): Version
    {
        $fileManager = new FileManager();
        $sourcePath = $this->extractPlugin($path, $originalFileName);
        try {
            $versionFile = $sourcePath . static::PLUGIN_VERSION_FILE;
            $pluginVersion = VersionCheck::getValidPluginVersionInfo($versionFile);

            // Check whether the uploaded plug-in fits the original plug-in.
            if ("plugins.{$category}" !== $pluginVersion->getProductType()) {
                throw new Exception(__('manager.plugins.wrongCategory'));
            }

            if ($plugin !== $pluginVersion->getProduct()) {
                throw new Exception(__('manager.plugins.wrongName'));
            }

            $versionDao = DAORegistry::getDAO('VersionDAO'); /** @var VersionDAO $versionDao */
            $installedPlugin = $versionDao->getCurrentVersion($pluginVersion->getProductType(), $pluginVersion->getProduct(), true);
            if (!$installedPlugin) {
                throw new Exception(__('manager.plugins.pleaseInstall'));
            }

            if ($installedPlugin->compare($pluginVersion) >= 0) {
                throw new Exception(__('manager.plugins.installedVersionNewer'));
            }

            $destinyPath = Core::getBaseDir() . "/plugins/{$category}/{$plugin}";

            // Delete existing files.
            $fileManager->rmtree($destinyPath);

            // Check whether deleting has worked.
            if (is_dir($destinyPath)) {
                throw new Exception(__('manager.plugins.deleteError', ['pluginName' => $pluginVersion->getProduct()]));
            }

            // Copy the plug-in from the temporary folder to the target folder.
            $fileManager->copyDir($sourcePath, $destinyPath) || throw new Exception('Could not copy plugin to destination!');

            try {
                $upgradeFile = "{$destinyPath}/" . static::PLUGIN_UPGRADE_FILE;
                if ($fileManager->fileExists($upgradeFile)) {
                    /** @var SiteDAO */
                    $siteDao = DAORegistry::getDAO('SiteDAO');
                    $site = $siteDao->getSite();
                    $params = $this->_getConnectionParams();
                    $params['locale'] = $site->getPrimaryLocale();
                    $params['additionalLocales'] = $site->getSupportedLocales();
                    $installer = new Upgrade($params, $upgradeFile, true);
                    // Run the upgrade/migration
                    $installer->execute() || throw new Exception(__('manager.plugins.upgradeFailed', ['errorString' => $installer->getErrorString()]));
                }

                // Add the new version to the database
                $pluginVersion->setCurrent(1);
                $versionDao->insertVersion($pluginVersion, true);
                return $pluginVersion;
            } catch (Throwable $e) {
                // Delete the plugin files on failure
                $fileManager->rmtree($destinyPath);
                throw $e;
            }
        } finally {
            // Discard the temporary plugin files
            $fileManager->rmtree($sourcePath);
        }
    }

    /**
     * Drops risky characters from a filename
     */
    private function sanitizeFilename(string $filename): string
    {
        return preg_replace('/[^\w.-]/', '', $filename);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\plugins\PluginHelper', '\PluginHelper');
    foreach ([
        'PLUGIN_ACTION_UPLOAD',
        'PLUGIN_ACTION_UPGRADE',
        'PLUGIN_VERSION_FILE',
        'PLUGIN_INSTALL_FILE',
        'PLUGIN_UPGRADE_FILE',
    ] as $constantName) {
        define($constantName, constant('\PluginHelper::' . $constantName));
    }
}
