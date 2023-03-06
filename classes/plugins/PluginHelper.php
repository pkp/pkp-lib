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
use Exception;
use FilesystemIterator;
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
use SplFileObject;
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
        // A permissive extension might be returned (e.g. 1.2.3.tar.gz)
        $getExtension = fn (string $path) => explode('.', basename($path), 2)[1] ?? '';
        // Drops risky characters
        $sanitize = fn (string $path) => preg_replace('/[^\w.-]/', '', $path);

        $extension = $sanitize($getExtension($originalFileName));
        $baseName = $sanitize(basename($originalFileName, ".{$extension}")) ?: 'plugin';
        $fileManager = new FileManager();

        // If the extension doesn't match, copy the file to another location to avoid issues with the PharData class
        $filePathWithExtension = null;
        if ($getExtension($filePath) !== $extension) {
            $filePathWithExtension = ($this->getTemporaryFile($baseName, ".{$extension}"))->getPathname();
            if (!$fileManager->copyFile($filePath, $filePathWithExtension)) {
                throw new Exception('Failed to copy file');
            }
        }
        $extractPath = null;
        try {
            // Create a random directory to avoid symlink attacks.
            $extractPath = rtrim(sys_get_temp_dir(), '\\/') . "/{$baseName}" . substr(md5(mt_rand()), 0, 10) . '/';
            if (!$fileManager->mkdir($extractPath)) {
                throw new Exception("Could not create directory {$extractPath}");
            }

            $tarball = new PharData($filePathWithExtension ?? $filePath);
            $tarball->extractTo($extractPath, null, true);

            // Look for the plugin's version.xml file
            if (is_file($extractPath . static::PLUGIN_VERSION_FILE)) {
                return $extractPath;
            }

            foreach (new FilesystemIterator($extractPath) as $item) {
                if ($item->isDir() && is_file("{$item}/" . static::PLUGIN_VERSION_FILE)) {
                    return "{$item}/";
                }
            }

            // Could not match the plugin archive's contents against our expectations
            throw new Exception(__('manager.plugins.invalidPluginArchive'));
        } catch (Throwable $e) {
            // Cleanup the extracted folder on failure
            if ($extractPath) {
                $fileManager->rmtree($extractPath);
            }
            throw $e;
        } finally {
            // Cleanup the temporary packed plugin file if it exists
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
            $versionFile = $sourcePath . self::PLUGIN_VERSION_FILE;
            $pluginVersion = VersionCheck::getValidPluginVersionInfo($versionFile);
            $versionDao = DAORegistry::getDAO('VersionDAO'); /** @var VersionDAO $versionDao */
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
            if (!$fileManager->copyDir($sourcePath, $destinyPath)) {
                throw new Exception('Could not copy plugin to destination!');
            }
            try {
                // Upgrade the database with the new plug-in.
                $installFile = Arr::first(
                    ["{$destinyPath}/" . self::PLUGIN_INSTALL_FILE, $baseDir . PKP_LIB_PATH . '/xml/defaultPluginInstall.xml'],
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
                if (!$installer->execute()) {
                    throw new Exception(__('manager.plugins.installFailed', ['errorString' => $installer->getErrorString()]));
                }
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
            $versionFile = $sourcePath . self::PLUGIN_VERSION_FILE;
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

            if ($installedPlugin->compare($pluginVersion) > 0) {
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
            if (!$fileManager->copyDir($sourcePath, $destinyPath)) {
                throw new Exception('Could not copy plugin to destination!');
            }

            try {
                $upgradeFile = "{$destinyPath}/" . self::PLUGIN_UPGRADE_FILE;
                if ($fileManager->fileExists($upgradeFile)) {
                    $siteDao = DAORegistry::getDAO('SiteDAO'); /** @var SiteDAO $siteDao */
                    $site = $siteDao->getSite();
                    $params = $this->_getConnectionParams();
                    $params['locale'] = $site->getPrimaryLocale();
                    $params['additionalLocales'] = $site->getSupportedLocales();
                    $installer = new Upgrade($params, $upgradeFile, true);

                    if (!$installer->execute()) {
                        throw new Exception(__('manager.plugins.upgradeFailed', ['errorString' => $installer->getErrorString()]));
                    }
                }

                $pluginVersion->setCurrent(1);
                $versionDao->insertVersion($pluginVersion, true);
                return $pluginVersion;
            } catch (Throwable $e) {
                // Delete the plugin files on failure
                $fileManager->rmtree($destinyPath);
                throw $e;
            }
        } finally {
            $fileManager->rmtree($sourcePath);
        }
    }

    /**
     * Attempts to create a locked and writable temporary file
     * The prefix/suffix will receive a minor sanitization
     */
    public static function getTemporaryFile(string $prefix = '', string $suffix = ''): SplFileObject
    {
        $sanitize = fn (string $path) => preg_replace('/[^\w.-]/', '', $path);
        $basePath = rtrim(sys_get_temp_dir(), '\\/') . "/";
        for ($attempts = 10; $attempts--; ) {
            try {
                $file = new SplFileObject($basePath . $sanitize($prefix) . substr(md5(mt_rand()), 0, 10) . $sanitize($suffix), 'x+');
                $file->flock(LOCK_EX);
                return $file;
            } catch (Throwable $e) {
                error_log($e);
            }
        }
        throw new Exception('Failed to create temporary file');
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
