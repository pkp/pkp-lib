<?php

/**
 * @file classes/core/PKPApplication.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
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

define('ROUTE_COMPONENT', 'component');
define('ROUTE_PAGE', 'page');

class PKPApplication {
	function PKPApplication() {
		// Configure error reporting
		// FIXME: Error logging needs to be suppressed for strict
		// and deprecation errors in PHP5 as long as we support PHP 4.
		// This is primarily for static method warnings and warnings
		// about use of ... =& new ... Static class members cannot be
		// declared in PHP4 and ... =& new ... is deprecated since PHP 5.
		$errorReportingLevel = E_ALL;
		if (defined('E_STRICT')) $errorReportingLevel &= ~E_STRICT;
		if (defined('E_DEPRECATED')) $errorReportingLevel &= ~E_DEPRECATED;
		@error_reporting($errorReportingLevel);

		// Instantiate the profiler
		import('core.PKPProfiler');
		$pkpProfiler = new PKPProfiler();

		// Begin debug logging
		Console::logMemory('', 'PKPApplication::construct');
		Console::logSpeed('PKPApplication::construct');

		// Seed random number generator
		mt_srand(((double) microtime()) * 1000000);

		import('core.Core');
		import('core.String');
		import('core.Registry');

		import('config.Config');

		if (Config::getVar('debug', 'display_errors')) {
			// Try to switch off normal error display when error display
			// is being managed by OJS.
			@ini_set('display_errors', false);
		}

		if (Config::getVar('debug', 'deprecation_warnings')) {
			// Switch deprecation warnings back on. This can only be done
			// after declaring the Config class as we need access to the
			// configuration and we cannot declare the Config class before
			// we've switched of deprecation warnings as its declaration
			// causes warnings itself.
			if (defined('E_STRICT')) $errorReportingLevel |= E_STRICT;
			if (defined('E_DEPRECATED')) $errorReportingLevel |= E_DEPRECATED;
			@error_reporting($errorReportingLevel);
		}

		Registry::set('application', $this);

		import('db.DAORegistry');
		import('db.XMLDAO');

		import('cache.CacheManager');

		import('security.Validation');
		import('session.SessionManager');
		import('template.TemplateManager');

		import('plugins.PluginRegistry');
		import('plugins.HookRegistry');

		import('i18n.AppLocale');

		String::init();
		set_error_handler(array($this, 'errorHandler'));

		$microTime = Core::microtime();
		Registry::set('system.debug.startTime', $microTime);

		$notes = array();
		Registry::set('system.debug.notes', $notes);

		Registry::set('system.debug.profiler', $pkpProfiler);

		if (Config::getVar('general', 'installed')) {
			// Initialize database connection
			$conn =& DBConnection::getInstance();

			if (!$conn->isConnected()) {
				if (Config::getVar('database', 'debug')) {
					$dbconn =& $conn->getDBConn();
					fatalError('Database connection failed: ' . $dbconn->errorMsg());
				} else {
					fatalError('Database connection failed!');
				}
			}
		}
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
	 * Get the request implementation singleton
	 * @return Request
	 */
	function &getRequest() {
		$request =& Registry::get('request', true, null);

		if (is_null($request)) {
			import('core.Request');

			// Implicitly set request by ref in the registry
			$request = new Request();
		}

		return $request;
	}

	/**
	 * Get the dispatcher implementation singleton
	 * @return Dispatcher
	 */
	function &getDispatcher() {
		$dispatcher =& Registry::get('dispatcher', true, null);

		if (is_null($dispatcher)) {
			import('core.Dispatcher');

			// Implicitly set dispatcher by ref in the registry
			$dispatcher = new Dispatcher();

			// Inject dependency
			$dispatcher->setApplication($this->getApplication());

			// Inject router configuration
			$dispatcher->addRouterName('core.PKPComponentRouter', ROUTE_COMPONENT);
			$dispatcher->addRouterName('core.PageRouter', ROUTE_PAGE);
		}

		return $dispatcher;
	}

	/**
	 * This executes the application by delegating the
	 * request to the dispatcher.
	 */
	function execute() {
		// Dispatch the request to the correct handler
		$dispatcher =& $this->getDispatcher();
		$dispatcher->dispatch($this->getRequest());
	}

	/**
	 * Get the symbolic name of this application
	 * @return string
	 */
	function getName() {
		return 'pkp-lib';
	}

	/**
	 * Get the locale key for the name of this application.
	 * @return string
	 */
	function getNameKey() {
		// must be implemented by sub-classes
		assert(false);
	}

	/**
	 * Get the "context depth" of this application, i.e. the number of
	 * parts of the URL after index.php that represent the context of
	 * the current request (e.g. Journal [1], or Conference and
	 * Scheduled Conference [2]).
	 * @return int
	 */
	function getContextDepth() {
		// must be implemented by sub-classes
		assert(false);
	}

	/**
	 * Get the list of the contexts available for this application
	 * i.e. the various parameters that are needed to represent the
	 * (e.g. array('journal') or array('conference', 'schedConf'))
	 * @return Array
	 */
	function getContextList() {
		// must be implemented by sub-classes
		assert(false);
	}

	/**
	 * Get the URL to the XML descriptor for the current version of this
	 * application.
	 * @return string
	 */
	function getVersionDescriptorUrl() {
		// must be implemented by sub-classes
		assert(false);
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
			'TimeZoneDAO' => 'i18n.TimeZoneDAO',
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
		// must be implemented by sub-classes
		assert(false);
	}

	/**
	 * Custom error handler
	 *
	 * NB: Custom error handlers are called for all error levels
	 * independent of the error_reporting parameter.
	 * @param $errorno string
	 * @param $errstr string
	 * @param $errfile string
	 * @param $errline string
	 */
	function errorHandler($errorno, $errstr, $errfile, $errline) {
		// We only report/log errors if their corresponding
		// error level bit is set in error_reporting.
		// We have to check error_reporting() each time as
		// some application parts change the setting (e.g.
		// smarty, adodb, certain plugins).
		if(error_reporting() & $errorno) {
			if ($errorno == E_ERROR) {
				echo 'An error has occurred.  Please check your PHP log file.';
			} elseif (Config::getVar('debug', 'display_errors')) {
				echo $this->buildErrorMessage($errorno, $errstr, $errfile, $errline) . "<br/>\n";
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
			E_ERROR			=> 'ERROR',
			E_WARNING		=> 'WARNING',
			E_PARSE			=> 'PARSING ERROR',
			E_NOTICE		=> 'NOTICE',
			E_CORE_ERROR		=> 'CORE ERROR',
			E_CORE_WARNING		=> 'CORE WARNING',
			E_COMPILE_ERROR		=> 'COMPILE ERROR',
			E_COMPILE_WARNING	=> 'COMPILE WARNING',
			E_USER_ERROR		=> 'USER ERROR',
			E_USER_WARNING		=> 'USER WARNING',
			E_USER_NOTICE		=> 'USER NOTICE',
		);

		if (array_key_exists($errorno, $errorType)) {
			$type = $errorType[$errorno];
		} else {
			$type = 'CAUGHT EXCEPTION';
		}

		// Return abridged message if strict error or notice (since they are more common)
		// This also avoids infinite loops when E_STRICT (=deprecation level) error
		// reporting is switched on.
		$shortErrors = E_NOTICE;
		if (defined('E_STRICT')) $shortErrors |= E_STRICT;
		if (defined('E_DEPRECATED')) $shortErrors |= E_DEPRECATED;
		if ($errorno & $shortErrors) {
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

				$message[] = "   File: {$file} line {$line}";
				$message[] = "     Function: {$class}{$type}{$function}($args)";
			}
		}

		static $dbServerInfo;
		if (!isset($dbServerInfo) && Config::getVar('general', 'installed')) {
			$dbconn =& DBConnection::getConn();
			$dbServerInfo = $dbconn->ServerInfo();
		}

		$message[] = "  Server info:";
		$message[] = "   OS: " . Core::serverPHPOS();
		$message[] = "   PHP Version: " . Core::serverPHPVersion();
		$message[] = "   Apache Version: " . (function_exists('apache_get_version') ? apache_get_version() : 'N/A');
		$message[] = "   DB Driver: " . Config::getVar('database', 'driver');
		if (isset($dbServerInfo)) $message[] = "   DB server version: " . (empty($dbServerInfo['description']) ? $dbServerInfo['version'] : $dbServerInfo['description']);

		return implode("\n", $message);
	}
}

?>
