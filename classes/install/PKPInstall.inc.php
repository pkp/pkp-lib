<?php

/**
 * @defgroup install
 */

/**
 * @file classes/install/PKPInstall.inc.php
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class Install
 * @ingroup install
 * @see Installer, InstallForm
 *
 * @brief Perform system installation.
 *
 * This script will:
 *  - Create the database (optionally), and install the database tables and initial data.
 *  - Update the config file with installation parameters.
 */


import('lib.pkp.classes.install.Installer');

class PKPInstall extends Installer {

	/**
	 * Constructor.
	 * @see install.form.InstallForm for the expected parameters
	 * @param $xmlDescriptor string descriptor path
	 * @param $params array installer parameters
	 * @param $isPlugin boolean true iff a plugin is being installed
	 */
	function PKPInstall($xmlDescriptor, $params, $isPlugin) {
		parent::Installer($xmlDescriptor, $params, $isPlugin);
	}

	/**
	 * Returns true iff this is an upgrade process.
	 */
	function isUpgrade() {
		return false;
	}

	/**
	 * Pre-installation.
	 * @return boolean
	 */
	function preInstall() {
		if(!isset($this->currentVersion)) {
			$this->currentVersion = Version::fromString('');
		}

		$this->locale = $this->getParam('locale');
		$this->installedLocales = $this->getParam('additionalLocales');
		if (!isset($this->installedLocales) || !is_array($this->installedLocales)) {
			$this->installedLocales = array();
		}
		if (!in_array($this->locale, $this->installedLocales) && AppLocale::isLocaleValid($this->locale)) {
			array_push($this->installedLocales, $this->locale);
		}

		// Connect to database
		$conn = new DBConnection(
			$this->getParam('databaseDriver'),
			$this->getParam('databaseHost'),
			$this->getParam('databaseUsername'),
			$this->getParam('databasePassword'),
			$this->getParam('createDatabase') ? null : $this->getParam('databaseName'),
			true,
			$this->getParam('connectionCharset') == '' ? false : $this->getParam('connectionCharset')
		);

		$this->dbconn =& $conn->getDBConn();

		if (!$conn->isConnected()) {
			$this->setError(INSTALLER_ERROR_DB, $this->dbconn->errorMsg());
			return false;
		}

		DBConnection::getInstance($conn);

		return parent::preInstall();
	}


	//
	// Installer actions
	//

	/**
	 * Get the names of the directories to create.
	 * @return array
	 */
	function getCreateDirectories() {
		return array('site');
	}

	/**
	 * Create required files directories
	 * FIXME No longer needed since FileManager will auto-create?
	 * @return boolean
	 */
	function createDirectories() {
		// Check if files directory exists and is writeable
		if (!(file_exists($this->getParam('filesDir')) &&  is_writeable($this->getParam('filesDir')))) {
			// Files upload directory unusable
			$this->setError(INSTALLER_ERROR_GENERAL, 'installer.installFilesDirError');
			return false;
		} else {
			// Create required subdirectories
			$dirsToCreate = $this->getCreateDirectories();
			import('lib.pkp.classes.file.FileManager');
			$fileManager = new FileManager();
			foreach ($dirsToCreate as $dirName) {
				$dirToCreate = $this->getParam('filesDir') . '/' . $dirName;
				if (!file_exists($dirToCreate)) {
					if (!$fileManager->mkdir($dirToCreate)) {
						$this->setError(INSTALLER_ERROR_GENERAL, 'installer.installFilesDirError');
						return false;
					}
				}
			}
		}

		// Check if public files directory exists and is writeable
		$publicFilesDir = Config::getVar('files', 'public_files_dir');
		if (!(file_exists($publicFilesDir) &&  is_writeable($publicFilesDir))) {
			// Public files upload directory unusable
			$this->setError(INSTALLER_ERROR_GENERAL, 'installer.publicFilesDirError');
			return false;
		} else {
			// Create required subdirectories
			$dirsToCreate = $this->getCreateDirectories();
			import('lib.pkp.classes.file.FileManager');
			$fileManager = new FileManager();
			foreach ($dirsToCreate as $dirName) {
				$dirToCreate = $publicFilesDir . '/' . $dirName;
				if (!file_exists($dirToCreate)) {
					if (!$fileManager->mkdir($dirToCreate)) {
						$this->setError(INSTALLER_ERROR_GENERAL, 'installer.publicFilesDirError');
						return false;
					}
				}
			}
		}

		return true;
	}

	/**
	 * Create a new database if required.
	 * @return boolean
	 */
	function createDatabase() {
		if (!$this->getParam('createDatabase')) {
			return true;
		}

		// Get database creation sql
		$dbdict =& NewDataDictionary($this->dbconn);

		if ($this->getParam('databaseCharset')) {
				$dbdict->SetCharSet($this->getParam('databaseCharset'));
		}

		list($sql) = $dbdict->CreateDatabase($this->getParam('databaseName'));
		unset($dbdict);

		if (!$this->executeSQL($sql)) {
			return false;
		}

		// Re-connect to the created database
		$this->dbconn->disconnect();

		$conn = new DBConnection(
			$this->getParam('databaseDriver'),
			$this->getParam('databaseHost'),
			$this->getParam('databaseUsername'),
			$this->getParam('databasePassword'),
			$this->getParam('databaseName'),
			true,
			$this->getParam('connectionCharset') == '' ? false : $this->getParam('connectionCharset')
		);

		DBConnection::getInstance($conn);

		$this->dbconn =& $conn->getDBConn();

		if (!$conn->isConnected()) {
			$this->setError(INSTALLER_ERROR_DB, $this->dbconn->errorMsg());
			return false;
		}

		return true;
	}

	/**
	 * Write the configuration file.
	 * @return boolean
	 */
	function createConfig() {
		return $this->updateConfig(
			array(
				'general' => array(
					'installed' => 'On',
					'base_url' => Request::getBaseUrl()
				),
				'database' => array(
					'driver' => $this->getParam('databaseDriver'),
					'host' => $this->getParam('databaseHost'),
					'username' => $this->getParam('databaseUsername'),
					'password' => $this->getParam('databasePassword'),
					'name' => $this->getParam('databaseName')
				),
				'i18n' => array(
					'locale' => $this->getParam('locale'),
					'client_charset' => $this->getParam('clientCharset'),
					'connection_charset' => $this->getParam('connectionCharset') == '' ? 'Off' : $this->getParam('connectionCharset'),
					'database_charset' => $this->getParam('databaseCharset') == '' ? 'Off' : $this->getParam('databaseCharset')
				),
				'files' => array(
					'files_dir' => $this->getParam('filesDir')
				),
				'security' => array(
					'encryption' => $this->getParam('encryption')
				),
				'oai' => array(
					'repository_id' => $this->getParam('oaiRepositoryId')
				)
			)
		);
	}
}

?>
