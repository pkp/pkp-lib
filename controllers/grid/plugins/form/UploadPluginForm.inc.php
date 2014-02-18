<?php

/**
 * @file controllers/grid/plugins/form/UploadPluginForm.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class UploadPluginForm
 * @ingroup controllers_grid_plugins_form
 *
 * @brief Form to upload a plugin file.
 */


define('VERSION_FILE', '/version.xml');
define('INSTALL_FILE', '/install.xml');
define('UPGRADE_FILE', '/upgrade.xml');

// Import the base Form.
import('lib.pkp.classes.form.Form');

import('lib.pkp.classes.site.Version');
import('lib.pkp.classes.site.VersionCheck');
import('lib.pkp.classes.file.FileManager');
import('classes.install.Install');
import('classes.install.Upgrade');

class UploadPluginForm extends Form {

	/** @var String */
	var $_function;


	/**
	 * Constructor.
	 */
	function UploadPluginForm($function) {
		parent::Form('controllers/grid/plugins/form/uploadPluginForm.tpl');

		$this->_function = $function;

		$this->addCheck(new FormValidator($this, 'temporaryFileId', 'required', 'Please select a file first'));
	}

	//
	// Implement template methods from Form.
	//
	/**
	 * @copydoc Form::readInputData()
	 */
	function readInputData() {
		$this->readUserVars(array('temporaryFileId'));
	}

	/**
	 * @copydoc Form::fetch()
	 */
	function fetch($request) {
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('function', $this->_function);

		return parent::fetch($request);
	}

	/**
	 * @copydoc Form::execute()
	 */
	function execute($request) {
		parent::execute($request);

		// Retrieve the temporary file.
		$user = $request->getUser();
		$temporaryFileId = $this->getData('temporaryFileId');
		$temporaryFileDao = DAORegistry::getDAO('TemporaryFileDAO');
		$temporaryFile = $temporaryFileDao->getTemporaryFile($temporaryFileId, $user->getId());

		// tar archive basename (less potential version number) must equal plugin directory name
		// and plugin files must be in a directory named after the plug-in.
		$matches = array();
		String::regexp_match_get('/^[a-zA-Z0-9]+/', basename($temporaryFile->getOriginalFileName(), '.tar.gz'), $matches);
		$pluginName = array_pop($matches);

		// Create random dirname to avoid symlink attacks.
		$pluginDir = dirname($temporaryFile->getFilePath()) . DIRECTORY_SEPARATOR . $pluginName . substr(md5(mt_rand()), 0, 10);
		mkdir($pluginDir);

		$errorMsg = null;

		// Test whether the tar binary is available for the export to work
		$tarBinary = Config::getVar('cli', 'tar');
		if (!empty($tarBinary) && file_exists($tarBinary)) {
			exec($tarBinary.' -xzf ' . escapeshellarg($temporaryFile->getFilePath()) . ' -C ' . escapeshellarg($pluginDir));
		} else {
			$errorMsg = __('manager.plugins.tarCommandNotFound');
		}

		if (empty($errorMsg)) {
			// We should now find a directory named after the
			// plug-in within the extracted archive.
			$pluginDir .= DIRECTORY_SEPARATOR . $pluginName;
			if (is_dir($pluginDir)) {
				$result = null;
				if ($this->_function == 'install') {
					$result = $this->_installPlugin($request, $pluginDir);
				} else if ($this->_function == 'upgrade') {
					$result = $this->_upgradePlugin($request, $pluginDir);
				}

				if(!is_null($result) && $result !== true) {
					$errorMsg = $result;
				}
			} else {
				$errorMsg = __('manager.plugins.invalidPluginArchive');
			}
		}

		if(!is_null($errorMsg) ) {
			$notificationMgr = new NotificationManager();
			$notificationMgr->createTrivialNotification($user->getId(), NOTIFICATION_TYPE_ERROR, array('contents' => $errorMsg));
			return false;
		}

		return true;
	}


	//
	// Private helper methods.
	//
	/**
	 * Installs the uploaded plugin
	 * @param $request PKPRequest
	 * @param $path string path to plugin Directory
	 * @return boolean
	 */
	function _installPlugin($request, $path) {
		$versionFile = $path . VERSION_FILE;

		$checkResult =& VersionCheck::getValidPluginVersionInfo($versionFile, true);
		if (is_string($checkResult)) return __($checkResult);
		if (is_a($checkResult, 'Version')) {
			$pluginVersion = $checkResult;
		} else {
			assert(false);
		}

		$versionDao = DAORegistry::getDAO('VersionDAO'); /* @var $versionDao VersionDAO */
		$installedPlugin = $versionDao->getCurrentVersion($pluginVersion->getProductType(), $pluginVersion->getProduct(), true);

		if(!$installedPlugin) {
			$pluginLibDest = Core::getBaseDir() . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'pkp' . DIRECTORY_SEPARATOR . strtr($pluginVersion->getProductType(), '.', DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $pluginVersion->getProduct();
			$pluginDest = Core::getBaseDir() . DIRECTORY_SEPARATOR . strtr($pluginVersion->getProductType(), '.', DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $pluginVersion->getProduct();

			// Copy the plug-in from the temporary folder to the
			// target folder.
			// Start with the library part (if any).
			$libPath = $path . DIRECTORY_SEPARATOR . 'lib';
			$fileManager = new FileManager();
			if (is_dir($libPath)) {
				if(!$fileManager->copyDir($libPath, $pluginLibDest)) {
					return __('manager.plugins.copyError');
				}
				// Remove the library part of the temporary folder.
				$fileManager->rmtree($libPath);
			}

			// Continue with the application-specific part (mandatory).
			if(!$fileManager->copyDir($path, $pluginDest)) {
				return __('manager.plugins.copyError');
			}

			// Remove the temporary folder.
			$fileManager->rmtree(dirname($path));

			// Upgrade the database with the new plug-in.
			$installFile = $pluginDest . INSTALL_FILE;
			if(!is_file($installFile)) $installFile = Core::getBaseDir() . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'pkp' . DIRECTORY_SEPARATOR . 'xml' . DIRECTORY_SEPARATOR . 'defaultPluginInstall.xml';
			assert(is_file($installFile));
			$params = $this->_setConnectionParams();
			$installer = new Install($params, $installFile, true);
			$installer->setCurrentVersion($pluginVersion);
			if (!$installer->execute()) {
				// Roll back the copy
				if (is_dir($pluginLibDest)) $fileManager->rmtree($pluginLibDest);
				if (is_dir($pluginDest)) $fileManager->rmtree($pluginDest);
				return __('manager.plugins.installFailed', array('errorString' => $installer->getErrorString()));
			}

			$notificationMgr = new NotificationManager();
			$user = $request->getUser();
			$notificationMgr->createTrivialNotification(
				$user->getId(),
				NOTIFICATION_TYPE_SUCCESS,
				array('contents' =>
					__('manager.plugins.installSuccessful', array('versionNumber' => $pluginVersion->getVersionString(false)))));

			$versionDao->insertVersion($pluginVersion, true);
			return true;
		} else {
			if ($this->_checkIfNewer($pluginVersion->getProductType(), $pluginVersion->getProduct(), $pluginVersion)) {
				return __('manager.plugins.pleaseUpgrade');
			} else {
				return __('manager.plugins.installedVersionOlder');
			}
		}
	}

	/**
	 * Checks to see if local version of plugin is newer than installed version
	 * @param $productType string Product type of plugin
	 * @param $productName string Product name of plugin
	 * @param $newVersion Version Version object of plugin to check against database
	 * @return boolean
	 */
	function _checkIfNewer($productType, $productName, $newVersion) {
		$versionDao = DAORegistry::getDAO('VersionDAO');
		$installedPlugin = $versionDao->getCurrentVersion($productType, $productName, true);

		if (!$installedPlugin) return false;
		if ($installedPlugin->compare($newVersion) > 0) return true;
		else return false;
	}

	/**
	 * Load database connection parameters into an array (needed for upgrade).
	 * @return array
	 */
	function _setConnectionParams() {
		return array(
				'clientCharset' => Config::getVar('i18n', 'client_charset'),
				'connectionCharset' => Config::getVar('i18n', 'connection_charset'),
				'databaseCharset' => Config::getVar('i18n', 'database_charset'),
				'databaseDriver' => Config::getVar('database', 'driver'),
				'databaseHost' => Config::getVar('database', 'host'),
				'databaseUsername' => Config::getVar('database', 'username'),
				'databasePassword' => Config::getVar('database', 'password'),
				'databaseName' => Config::getVar('database', 'name')
		);
	}

	/**
	 * Upgrade a plugin to a newer version from the user's filesystem
	 * @param $request PKPRequest
	 * @param $path string path to plugin Directory
	 * @param $templateMgr reference to template manager
	 * @param $category string
	 * @param $plugin string
	 * @return boolean
	 */
	function _upgradePlugin($request, $path, &$templateMgr) {
		$category = $request->getUserVar('category');
		$plugin = $request->getUserVar('plugin');

		$versionFile = $path . VERSION_FILE;
		$templateMgr->assign('error', true);

		$pluginVersion =& VersionCheck::getValidPluginVersionInfo($versionFile, $templateMgr);
		if (is_null($pluginVersion)) return false;
		assert(is_a($pluginVersion, 'Version'));

		// Check whether the uploaded plug-in fits the original plug-in.
		if ('plugins.'.$category != $pluginVersion->getProductType()) {
			return __('manager.plugins.wrongCategory');
		}

		if ($plugin != $pluginVersion->getProduct()) {
			return __('manager.plugins.wrongName');
		}

		$versionDao = DAORegistry::getDAO('VersionDAO');
		$installedPlugin = $versionDao->getCurrentVersion($pluginVersion->getProductType(), $pluginVersion->getProduct(), true);
		if(!$installedPlugin) {
			return __('manager.plugins.pleaseInstall');
		}

		if ($this->_checkIfNewer($pluginVersion->getProductType(), $pluginVersion->getProduct(), $pluginVersion)) {
			return __('manager.plugins.installedVersionNewer');
		} else {
			$pluginDest = Core::getBaseDir() . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . $category . DIRECTORY_SEPARATOR . $plugin;
			$pluginLibDest = Core::getBaseDir() . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'pkp' . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . $category . DIRECTORY_SEPARATOR . $plugin;

			// Delete existing files.
			$fileManager = new FileManager();
			if (is_dir($pluginDest)) $fileManager->rmtree($pluginDest);
			if (is_dir($pluginLibDest)) $fileManager->rmtree($pluginLibDest);

			// Check whether deleting has worked.
			if(is_dir($pluginDest) || is_dir($pluginLibDest)) {
				return __('message', 'manager.plugins.deleteError');
			}

			// Copy the plug-in from the temporary folder to the
			// target folder.
			// Start with the library part (if any).
			$libPath = $path . DIRECTORY_SEPARATOR . 'lib';
			if (is_dir($libPath)) {
				if(!$fileManager->copyDir($libPath, $pluginLibDest)) {
					return __('manager.plugins.copyError');
				}
				// Remove the library part of the temporary folder.
				$fileManager->rmtree($libPath);
			}

			// Continue with the application-specific part (mandatory).
			if(!$fileManager->copyDir($path, $pluginDest)) {
				return __('manager.plugins.copyError');
			}

			// Remove the temporary folder.
			$fileManager->rmtree(dirname($path));

			$upgradeFile = $pluginDest . UPGRADE_FILE;
			if($fileManager->fileExists($upgradeFile)) {
				$params = $this->_setConnectionParams();
				$installer = new Upgrade($params, $upgradeFile, true);

				if (!$installer->execute()) {
					return __('manager.plugins.upgradeFailed', array('errorString' => $installer->getErrorString()));
				}
			}

			$installedPlugin->setCurrent(0);
			$pluginVersion->setCurrent(1);
			$versionDao->insertVersion($pluginVersion, true);

			$notificationMgr = new NotificationManager();
			$notificationMgr->createTrivialNotification(
				$user->getId(), NOTIFICATION_TYPE_SUCCESS, array('contents' => __('manager.plugins.upgradeSuccessful', array('versionString' => $pluginVersion->getVersionString(false)))));

			return true;
		}
	}
}

?>
