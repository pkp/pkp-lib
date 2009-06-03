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
		Console::logMemory('', 'PKPApplication::construct');
		Console::logSpeed('PKPApplication::construct');

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
		set_error_handler(array($this, 'errorHandler'));

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
	
	/**
	 * Custom error handler
	 * @param $errorno string
	 * @param $errstr string
	 * @param $errfile string
	 * @param $errline string
	 */
	function errorHandler($errorno, $errstr, $errfile, $errline) {
		// FIXME: Error logging needs to be suppressed for strict errors as long as we support PHP4
		if(error_reporting() != 0 && $errorno != 2048) {
			if ($errorno ==  E_ERROR) {
				echo 'An error has occurred.  Please check your PHP log file.';
			} else if(Config::getVar('debug', 'display_errors')) {
				echo $this->buildErrorMessage($errorno, $errstr, $errfile, $errline);
			}

			error_log($this->buildErrorMessage($errorno, $errstr, $errfile, $errline), 0);
		}
	}
	
	/**
	 * Auxiliary function to errorHandler that returns a formatted error message.
	 * Error type formatting code adapted from ash, http://ca3.php.net/manual/en/function.set-error-handler.php
	 * @param $errorno string
	 * @param $errstr string
	 * @param $errfile string
	 * @param $errline string
 	 * @return $message string
	 */
	function buildErrorMessage($errorno, $errstr, $errfile, $errline) {
		$message = array();
		$errorType = array (
		   E_ERROR            => 'ERROR',
		   E_WARNING        => 'WARNING',
		   E_PARSE          => 'PARSING ERROR',
		   E_NOTICE         => 'NOTICE',
		   E_CORE_ERROR     => 'CORE ERROR',
		   E_CORE_WARNING   => 'CORE WARNING',
		   E_COMPILE_ERROR  => 'COMPILE ERROR',
		   E_COMPILE_WARNING => 'COMPILE WARNING',
		   E_USER_ERROR     => 'USER ERROR',
		   E_USER_WARNING   => 'USER WARNING',
		   E_USER_NOTICE    => 'USER NOTICE',
	   );
	   
		if (array_key_exists($errorno, $errorType)) {
			$type = $errorType[$errorno];
		} else {
			$type = 'CAUGHT EXCEPTION';
		}

		// Return abridged message if strict error or notice (since they are more common)
		if ($errorno == E_NOTICE) {
			return $type . ': ' . $errstr . ' (' . $errfile . ':' . $errline . ')';
		}
	

		$message[] = $this->getName() . ' has produced an error';
		$message[] = '  Message: ' . $type . ': ' . $errstr;
		$message[] = '  In file: ' . $errfile;
		$message[] = '  At line: ' . $errline;
		$message[] = '  Stacktrace: ';
		
		if(Config::getVar('debug', 'show_stacktrace')) {
			$trace = debug_backtrace();
			// Remove the call to fatalError from the call trace.
			array_shift($trace);
	
			// Back-trace pretty-printer adapted from the following URL:
			// http://ca3.php.net/manual/en/function.debug-backtrace.php
			// Thanks to diz at ysagoon dot com

			foreach ($trace as $bt) {
				$args = '';
				if (isset($bt['args'])) foreach ($bt['args'] as $a) {
					if (!empty($args)) {
						$args .= ', ';
					}
					switch (gettype($a)) {
						case 'integer':
						case 'double':
							$args .= $a;
							break;
						case 'string':
							$a = htmlspecialchars(substr($a, 0, 64)).((strlen($a) > 64) ? '...' : '');
							$args .= "\"$a\"";
							break;
						case 'array':
							$args .= 'Array('.count($a).')';
							break;
						case 'object':
							$args .= 'Object('.get_class($a).')';
							break;
						case 'resource':
							$args .= 'Resource('.strstr($a, '#').')';
							break;
						case 'boolean':
							$args .= $a ? 'True' : 'False';
							break;
						case 'NULL':
							$args .= 'Null';
							break;
						default:
							$args .= 'Unknown';
					}
				}
				$class = isset($bt['class'])?$bt['class']:'';
				$type = isset($bt['type'])?$bt['type']:'';
				$function = isset($bt['function'])?$bt['function']:'';
				$file = isset($bt['file'])?$bt['file']:'(unknown)';
				$line = isset($bt['line'])?$bt['line']:'(unknown)';
	
				$message[] =  "   File: {$file} line {$line}";
				$message[] =  "     Function: {$class}{$type}{$function}($args)";
			}
		}
		
		$dbconn = &DBConnection::getConn();
		$dbServerInfo = $dbconn->ServerInfo();
		
		$message[] =  "  Server info:";
		$message[] =  "   OS: " . Core::serverPHPOS();
		$message[] =  "   PHP Version: " . Core::serverPHPVersion();
		$message[] =  "   Apache Version: " . (function_exists('apache_get_version') ? apache_get_version() : 'N/A');
		$message[] =  "   DB Driver: " . Config::getVar('database', 'driver');
		$message[] =  "   DB server version: " . (empty($dbServerInfo['description']) ? $dbServerInfo['version'] : $dbServerInfo['description']);
		
	
		return implode("\n", $message); 		
	}
}

?>
