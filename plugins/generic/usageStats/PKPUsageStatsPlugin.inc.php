<?php

/**
 * @file plugins/generic/usageStats/PKPUsageStatsPlugin.inc.php
 *
 * Copyright (c) 2013-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPUsageStatsPlugin
 * @ingroup plugins_generic_usageStats
 *
 * @brief Provide usage statistics to data objects.
 */

import('lib.pkp.classes.plugins.GenericPlugin');

class PKPUsageStatsPlugin extends GenericPlugin {

	/** @var $_currentUsageEvent array */
	var $_currentUsageEvent;

	/** @var $_dataPrivacyOn boolean */
	var $_dataPrivacyOn;

	/** @var $_optedOut boolean */
	var $_optedOut;

	/** @var $_saltpath string */
	var $_saltpath;

	/**
	 * Constructor.
	 */
	function PKPUsageStatsPlugin() {
		parent::GenericPlugin();

		// The upgrade and install processes will need access
		// to constants defined in that report plugin.
		import('plugins.generic.usageStats.UsageStatsReportPlugin');
	}


	//
	// Public methods.
	//
	/**
	 * Get the report plugin object that implements
	 * the metric type details.
	 */
	function getReportPlugin() {
		$this->import('UsageStatsReportPlugin');
		return new UsageStatsReportPlugin();
	}


	//
	// Implement methods from PKPPlugin.
	//
	/**
	 * @see LazyLoadPlugin::register()
	 */
	function register($category, $path) {
		$success = parent::register($category, $path);

		HookRegistry::register('AcronPlugin::parseCronTab', array($this, 'callbackParseCronTab'));

		if ($this->getEnabled() && $success) {
			// Register callbacks.
			HookRegistry::register('PluginRegistry::loadCategory', array($this, 'callbackLoadCategory'));
			HookRegistry::register('LoadHandler', array($this, 'callbackLoadHandler'));

			// If the plugin will provide the access logs,
			// register to the usage event hook provider.
			if ($this->getSetting(CONTEXT_ID_NONE, 'createLogFiles')) {
				HookRegistry::register('UsageEventPlugin::getUsageEvent', array(&$this, 'logUsageEvent'));
			}

			$this->_dataPrivacyOn = $this->getSetting(CONTEXT_ID_NONE, 'dataPrivacyOption');
			$this->_saltpath = $this->getSetting(CONTEXT_ID_NONE, 'saltFilepath');
			// Check config for backward compatibility.
			if (!$this->_saltpath) $this->_saltpath = Config::getVar('usageStats', 'salt_filepath');
			$application = Application::getApplication();
			$request = $application->getRequest();
			$this->_optedOut = $request->getCookieVar('usageStats-opt-out');
			if ($this->_optedOut) {
				// Renew the Opt-Out cookie if present.
				$request->setCookieVar('usageStats-opt-out', true, time() + 60*60*24*365);
			}
		}

		return $success;
	}

	/**
	 * Get the path to the salt file.
	 * @return string
	 */
	function getSaltpath() {
		return $this->_saltpath;
	}

	/**
	 * @see PKPPlugin::getDisplayName()
	 */
	function getDisplayName() {
		return __('plugins.generic.usageStats.displayName');
	}

	/**
	 * @see PKPPlugin::getDescription()
	 */
	function getDescription() {
		return __('plugins.generic.usageStats.description');
	}

	/**
	 * @see PKPPlugin::isSitePlugin()
	 */
	function isSitePlugin() {
		return true;
	}

	/**
	 * @see PKPPlugin::getInstallSitePluginSettingsFile()
	 */
	function getInstallSitePluginSettingsFile() {
		return PKP_LIB_PATH . DIRECTORY_SEPARATOR . $this->getPluginPath() . DIRECTORY_SEPARATOR . 'settings.xml';
	}

	/**
	 * @see PKPPlugin::getInstallSchemaFile()
	 */
	function getInstallSchemaFile() {
		return PKP_LIB_PATH . DIRECTORY_SEPARATOR . $this->getPluginPath() . DIRECTORY_SEPARATOR . 'schema.xml';
	}

	/**
	 * @see Plugin::getTemplatePath($inCore)
	 */
	function getTemplatePath($inCore = false) {
		return parent::getTemplatePath($inCore) . 'templates' .  DIRECTORY_SEPARATOR;
	}

	/**
	 * @see PKPPlugin::manage()
	 */
	function manage($args, $request) {
		$this->import('UsageStatsSettingsForm');
		switch($request->getUserVar('verb')) {
			case 'settings':
				$settingsForm = new UsageStatsSettingsForm($this);
				$settingsForm->initData();
				return new JSONMessage(true, $settingsForm->fetch($request));
			case 'save':
				$settingsForm = new UsageStatsSettingsForm($this);
				$settingsForm->readInputData();
				if ($settingsForm->validate()) {
					$settingsForm->execute();
					$notificationManager = new NotificationManager();
					$notificationManager->createTrivialNotification(
						$request->getUser()->getId(),
						NOTIFICATION_TYPE_SUCCESS,
						array('contents' => __('plugins.generic.usageStats.settings.saved'))
					);
					return new JSONMessage(true);
				}
				return new JSONMessage(true, $settingsForm->fetch($request));
		}
		return parent::manage($args, $request);
	}


	//
	// Implement template methods from GenericPlugin.
	//
	/**
	 * @see Plugin::getActions()
	 */
	function getActions($request, $verb) {
		$router = $request->getRouter();
		import('lib.pkp.classes.linkAction.request.AjaxModal');
		return array_merge(
			$this->getEnabled()?array(
				new LinkAction(
					'settings',
					new AjaxModal(
						$router->url($request, null, null, 'manage', null, array('verb' => 'settings', 'plugin' => $this->getName(), 'category' => 'generic')),
						$this->getDisplayName()
					),
					__('manager.plugins.settings'),
					null
				),
			):array(),
			parent::getActions($request, $verb)
		);
	}


	//
	// Hook implementations.
	//
	/**
	 * @see PluginRegistry::loadCategory()
	 */
	function callbackLoadCategory($hookName, $args) {
		// Instantiate report plugin.
		$plugin = null;
		$category = $args[0];
		if ($category == 'reports') {
			$plugin = $this->getReportPlugin();
		}
		if ($category == 'blocks' && $this->_dataPrivacyOn) {
			$this->import('UsageStatsOptoutBlockPlugin');
			$plugin = new UsageStatsOptoutBlockPlugin($this->getName());
		}

		// Register report plugin (by reference).
		if ($plugin) {
			$seq = $plugin->getSeq();
			$plugins =& $args[1];
			if (!isset($plugins[$seq])) $plugins[$seq] = array();
			$plugins[$seq][$this->getPluginPath()] = $plugin;
		}

		return false;
	}

	/**
 	 * @see PKPPageRouter::route()
	 */
	function callbackLoadHandler($hookName, $args) {
		// Check the page.
		$page = $args[0];
		if ($page !== 'usageStats') return;
		// Check the operation.
		$availableOps = array('privacyInformation');
		$op = $args[1];
		if (!in_array($op, $availableOps)) return;
		// The handler had been requested.
		define('HANDLER_CLASS', 'UsageStatsHandler');
		define('USAGESTATS_PLUGIN_NAME', $this->getName());
		$handlerFile =& $args[2];
		$handlerFile = $this->getPluginPath() . '/' . 'UsageStatsHandler.inc.php';
	}

	/**
	 * @see AcronPlugin::parseCronTab()
	 */
	function callbackParseCronTab($hookName, $args) {
		if ($this->getEnabled() || !Config::getVar('general', 'installed')) {
			$taskFilesPath =& $args[0]; // Reference needed.
			$taskFilesPath[] = PKP_LIB_PATH . DIRECTORY_SEPARATOR . $this->getPluginPath() . DIRECTORY_SEPARATOR . 'scheduledTasksAutoStage.xml';
		}

		return false;
	}

	/**
	 * Validate that the path of the salt file exists and is writable.
	 * @param $saltpath string
	 * @return boolean
	 */
	function validateSaltpath($saltpath) {
		if (!file_exists($saltpath)) {
			touch($saltpath);
		}
		if (is_writable($saltpath)) {
			return true;
		}
		return false;
	}

	/**
	 * Log the usage event into a file.
	 * @param $hookName string
	 * @param $args array
	 * @return boolean
	 */
	function logUsageEvent($hookName, $args) {
		$hookName = $args[0];
		$usageEvent = $args[1];

		// Check the statistics opt-out.
		if ($this->_optedOut) return false;

		if ($hookName == 'FileManager::downloadFileFinished' && !$usageEvent && $this->_currentUsageEvent) {
			// File download is finished, try to log the current usage event.
			$downloadSuccess = $args[2];
			if ($downloadSuccess && !connection_aborted()) {
				$this->_currentUsageEvent['downloadSuccess'] = true;
				$usageEvent = $this->_currentUsageEvent;
			}
		}

		if ($usageEvent && !$usageEvent['downloadSuccess']) {
			// Don't log until we get the download finished hook call.
			$this->_currentUsageEvent = $usageEvent;
			return false;
		}

		if ($usageEvent) {
			$this->_writeUsageEventInLogFile($usageEvent);
		}

		return false;
	}

	/**
	 * Get the geolocation tool to process geo localization
	 * data.
	 * @return mixed GeoLocationTool object or null
	 */
	function &getGeoLocationTool() {
		/** Geo location tool wrapper class. If changing the geo location tool
		* is required, change the code inside this class, keeping the public
		* interface. */
		$this->import('GeoLocationTool');

		$null = null;
		$tool = new GeoLocationTool();
		if ($tool->isPresent()) {
			return $tool;
		} else {
			return $null;
		}
	}

	/**
	 * Get the plugin's files path.
	 * @return string
	 */
	function getFilesPath() {
		import('lib.pkp.classes.file.PrivateFileManager');
		$fileMgr = new PrivateFileManager();

		return realpath($fileMgr->getBasePath()) . DIRECTORY_SEPARATOR . 'usageStats';
	}

	/**
	 * Get the plugin's usage event logs path.
	 * @return string
	 */
	function getUsageEventLogsPath() {
		return $this->getFilesPath() . DIRECTORY_SEPARATOR . 'usageEventLogs';
	}

	/**
	 * Get current day usage event log name.
	 * @return string
	 */
	function getUsageEventCurrentDayLogName() {
		return 'usage_events_' . date("Ymd") . '.log';
	}


	//
	// Private helper methods.
	//
	/**
	 * @param $usageEvent array
	 */
	function _writeUsageEventInLogFile($usageEvent) {
		$salt = null;
		if ($this->_dataPrivacyOn) {
			// Salt management.
			$saltFilename = $this->getSaltpath();
			if (!$this->validateSaltpath($saltFilename)) return false;
			$currentDate = date("Ymd");
			$saltFilenameLastModified = date("Ymd", filemtime($saltFilename));
			$file = fopen($saltFilename, 'r');
			$salt = trim(fread($file,filesize($saltFilename)));
			fclose($file);
			if (empty($salt) || ($currentDate != $saltFilenameLastModified)) {
				if(function_exists('mcrypt_create_iv')) {
					$newSalt = bin2hex(mcrypt_create_iv(16, MCRYPT_DEV_URANDOM|MCRYPT_RAND));
				} elseif (function_exists('openssl_random_pseudo_bytes')){
					$newSalt = bin2hex(openssl_random_pseudo_bytes(16, $cstrong));
				} elseif (file_exists('/dev/urandom')){
					$newSalt = bin2hex(file_get_contents('/dev/urandom', false, null, 0, 16));
				} else {
					$newSalt = mt_rand();
				}
				$file = fopen($saltFilename,'wb');
				if (flock($file, LOCK_EX)) {
					fwrite($file, $newSalt);
					flock($file, LOCK_UN);
				} else {
					assert(false);
				}
				fclose($file);
				$salt = $newSalt;
			}
		}

		// Manage the IP address (evtually hash it)
		if ($this->_dataPrivacyOn) {
			if (!isset($salt)) return false;
			// Hash the IP
			$hashedIp = $this->_hashIp($usageEvent['ip'], $salt);
			// Never store unhashed IPs!
			if ($hashedIp === false) return false;
			$desiredParams = array($hashedIp);
		} else {
			$desiredParams = array($usageEvent['ip']);
		}

		if (isset($usageEvent['classification'])) {
			$desiredParams[] = $usageEvent['classification'];
		} else {
			$desiredParams[] = '-';
		}

		if (!$this->_dataPrivacyOn && isset($usageEvent['user'])) {
			$desiredParams[] = $usageEvent['user']->getId();
		} else {
			$desiredParams[] = '-';
		}

		$desiredParams = array_merge($desiredParams,
		array('"' . $usageEvent['time'] . '"', $usageEvent['canonicalUrl'],
						'200', // The usage event plugin always log requests that returned this code.
						'"' . $usageEvent['userAgent'] . '"'));

		$usageLogEntry = implode(' ', $desiredParams) . PHP_EOL;

		import('lib.pkp.classes.file.PrivateFileManager');
		$fileMgr = new PrivateFileManager();

		// Get the current day filename.
		$filename = $this->getUsageEventCurrentDayLogName();

		// Check the plugin file directory.
		$usageEventFilesPath = $this->getUsageEventLogsPath();
		if (!$fileMgr->fileExists($usageEventFilesPath, 'dir')) {
			$success = $fileMgr->mkdirtree($usageEventFilesPath);
			if (!$success) {
				// Files directory wrong configuration?
				assert(false);
				return false;
			}
		}

		$filePath = $usageEventFilesPath . DIRECTORY_SEPARATOR . $filename;
		$fp = fopen($filePath, 'ab');
		if (flock($fp, LOCK_EX)) {
			fwrite($fp, $usageLogEntry);
			flock($fp, LOCK_UN);
		} else {
			// Couldn't lock the file.
			assert(false);
		}
		fclose($fp);
	}

	//
	// Private helper methods.
	//
	/**
	* Hash (SHA256) the given IP using the given SALT.
	*
	* NB: This implementation was taken from OA-S directly. See
	* http://sourceforge.net/p/openaccessstati/code-0/3/tree/trunk/logfile-parser/lib/logutils.php
	* We just do not implement the PHP4 part as OJS dropped PHP4 support.
	*
	* @param $ip string
	* @param $salt string
	* @return string|boolean The hashed IP or boolean false if something went wrong.
	*/
	function _hashIp($ip, $salt) {
		if(function_exists('mhash')) {
			return bin2hex(mhash(MHASH_SHA256, $ip.$salt));
		} else {
			assert(function_exists('hash'));
			if (!function_exists('hash')) return false;
			return hash('sha256', $ip.$salt);
		}
	}
}

?>
