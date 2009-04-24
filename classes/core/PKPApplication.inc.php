<?php

/**
 * @file classes/core/PKPApplication.inc.php
 *
 * Copyright (c) 2000-2009 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPApplication
 * @ingroup core
 *
 * @brief Class describing this application.
 *
 */

// $Id$


define('REALLY_BIG_NUMBER', 10000);

class PKPApplication {
	/**
	 * Initialize the application with a given application object
	 * @param $application object Subclass of PKPApplication class to use
	 */
	function initialize(&$application) {
		Registry::set('application', $application);
	}

	function PKPApplication() {
		// Begin debug logging
		Console::logMemory('', 'Begin logging data');

		// Inititalize the application.
		error_reporting(E_ALL);

		// Seed random number generator
		mt_srand(((double) microtime()) * 1000000);

		import('core.Core');
		import('core.PKPRequest');
		import('core.String');
		import('core.Registry');

		import('config.Config');

		import('db.DAORegistry');
		import('db.XMLDAO');

		import('security.Validation');
		import('session.SessionManager');
		import('template.TemplateManager');

		import('plugins.PluginRegistry');
		import('plugins.HookRegistry');

		$this->initialize($this);
		String::init();

		if ($this->isCacheable()) {
			// Can we serve a cached response?
			if (Config::getVar('cache', 'web_cache')) {
				if ($this->displayCached()) exit(); // Success
				ob_start(array(&$this, 'cacheContent'));
			}
		} else {
			if (isset($_SERVER['HTTP_X_MOZ']) && $_SERVER['HTTP_X_MOZ'] == 'prefetch') {
				header('HTTP/1.0 403 Forbidden');
				echo '403: Forbidden<br><br>Pre-fetching not allowed.';
				exit;
			}
		}

		Locale::initialize();
		PluginRegistry::loadCategory('generic');
	}

	/**
	 * Get the current application object
	 * @return object
	 */
	function &getApplication() {
		$application =& Registry::get('application');
		return $application;
	}

	/**
	 * Get the locale key for the name of this application.
	 * @return string
	 */
	function getNameKey() {
		fatalError('Abstract method');
	}

	/**
	 * Get the "context depth" of this application, i.e. the number of
	 * parts of the URL after index.php that represent the context of
	 * the current request (e.g. Journal [1], or Conference and
	 * Scheduled Conference [2]).
	 * @return int
	 */
	function getContextDepth() {
		fatalError('Abstract method');
	}

	/**
	 * Get the list of the contexts available for this application
	 * i.e. the various parameters that are needed to represent the
	 * (e.g. array('journal') or array('conference', 'schedConf'))
	 * @return Array
	 */
	function getContextList() {
		fatalError('Abstract method');
	}

	/**
	 * Get the URL to the XML descriptor for the current version of this
	 * application.
	 * @return string
	 */
	function getVersionDescriptorUrl() {
		fatalError('Abstract method');
	}

	/**
	 * Determine whether or not this request is cacheable
	 * @return boolean
	 */
	function isCacheable() {
		return false;
	}

	/**
	 * Determine the filename to use for a local cache file.
	 * @return string
	 */
	function getCacheFilename() {
		fatalError('Abstract method');
	}

	/**
	 * Cache content as a local file.
	 * @param $contents string
	 * @return string
	 */
	function cacheContent($contents) {
		$filename = $this->getCacheFilename();
		$fp = fopen($filename, 'w');
		if ($fp) {
			fwrite($fp, mktime() . ':' . $contents);
			fclose($fp);
		}
		return $contents;
	}

	/**
	 * Display the request contents from cache.
	 */
	function displayCached() {
		$filename = $this->getCacheFilename();
		if (!file_exists($filename)) return false;

		$fp = fopen($filename, 'r');
		$data = fread($fp, filesize($filename));
		fclose($fp);

		$i = strpos($data, ':');
		$time = substr($data, 0, $i);
		$contents = substr($data, $i+1);

		if (mktime() > $time + Config::getVar('cache', 'web_cache_hours') * 60 * 60) return false;

		header('Content-Type: text/html; charset=' . Config::getVar('i18n', 'client_charset'));

		echo $contents;
		return true;
	}

	/**
	 * Get the map of DAOName => full.class.Path for this application.
	 * @return array
	 */
	function getDAOMap() {
		return array(
			'AccessKeyDAO' => 'security.AccessKeyDAO',
			'AuthSourceDAO' => 'security.AuthSourceDAO',
			'CaptchaDAO' => 'captcha.CaptchaDAO',
			'ControlledVocabDAO' => 'controlledVocab.ControlledVocabDAO',
			'ControlledVocabEntryDAO' => 'controlledVocab.ControlledVocabEntryDAO',
			'CountryDAO' => 'i18n.CountryDAO',
			'CurrencyDAO' => 'currency.CurrencyDAO',
			'GroupDAO' => 'group.GroupDAO',
			'GroupMembershipDAO' => 'group.GroupMembershipDAO',
			'HelpTocDAO' => 'help.HelpTocDAO',
			'HelpTopicDAO' => 'help.HelpTopicDAO',
			'NotificationDAO' => 'notification.NotificationDAO',
			'NotificationSettingsDAO' => 'notification.NotificationSettingsDAO',
			'ScheduledTaskDAO' => 'scheduledTask.ScheduledTaskDAO',
			'SessionDAO' => 'session.SessionDAO',
			'SignoffDAO' => 'signoff.SignoffDAO',
			'SiteDAO' => 'site.SiteDAO',
			'SiteSettingsDAO' => 'site.SiteSettingsDAO',
			'TemporaryFileDAO' => 'file.TemporaryFileDAO',
			'VersionDAO' => 'site.VersionDAO',
			'XMLDAO' => 'db.XMLDAO'
		);
	}

	/**
	 * Return the fully-qualified (e.g. page.name.ClassNameDAO) name of the
	 * given DAO.
	 * @param $name string
	 * @return string
	 */
	function getQualifiedDAOName($name) {
		$map =& Registry::get('daoMap', true, $this->getDAOMap());
		if (isset($map[$name])) return $map[$name];
		return null;
	}

	/**
	 * Instantiate the help object for this application.
	 * @return object
	 */
	function &instantiateHelp() {
		fatalError('Abstract class');
	}
}

?>
