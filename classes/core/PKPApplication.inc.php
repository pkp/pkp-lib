<?php

/**
 * @file classes/core/PKPApplication.inc.php
 *
 * Copyright (c) 2000-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPApplication
 * @ingroup core
 *
 * @brief Class describing this application.
 *
 */

define_exposed('REALLY_BIG_NUMBER', 10000);

define('ROUTE_COMPONENT', 'component');
define('ROUTE_PAGE', 'page');

define('CONTEXT_SITE', 0);
define('CONTEXT_ID_NONE', 0);
define('REVIEW_ROUND_NONE', 0);

define('ASSOC_TYPE_PRODUCTION_ASSIGNMENT',	0x0000202);
define('ASSOC_TYPE_SUBMISSION_FILE',		0x0000203);
define('ASSOC_TYPE_REVIEW_RESPONSE',		0x0000204);
define('ASSOC_TYPE_REVIEW_ASSIGNMENT',		0x0000205);
define('ASSOC_TYPE_SUBMISSION_EMAIL_LOG_ENTRY',	0x0000206);
define('ASSOC_TYPE_WORKFLOW_STAGE',		0x0000207);
define('ASSOC_TYPE_NOTE',			0x0000208);
define('ASSOC_TYPE_ANNOUNCEMENT',		0x000020A);
define('ASSOC_TYPE_REVIEW_ROUND',		0x000020B);
define('ASSOC_TYPE_SUBMISSION_FILES',		0x000020F);
define('ASSOC_TYPE_PUBLISHED_SUBMISSION',	0x0000210);
define('ASSOC_TYPE_PLUGIN',			0x0000211);
define('ASSOC_TYPE_USER',			0x0001000); // This value used because of bug #6068
define('ASSOC_TYPE_USER_GROUP',			0x0100002);
define('ASSOC_TYPE_CITATION',			0x0100003);
define('ASSOC_TYPE_AUTHOR',			0x0100004);
define('ASSOC_TYPE_EDITOR',			0x0100005);
define('ASSOC_TYPE_SIGNOFF',			0x0100006);
define('ASSOC_TYPE_USER_ROLES',			0x0100007);
define('ASSOC_TYPE_ACCESSIBLE_WORKFLOW_STAGES',	0x0100008);
define('ASSOC_TYPE_SUBMISSION',			0x0100009);

define_exposed('WORKFLOW_STAGE_ID_PUBLISHED', 0); // FIXME? See bug #6463.
define_exposed('WORKFLOW_STAGE_ID_SUBMISSION', 1);
define_exposed('WORKFLOW_STAGE_ID_INTERNAL_REVIEW', 2);
define_exposed('WORKFLOW_STAGE_ID_EXTERNAL_REVIEW', 3);
define_exposed('WORKFLOW_STAGE_ID_EDITING', 4);
define_exposed('WORKFLOW_STAGE_ID_PRODUCTION', 5);

// FIXME: these were defined in userGroup. they need to be moved somewhere with classes that do mapping.
define('WORKFLOW_STAGE_PATH_SUBMISSION', 'submission');
define('WORKFLOW_STAGE_PATH_INTERNAL_REVIEW', 'internalReview');
define('WORKFLOW_STAGE_PATH_EXTERNAL_REVIEW', 'externalReview');
define('WORKFLOW_STAGE_PATH_EDITING', 'editorial');
define('WORKFLOW_STAGE_PATH_PRODUCTION', 'production');

// To expose LISTBUILDER_SOURCE_TYPE_... constants via JS
import('lib.pkp.classes.controllers.listbuilder.ListbuilderHandler');

// To expose ORDER_CATEGORY_GRID_... constants via JS
import('lib.pkp.classes.controllers.grid.feature.OrderCategoryGridItemsFeature');

class PKPApplication {
	var $enabledProducts;
	var $allProducts;

	function PKPApplication() {
		// Seed random number generator
		mt_srand(((double) microtime()) * 1000000);

		import('lib.pkp.classes.core.Core');
		import('lib.pkp.classes.core.String');
		import('lib.pkp.classes.core.Registry');

		import('lib.pkp.classes.config.Config');

		if (Config::getVar('debug', 'display_errors')) {
			// Try to switch off normal error display when error display
			// is being managed by us.
			ini_set('display_errors', false);
		}

		Registry::set('application', $this);

		import('lib.pkp.classes.db.DAORegistry');
		import('lib.pkp.classes.db.XMLDAO');

		import('lib.pkp.classes.cache.CacheManager');

		import('classes.security.Validation');
		import('lib.pkp.classes.session.SessionManager');
		import('classes.template.TemplateManager');
		import('classes.notification.NotificationManager');

		import('lib.pkp.classes.plugins.PluginRegistry');
		import('lib.pkp.classes.plugins.HookRegistry');

		import('classes.i18n.AppLocale');

		String::init();
		set_error_handler(array($this, 'errorHandler'));

		$microTime = Core::microtime();
		Registry::set('system.debug.startTime', $microTime);

		$notes = array();
		Registry::set('system.debug.notes', $notes);

		if (Config::getVar('general', 'installed')) {
			// Initialize database connection
			$conn = DBConnection::getInstance();

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
	 * @return Application
	 */
	static function &getApplication() {
		$application =& Registry::get('application');
		return $application;
	}

	/**
	 * Get the request implementation singleton
	 * @return Request
	 */
	static function &getRequest() {
		$request =& Registry::get('request', true, null);

		if (is_null($request)) {
			import('classes.core.Request');

			// Implicitly set request by ref in the registry
			$request = new Request();
		}

		return $request;
	}

	/**
	 * Get the dispatcher implementation singleton
	 * @return Dispatcher
	 */
	static function &getDispatcher() {
		$dispatcher =& Registry::get('dispatcher', true, null);

		if (is_null($dispatcher)) {
			import('lib.pkp.classes.core.Dispatcher');

			// Implicitly set dispatcher by ref in the registry
			$dispatcher = new Dispatcher();

			// Inject dependency
			$dispatcher->setApplication(PKPApplication::getApplication());

			// Inject router configuration
			$dispatcher->addRouterName('lib.pkp.classes.core.PKPComponentRouter', ROUTE_COMPONENT);
			$dispatcher->addRouterName('classes.core.PageRouter', ROUTE_PAGE);
		}

		return $dispatcher;
	}

	/**
	 * This executes the application by delegating the
	 * request to the dispatcher.
	 */
	function execute() {
		// Dispatch the request to the correct handler
		$dispatcher = $this->getDispatcher();
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
	 * This function retrieves all enabled product versions once
	 * from the database and caches the result for further
	 * access.
	 *
	 * @param $category string
	 * @param $mainContextId integer Optional ID of the top-level context
	 * (e.g. Journal, Conference, Press) to query for enabled products
	 * @return array
	 */
	function &getEnabledProducts($category = null, $mainContextId = null) {
		if (is_null($this->enabledProducts) || !is_null($mainContextId)) {
			$contextDepth = $this->getContextDepth();

			$settingContext = array();
			if ($contextDepth > 0) {
				$request = $this->getRequest();
				$router = $request->getRouter();

				if (is_null($mainContextId)) {
					// Try to identify the main context (e.g. journal, conference, press),
					// will be null if none found.
					$mainContext = $router->getContext($request, 1);
					if ($mainContext) $mainContextId = $mainContext->getId();
				}

				// Create the context for the setting if found
				if (!is_null($mainContextId)) $settingContext[] = $mainContextId;
				$settingContext = array_pad($settingContext, $contextDepth, 0);
				$settingContext = array_combine($this->getContextList(), $settingContext);
			}

			$versionDao = DAORegistry::getDAO('VersionDAO'); /* @var $versionDao VersionDAO */
			$this->enabledProducts =& $versionDao->getCurrentProducts($settingContext);
		}

		if (is_null($category)) {
			return $this->enabledProducts;
		} elseif (isset($this->enabledProducts[$category])) {
			return $this->enabledProducts[$category];
		} else {
			$returner = array();
			return $returner;
		}
	}

	/**
	 * Get the list of plugin categories for this application.
	 */
	function getPluginCategories() {
		// To be implemented by sub-classes
		assert(false);
	}

	/**
	 * Return the current version of the application.
	 * @return Version
	 */
	function &getCurrentVersion() {
		$currentVersion =& $this->getEnabledProducts('core');
		assert(count($currentVersion)) == 1;
		return $currentVersion[$this->getName()];
	}

	/**
	 * Get the map of DAOName => full.class.Path for this application.
	 * @return array
	 */
	function getDAOMap() {
		return array(
			'AccessKeyDAO' => 'lib.pkp.classes.security.AccessKeyDAO',
			'AuthSourceDAO' => 'lib.pkp.classes.security.AuthSourceDAO',
			'CitationDAO' => 'lib.pkp.classes.citation.CitationDAO',
			'ControlledVocabDAO' => 'lib.pkp.classes.controlledVocab.ControlledVocabDAO',
			'ControlledVocabEntryDAO' => 'lib.pkp.classes.controlledVocab.ControlledVocabEntryDAO',
			'CountryDAO' => 'lib.pkp.classes.i18n.CountryDAO',
			'CurrencyDAO' => 'lib.pkp.classes.currency.CurrencyDAO',
			'DataObjectTombstoneDAO' => 'lib.pkp.classes.tombstone.DataObjectTombstoneDAO',
			'DataObjectTombstoneSettingsDAO' => 'lib.pkp.classes.tombstone.DataObjectTombstoneSettingsDAO',
			'EditDecisionDAO' => 'lib.pkp.classes.submission.EditDecisionDAO',
			'FilterDAO' => 'lib.pkp.classes.filter.FilterDAO',
			'FilterGroupDAO' => 'lib.pkp.classes.filter.FilterGroupDAO',
			'GenreDAO' => 'lib.pkp.classes.submission.GenreDAO',
			'InterestDAO' => 'lib.pkp.classes.user.InterestDAO',
			'InterestEntryDAO' => 'lib.pkp.classes.user.InterestEntryDAO',
			'LanguageDAO' => 'lib.pkp.classes.language.LanguageDAO',
			'LibraryFileDAO' => 'lib.pkp.classes.context.LibraryFileDAO',
			'MetadataDescriptionDAO' => 'lib.pkp.classes.metadata.MetadataDescriptionDAO',
			'NotificationDAO' => 'lib.pkp.classes.notification.NotificationDAO',
			'NotificationMailListDAO' => 'lib.pkp.classes.notification.NotificationMailListDAO',
			'NotificationSettingsDAO' => 'lib.pkp.classes.notification.NotificationSettingsDAO',
			'NotificationSubscriptionSettingsDAO' => 'lib.pkp.classes.notification.NotificationSubscriptionSettingsDAO',
			'ProcessDAO' => 'lib.pkp.classes.process.ProcessDAO',
			'ReviewFilesDAO' => 'lib.pkp.classes.submission.ReviewFilesDAO',
			'ReviewRoundDAO' => 'lib.pkp.classes.submission.reviewRound.ReviewRoundDAO',
			'ScheduledTaskDAO' => 'lib.pkp.classes.scheduledTask.ScheduledTaskDAO',
			'SessionDAO' => 'lib.pkp.classes.session.SessionDAO',
			'SignoffDAO' => 'lib.pkp.classes.signoff.SignoffDAO',
			'SiteDAO' => 'lib.pkp.classes.site.SiteDAO',
			'SiteSettingsDAO' => 'lib.pkp.classes.site.SiteSettingsDAO',
			'SubmissionAgencyDAO' => 'lib.pkp.classes.submission.SubmissionAgencyDAO',
			'SubmissionAgencyEntryDAO' => 'lib.pkp.classes.submission.SubmissionAgencyEntryDAO',
			'SubmissionDisciplineDAO' => 'lib.pkp.classes.submission.SubmissionDisciplineDAO',
			'SubmissionDisciplineEntryDAO' => 'lib.pkp.classes.submission.SubmissionDisciplineEntryDAO',
			'SubmissionEmailLogDAO' => 'lib.pkp.classes.log.SubmissionEmailLogDAO',
			'SubmissionFileEventLogDAO' => 'lib.pkp.classes.log.SubmissionFileEventLogDAO',
			'SubmissionFileSignoffDAO' => 'lib.pkp.classes.submission.SubmissionFileSignoffDAO',
			'SubmissionLanguageDAO' => 'lib.pkp.classes.submission.SubmissionLanguageDAO',
			'SubmissionLanguageEntryDAO' => 'lib.pkp.classes.submission.SubmissionLanguageEntryDAO',
			'SubmissionKeywordDAO' => 'lib.pkp.classes.submission.SubmissionKeywordDAO',
			'SubmissionKeywordEntryDAO' => 'lib.pkp.classes.submission.SubmissionKeywordEntryDAO',
			'SubmissionSubjectDAO' => 'lib.pkp.classes.submission.SubmissionSubjectDAO',
			'SubmissionSubjectEntryDAO' => 'lib.pkp.classes.submission.SubmissionSubjectEntryDAO',
			'TimeZoneDAO' => 'lib.pkp.classes.i18n.TimeZoneDAO',
			'TemporaryFileDAO' => 'lib.pkp.classes.file.TemporaryFileDAO',
			'UserGroupDAO' => 'lib.pkp.classes.security.UserGroupDAO',
			'UserStageAssignmentDAO' => 'lib.pkp.classes.user.UserStageAssignmentDAO',
			'VersionDAO' => 'lib.pkp.classes.site.VersionDAO',
			'ViewsDAO' => 'lib.pkp.classes.views.ViewsDAO',
			'XMLDAO' => 'lib.pkp.classes.db.XMLDAO'
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
							$a = htmlspecialchars($a);
							$args .= "\"$a\"";
							break;
						case 'array':
							$args .= 'Array('.count($a).')';
							break;
						case 'object':
							$args .= 'Object('.get_class($a).')';
							break;
						case 'resource':
							$args .= 'Resource('.$a.')';
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

	/**
	 * Define a constant so that it can be exposed to the JS front-end.
	 * @param $name string
	 * @param $value mixed
	 */
	static function defineExposedConstant($name, $value) {
		define($name, $value);
		assert(preg_match('/^[a-zA-Z_]+$/', $name));
		$constants =& PKPApplication::getExposedConstants();
		$constants[$name] = $value;
	}

	/**
	 * Get an associative array of defined constants that should be exposed
	 * to the JS front-end.
	 * @return array
	 */
	static function &getExposedConstants() {
		static $exposedConstants = array();
		return $exposedConstants;
	}

	/**
	 * Get an array of locale keys that define strings that should be made available to
	 * JavaScript classes in the JS front-end.
	 * @return array
	 */
	function getJSLocaleKeys() {
		$keys = array('form.dataHasChanged');
		return $keys;
	}

	/**
	 * Get the top-level context DAO.
	 */
	static function getContextDAO() {
		assert(false); // Must be implemented by subclasses
	}

	/**
	 * Get the submission DAO.
	 */
	static function getSubmissionDAO() {
		assert(false); // Must be implemented by subclasses
	}

	/**
	 * Get the DAO for ROLE_ID_SUB_EDITOR roles.
	 */
	static function getSubEditorDAO() {
		assert(false); // Must be implemented by subclasses
	}

	/**
	 * Get the stages used by the application.
	 */
	static function getApplicationStages() {
		assert(false); // Must be implemented by subclasses
	}

	/**
	 * Get the file directory array map used by the application.
	 * should return array('context' => ..., 'submission' => ...)
	 */
	static function getFileDirectories() {
		assert(false); // Must be implemented by subclasses.
	}
}

/**
 * @see PKPApplication::defineExposedConstant()
 */
function define_exposed($name, $value) {
	PKPApplication::defineExposedConstant($name, $value);
}

?>
