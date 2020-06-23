<?php

/**
 * @file classes/plugins/PluginHelper.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PluginHelper
 * @ingroup classes_plugins
 *
 * @brief Helper class implementing plugin administration functions.
 */

import('lib.pkp.classes.site.Version');
import('lib.pkp.classes.site.VersionCheck');
import('lib.pkp.classes.file.FileManager');
import('classes.install.Install');
import('classes.install.Upgrade');

define('PLUGIN_ACTION_UPLOAD', 'upload');
define('PLUGIN_ACTION_UPGRADE', 'upgrade');

define('PLUGIN_VERSION_FILE', 'version.xml');
define('PLUGIN_INSTALL_FILE', 'install.xml');
define('PLUGIN_UPGRADE_FILE', 'upgrade.xml');

class PluginHelper {
	/**
	 * Extract and validate a plugin (prior to installation)
	 * @param $filePath string Full path to plugin archive
	 * @param $originalFileName string Original filename of plugin archive
	 * @return string Extracted plugin path
	 */
	public function extractPlugin($filePath, $originalFileName) {
		$fileManager = new FileManager();
		// tar archive basename (less potential version number) must
		// equal plugin directory name and plugin files must be in a
		// directory named after the plug-in (potentially with version)
		$matches = array();
		PKPString::regexp_match_get('/^[a-zA-Z0-9]+/', basename($originalFileName, '.tar.gz'), $matches);
		$pluginShortName = array_pop($matches);
		if (!$pluginShortName) {
			throw new Exception(__('manager.plugins.invalidPluginArchive'));
		}

		// Create random dirname to avoid symlink attacks.
		$pluginExtractDir = dirname($filePath) . DIRECTORY_SEPARATOR . $pluginShortName . substr(md5(mt_rand()), 0, 10);
		mkdir($pluginExtractDir);

		// Test whether the tar binary is available for the export to work
		$tarBinary = Config::getVar('cli', 'tar');
		if (empty($tarBinary) || !file_exists($tarBinary)) {
			rmdir($pluginExtractDir);
			throw new Exception(__('manager.plugins.tarCommandNotFound'));
		}

		$output = '';
		$returnCode = 0;
		if (in_array('exec', explode(',', ini_get('disable_functions')))) throw new Exception('The "exec" PHP function has been disabled on your server. Contact your system adminstrator to enable it.');
		exec($tarBinary.' -xzf ' . escapeshellarg($filePath) . ' -C ' . escapeshellarg($pluginExtractDir), $output, $returnCode);
		if ($returnCode) {
			$fileManager->rmtree($pluginExtractDir);
			throw new Exception(__('form.dropzone.dictInvalidFileType'));
		}

		// Look for a directory named after the plug-in's short
		// (alphanumeric) name within the extracted archive.
		if (is_dir($tryDir = $pluginExtractDir . '/' . $pluginShortName)) {
			return $tryDir; // Success
		}

		// Failing that, look for a directory named after the
		// archive. (Typically also contains the version number
		// e.g. with github generated release archives.)
		PKPString::regexp_match_get('/^[a-zA-Z0-9.-]+/', basename($originalFileName, '.tar.gz'), $matches);
		if (is_dir($tryDir = $pluginExtractDir . '/' . array_pop($matches))) {
			// We found a directory named after the archive
			// within the extracted archive. (Typically also
			// contains the version number, e.g. github
			// generated release archives.)
			return $tryDir;
		}

		// Could not match the plugin archive's contents against our expectations; error out.
		$fileManager->rmtree($pluginExtractDir);
		throw new Exception(__('manager.plugins.invalidPluginArchive'));
	}

	/**
	 * Installs an extracted plugin
	 * @param $path string path to plugin Directory
	 * @return Version Version of installed plugin on success
	 */
	public function installPlugin($path) {
		$versionFile = $path . '/' . PLUGIN_VERSION_FILE;

		$pluginVersion = VersionCheck::getValidPluginVersionInfo($versionFile);

		$versionDao = DAORegistry::getDAO('VersionDAO'); /** @var $versionDao VersionDAO */
		$installedPlugin = $versionDao->getCurrentVersion($pluginVersion->getProductType(), $pluginVersion->getProduct(), true);
		$pluginDest = Core::getBaseDir() . '/' . strtr($pluginVersion->getProductType(), '.', '/') . '/' . $pluginVersion->getProduct();

		if ($installedPlugin && file_exists($pluginDest)) {
			if ($this->_checkIfNewer($pluginVersion->getProductType(), $pluginVersion->getProduct(), $pluginVersion)) {
				throw new Exception(__('manager.plugins.pleaseUpgrade'));
			} else {
				throw new Exception(__('manager.plugins.installedVersionOlder'));
			}
		}

		// Copy the plug-in from the temporary folder to the target folder.
		$fileManager = new FileManager();
		if (!$fileManager->copyDir($path, $pluginDest)) throw new Exception('Could not copy plugin to desination!');
		if (!$fileManager->rmtree($path)) throw new Exception('Could not remove temporary plugin path!');

		// Upgrade the database with the new plug-in.
		$installFile = $pluginDest . '/' . PLUGIN_INSTALL_FILE;
		if(!is_file($installFile)) $installFile = Core::getBaseDir() . '/' . PKP_LIB_PATH . '/xml/defaultPluginInstall.xml';
		assert(is_file($installFile));
		$siteDao = DAORegistry::getDAO('SiteDAO'); /** @var $siteDao SiteDAO */
		$site = $siteDao->getSite();
		$params = $this->_getConnectionParams();
		$params['locale'] = $site->getPrimaryLocale();
		$params['additionalLocales'] = $site->getSupportedLocales();
		$installer = new Install($params, $installFile, true);
		$installer->setCurrentVersion($pluginVersion);
		if (!$installer->execute()) {
			// Roll back the copy
			if (is_dir($pluginDest)) $fileManager->rmtree($pluginDest);
			throw new Exception(__('manager.plugins.installFailed', array('errorString' => $installer->getErrorString())));
		}

		$versionDao->insertVersion($pluginVersion, true);
		return $pluginVersion;
	}

	/**
	 * Checks to see if local version of plugin is newer than installed version
	 * @param $productType string Product type of plugin
	 * @param $productName string Product name of plugin
	 * @param $newVersion Version Version object of plugin to check against database
	 * @return boolean
	 */
	protected function _checkIfNewer($productType, $productName, $newVersion) {
		$versionDao = DAORegistry::getDAO('VersionDAO'); /** @var $versionDao VersionDAO */
		$installedPlugin = $versionDao->getCurrentVersion($productType, $productName, true);
		if ($installedPlugin && $installedPlugin->compare($newVersion) > 0) return true;
		return false;
	}

	/**
	 * Load database connection parameters into an array (needed for upgrade).
	 * @return array
	 */
	protected function _getConnectionParams() {
		return array(
			'clientCharset' => Config::getVar('i18n', 'client_charset'),
			'connectionCharset' => Config::getVar('i18n', 'connection_charset'),
			'databaseDriver' => Config::getVar('database', 'driver'),
			'databaseHost' => Config::getVar('database', 'host'),
			'databaseUsername' => Config::getVar('database', 'username'),
			'databasePassword' => Config::getVar('database', 'password'),
			'databaseName' => Config::getVar('database', 'name')
		);
	}

	/**
	 * Upgrade a plugin to a newer version from the user's filesystem
	 * @param $category string
	 * @param $plugin string
	 * @param $path string path to plugin Directory
	 * @return Version
	 */
	public function upgradePlugin($category, $plugin, $path) {
		$fileManager = new FileManager();

		$versionFile = $path . '/' . PLUGIN_VERSION_FILE;
		$pluginVersion = VersionCheck::getValidPluginVersionInfo($versionFile);

		// Check whether the uploaded plug-in fits the original plug-in.
		if ('plugins.'.$category != $pluginVersion->getProductType()) {
			throw new Exception(__('manager.plugins.wrongCategory'));
		}

		if ($plugin != $pluginVersion->getProduct()) {
			throw new Exception(__('manager.plugins.wrongName'));
		}

		$versionDao = DAORegistry::getDAO('VersionDAO'); /** @var $versionDao VersionDAO */
		$installedPlugin = $versionDao->getCurrentVersion($pluginVersion->getProductType(), $pluginVersion->getProduct(), true);
		if(!$installedPlugin) {
			throw new Exception(__('manager.plugins.pleaseInstall'));
		}

		if ($this->_checkIfNewer($pluginVersion->getProductType(), $pluginVersion->getProduct(), $pluginVersion)) {
			throw new Exception(__('manager.plugins.installedVersionNewer'));
		}

		$pluginDest = Core::getBaseDir() . '/plugins/' . $category . '/' . $plugin;

		// Delete existing files.
		if (is_dir($pluginDest)) $fileManager->rmtree($pluginDest);

		// Check whether deleting has worked.
		if(is_dir($pluginDest)) {
			throw new Exception(__('manager.plugins.deleteError', array('pluginName' => $pluginVersion->getProduct())));
		}

		// Copy the plug-in from the temporary folder to the target folder.
		if (!$fileManager->copyDir($path, $pluginDest)) throw new Exception('Could not copy plugin to desination!');
		if (!$fileManager->rmtree($path)) throw new Exception('Could not remove temporary plugin path!');

		$upgradeFile = $pluginDest . '/' . PLUGIN_UPGRADE_FILE;
		if($fileManager->fileExists($upgradeFile)) {
			$siteDao = DAORegistry::getDAO('SiteDAO'); /** @var $siteDao SiteDAO */
			$site = $siteDao->getSite();
			$params = $this->_getConnectionParams();
			$params['locale'] = $site->getPrimaryLocale();
			$params['additionalLocales'] = $site->getSupportedLocales();
			$installer = new Upgrade($params, $upgradeFile, true);

			if (!$installer->execute()) throw new Exception(__('manager.plugins.upgradeFailed', array('errorString' => $installer->getErrorString())));
		}

		$installedPlugin->setCurrent(0);
		$pluginVersion->setCurrent(1);
		$versionDao->insertVersion($pluginVersion, true);
		return $pluginVersion;
	}
}

