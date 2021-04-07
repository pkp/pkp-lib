<?php

/**
 * @file classes/core/PKPApplication.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPApplication
 * @ingroup core
 *
 * @brief Class describing this application.
 *
 */

define('PHP_REQUIRED_VERSION', '7.3.0');

define('ROUTE_COMPONENT', 'component');
define('ROUTE_PAGE', 'page');
define('ROUTE_API', 'api');

define('API_VERSION', 'v1');

define('CONTEXT_SITE', 0);
define('CONTEXT_ID_NONE', 0);
define('CONTEXT_ID_ALL', '_');
define('REVIEW_ROUND_NONE', 0);

define('ASSOC_TYPE_PRODUCTION_ASSIGNMENT',	0x0000202);
define('ASSOC_TYPE_SUBMISSION_FILE',		0x0000203);
define('ASSOC_TYPE_REVIEW_RESPONSE',		0x0000204);
define('ASSOC_TYPE_REVIEW_ASSIGNMENT',		0x0000205);
define('ASSOC_TYPE_SUBMISSION_EMAIL_LOG_ENTRY',	0x0000206);
define('ASSOC_TYPE_WORKFLOW_STAGE',		0x0000207);
define('ASSOC_TYPE_NOTE',			0x0000208);
define('ASSOC_TYPE_REPRESENTATION',		0x0000209);
define('ASSOC_TYPE_ANNOUNCEMENT',		0x000020A);
define('ASSOC_TYPE_REVIEW_ROUND',		0x000020B);
define('ASSOC_TYPE_SUBMISSION_FILES',		0x000020F);
define('ASSOC_TYPE_PLUGIN',			0x0000211);
define('ASSOC_TYPE_SECTION',			0x0000212);
define('ASSOC_TYPE_CATEGORY',			0x000020D);
define('ASSOC_TYPE_USER',			0x0001000); // This value used because of bug #6068
define('ASSOC_TYPE_USER_GROUP',			0x0100002);
define('ASSOC_TYPE_CITATION',			0x0100003);
define('ASSOC_TYPE_AUTHOR',			0x0100004);
define('ASSOC_TYPE_EDITOR',			0x0100005);
define('ASSOC_TYPE_USER_ROLES',			0x0100007);
define('ASSOC_TYPE_ACCESSIBLE_WORKFLOW_STAGES',	0x0100008);
define('ASSOC_TYPE_SUBMISSION',			0x0100009);
define('ASSOC_TYPE_QUERY',			0x010000a);
define('ASSOC_TYPE_QUEUED_PAYMENT',		0x010000b);
define('ASSOC_TYPE_PUBLICATION', 0x010000c);
define('ASSOC_TYPE_ACCESSIBLE_FILE_STAGES',    0x010000d);

// Constant used in UsageStats for submission files that are not full texts
define('ASSOC_TYPE_SUBMISSION_FILE_COUNTER_OTHER', 0x0000213);

// FIXME: these were defined in userGroup. they need to be moved somewhere with classes that do mapping.
define('WORKFLOW_STAGE_PATH_SUBMISSION', 'submission');
define('WORKFLOW_STAGE_PATH_INTERNAL_REVIEW', 'internalReview');
define('WORKFLOW_STAGE_PATH_EXTERNAL_REVIEW', 'externalReview');
define('WORKFLOW_STAGE_PATH_EDITING', 'editorial');
define('WORKFLOW_STAGE_PATH_PRODUCTION', 'production');

// Constant used to distinguish between editorial and author workflows
define('WORKFLOW_TYPE_EDITORIAL', 'editorial');
define('WORKFLOW_TYPE_AUTHOR', 'author');

use Illuminate\Database\Capsule\Manager as Capsule;

interface iPKPApplicationInfoProvider {
	/**
	 * Get the top-level context DAO.
	 */
	public static function getContextDAO();

	/**
	 * Get the section DAO.
	 * @return DAO
	 */
	public static function getSectionDAO();

	/**
	 * Get the representation DAO.
	 */
	public static function getRepresentationDAO();

	/**
	 * Get a SubmissionSearchIndex instance.
	 */
	public static function getSubmissionSearchIndex();

	/**
	 * Get a SubmissionSearchDAO instance.
	 */
	public static function getSubmissionSearchDAO();

	/**
	 * Get the stages used by the application.
	 */
	public static function getApplicationStages();

	/**
	 * Get the file directory array map used by the application.
	 * should return array('context' => ..., 'submission' => ...)
	 */
	public static function getFileDirectories();

	/**
	 * Returns the context type for this application.
	 */
	public static function getContextAssocType();
}

abstract class PKPApplication implements iPKPApplicationInfoProvider {
	var $enabledProducts = array();
	var $allProducts;

	/**
	 * Constructor
	 */
	public function __construct() {
		// Seed random number generator
		mt_srand(((double) microtime()) * 1000000);

		import('lib.pkp.classes.core.Core');
		import('lib.pkp.classes.core.PKPString');
		import('lib.pkp.classes.core.Registry');

		import('lib.pkp.classes.config.Config');

		// Load Composer autoloader
		require_once('lib/pkp/lib/vendor/autoload.php');

		ini_set('display_errors', Config::getVar('debug', 'display_errors', ini_get('display_errors')));
		if (!defined('SESSION_DISABLE_INIT') && !Config::getVar('general', 'installed')) {
			define('SESSION_DISABLE_INIT', true);
		}

		Registry::set('application', $this);

		import('lib.pkp.classes.db.DAORegistry');
		import('lib.pkp.classes.db.XMLDAO');

		import('lib.pkp.classes.cache.CacheManager');

		import('lib.pkp.classes.security.RoleDAO');
		import('lib.pkp.classes.security.Validation');
		import('lib.pkp.classes.session.SessionManager');
		import('classes.template.TemplateManager');
		import('classes.notification.NotificationManager');
		import('lib.pkp.classes.statistics.PKPStatisticsHelper');

		import('lib.pkp.classes.plugins.PluginRegistry');
		import('lib.pkp.classes.plugins.HookRegistry');

		import('classes.i18n.AppLocale');

		PKPString::init();

		$microTime = Core::microtime();
		Registry::set('system.debug.startTime', $microTime);

		$notes = array();
		Registry::set('system.debug.notes', $notes);

		if (Config::getVar('general', 'installed')) $this->initializeDatabaseConnection();

		// Register custom autoloader functions for namespaces
		spl_autoload_register(function($class) {
			$prefix = 'PKP\\';
			$rootPath = BASE_SYS_DIR . "/lib/pkp/classes";
			customAutoload($rootPath, $prefix, $class);
		});
		spl_autoload_register(function($class) {
			$prefix = 'APP\\';
			$rootPath = BASE_SYS_DIR . "/classes";
			customAutoload($rootPath, $prefix, $class);
		});
	}

	/**
	 * Initialize the database connection (and related systems)
	 */
	public function initializeDatabaseConnection() {
		// Ensure multiple calls to this function don't cause trouble
		static $databaseConnectionInitialized = false;
		if ($databaseConnectionInitialized) return;

		$databaseConnectionInitialized = true;
		$laravelContainer = new Illuminate\Container\Container();
		Registry::set('laravelContainer', $laravelContainer);
		(new Illuminate\Bus\BusServiceProvider($laravelContainer))->register();
		(new Illuminate\Events\EventServiceProvider($laravelContainer))->register();
		$eventDispatcher = new \Illuminate\Events\Dispatcher($laravelContainer);
		$laravelContainer->instance('Illuminate\Contracts\Events\Dispatcher', $eventDispatcher);
		$laravelContainer->instance('Illuminate\Contracts\Container\Container', $laravelContainer);

		// Map valid config options to Illuminate database drivers
		$driver = strtolower(Config::getVar('database', 'driver'));
		if (substr($driver, 0, 8) === 'postgres') {
			$driver = 'pgsql';
		} else {
			$driver = 'mysql';
		}

		$capsule = new Capsule;
		$capsule->addConnection([
			'driver'    => $driver,
			'host'      => Config::getVar('database', 'host'),
			'database'  => Config::getVar('database', 'name'),
			'username'  => Config::getVar('database', 'username'),
			'port'      => Config::getVar('database', 'port'),
			'unix_socket'=> Config::getVar('database', 'unix_socket'),
			'password'  => Config::getVar('database', 'password'),
			'charset'   => Config::getVar('i18n', 'connection_charset', 'utf8'),
			'collation' => Config::getVar('database', 'collation', 'utf8_general_ci'),
		]);
		$capsule->setEventDispatcher($eventDispatcher);
		$capsule->setAsGlobal();
		if (Config::getVar('database', 'debug')) Capsule::listen(function($query) {
			error_log("Database query\n$query->sql\n" . json_encode($query->bindings));//\n Bindings: " . print_r($query->bindings, true));
		});


		// Set up Laravel queue handling
		$laravelContainer->bind('exception.handler', function () {
			return new class implements Illuminate\Contracts\Debug\ExceptionHandler {
				public function shouldReport(Throwable $e) {
					return true;
				}

				public function report(Throwable $e) {
					error_log((string) $e);
				}

				public function render($request, Throwable $e) {
					return null;
				}

				public function renderForConsole($output, Throwable $e) {
					echo (string) $e;
				}
			};
		});

		$queue = new Illuminate\Queue\Capsule\Manager($laravelContainer);

		// Synchronous (immediate) queue
		$queue->addConnection(['driver' => 'sync']);

		// Persistent queue
		$connection = Capsule::schema()->getConnection();
		$resolver = new \Illuminate\Database\ConnectionResolver(['default' => $connection]);
		$manager = $queue->getQueueManager();
		$laravelContainer['queue'] = $queue->getQueueManager();
		$manager->addConnector('database', function () use ($resolver) {
			return new Illuminate\Queue\Connectors\DatabaseConnector($resolver);
		});
		$queue->addConnection([
			'driver' => 'database',
			'table' => 'jobs',
			'connection' => 'default',
			'queue' => 'default',
		], 'persistent');

		$queue->setAsGlobal();
	}

	/**
	 * @copydoc PKPApplication::get()
	 * @deprecated Use PKPApplication::get() instead.
	 */
	public static function getApplication() {
		return self::get();
	}

	/**
	 * Get the current application object
	 * @return Application
	 */
	public static function get() {
		return Registry::get('application');
	}

	/**
	 * Return a HTTP client implementation.
	 * @return \GuzzleHttp\Client
	 */
	public function getHttpClient() {
		$application = Application::get();
		$userAgent = $application->getName() . '/';
		if (Config::getVar('general', 'installed') && !defined('RUNNING_UPGRADE')) {
			$versionDao = DAORegistry::getDAO('VersionDAO');
			$currentVersion = $versionDao->getCurrentVersion();
			$userAgent .= $currentVersion->getVersionString();
		} else {
			$userAgent .= '?';
		}

		return new \GuzzleHttp\Client([
			'proxy' => [
				'http' => Config::getVar('proxy', 'http_proxy', null),
				'https' => Config::getVar('proxy', 'https_proxy', null),
			],
			'headers' => [
				'User-Agent' => $userAgent,
			],
		]);
	}

	/**
	 * Get the request implementation singleton
	 * @return Request
	 */
	public function getRequest() {
		$request =& Registry::get('request', true, null); // Ref req'd

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
	public function getDispatcher() {
		$dispatcher =& Registry::get('dispatcher', true, null); // Ref req'd
		if (is_null($dispatcher)) {
			import('lib.pkp.classes.core.Dispatcher');

			// Implicitly set dispatcher by ref in the registry
			$dispatcher = new Dispatcher();

			// Inject dependency
			$dispatcher->setApplication(PKPApplication::get());

			// Inject router configuration
			$dispatcher->addRouterName('lib.pkp.classes.core.APIRouter', ROUTE_API);
			$dispatcher->addRouterName('lib.pkp.classes.core.PKPComponentRouter', ROUTE_COMPONENT);
			$dispatcher->addRouterName('classes.core.PageRouter', ROUTE_PAGE);
		}

		return $dispatcher;
	}

	/**
	 * This executes the application by delegating the
	 * request to the dispatcher.
	 */
	public function execute() {
		// Dispatch the request to the correct handler
		$dispatcher = $this->getDispatcher();
		$dispatcher->dispatch($this->getRequest());
	}

	/**
	 * Get the symbolic name of this application
	 * @return string
	 */
	public static function getName() {
		return 'pkp-lib';
	}

	/**
	 * Get the locale key for the name of this application.
	 * @return string
	 */
	abstract public function getNameKey();

	/**
	 * Get the "context depth" of this application, i.e. the number of
	 * parts of the URL after index.php that represent the context of
	 * the current request (e.g. Journal [1], or Conference and
	 * Scheduled Conference [2]).
	 * @return int
	 */
	abstract public function getContextDepth();

	/**
	 * Get the list of the contexts available for this application
	 * i.e. the various parameters that are needed to represent the
	 * (e.g. array('journal') or array('conference', 'schedConf'))
	 * @return Array
	 */
	abstract public function getContextList();

	/**
	 * Get the URL to the XML descriptor for the current version of this
	 * application.
	 * @return string
	 */
	abstract public function getVersionDescriptorUrl();

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
	public function &getEnabledProducts($category = null, $mainContextId = null) {
		$contextDepth = $this->getContextDepth();
		if (is_null($mainContextId)) {
			$request = $this->getRequest();
			$router = $request->getRouter();

			// Try to identify the main context (e.g. journal, conference, press),
			// will be null if none found.
			$mainContext = $router->getContext($request, 1);
			if ($mainContext) $mainContextId = $mainContext->getId();
			else $mainContextId = CONTEXT_SITE;
		}
		if (!isset($this->enabledProducts[$mainContextId])) {
			$settingContext = array();
			if ($contextDepth > 0) {
				// Create the context for the setting if found
				$settingContext[] = $mainContextId;
				$settingContext = array_pad($settingContext, $contextDepth, 0);
				$settingContext = array_combine($this->getContextList(), $settingContext);
			}

			$versionDao = DAORegistry::getDAO('VersionDAO'); /* @var $versionDao VersionDAO */
			$this->enabledProducts[$mainContextId] = $versionDao->getCurrentProducts($settingContext);
		}

		if (is_null($category)) {
			return $this->enabledProducts[$mainContextId];
		} elseif (isset($this->enabledProducts[$mainContextId][$category])) {
			return $this->enabledProducts[$mainContextId][$category];
		} else {
			$returner = array();
			return $returner;
		}
	}

	/**
	 * Get the list of plugin categories for this application.
	 * @return array
	 */
	abstract public function getPluginCategories();

	/**
	 * Return the current version of the application.
	 * @return Version
	 */
	public function &getCurrentVersion() {
		$currentVersion =& $this->getEnabledProducts('core');
		assert(count($currentVersion)) == 1;
		return $currentVersion[$this->getName()];
	}

	/**
	 * Get the map of DAOName => full.class.Path for this application.
	 * @return array
	 */
	public function getDAOMap() {
		return array(
			'AccessKeyDAO' => 'lib.pkp.classes.security.AccessKeyDAO',
			'AnnouncementDAO' => 'lib.pkp.classes.announcement.AnnouncementDAO',
			'AnnouncementTypeDAO' => 'lib.pkp.classes.announcement.AnnouncementTypeDAO',
			'AuthSourceDAO' => 'lib.pkp.classes.security.AuthSourceDAO',
			'CategoryDAO' => 'lib.pkp.classes.context.CategoryDAO',
			'CitationDAO' => 'lib.pkp.classes.citation.CitationDAO',
			'ControlledVocabDAO' => 'lib.pkp.classes.controlledVocab.ControlledVocabDAO',
			'ControlledVocabEntryDAO' => 'lib.pkp.classes.controlledVocab.ControlledVocabEntryDAO',
			'ControlledVocabEntrySettingsDAO' => 'lib.pkp.classes.controlledVocab.ControlledVocabEntrySettingsDAO',
			'CountryDAO' => 'lib.pkp.classes.i18n.CountryDAO', // DEPRECATED
			'CurrencyDAO' => 'lib.pkp.classes.currency.CurrencyDAO', // DEPRECATED
			'DataObjectTombstoneDAO' => 'lib.pkp.classes.tombstone.DataObjectTombstoneDAO',
			'DataObjectTombstoneSettingsDAO' => 'lib.pkp.classes.tombstone.DataObjectTombstoneSettingsDAO',
			'EditDecisionDAO' => 'lib.pkp.classes.submission.EditDecisionDAO',
			'EmailTemplateDAO' => 'lib.pkp.classes.mail.EmailTemplateDAO',
			'FilterDAO' => 'lib.pkp.classes.filter.FilterDAO',
			'FilterGroupDAO' => 'lib.pkp.classes.filter.FilterGroupDAO',
			'GenreDAO' => 'lib.pkp.classes.submission.GenreDAO',
			'InterestDAO' => 'lib.pkp.classes.user.InterestDAO',
			'InterestEntryDAO' => 'lib.pkp.classes.user.InterestEntryDAO',
			'LanguageDAO' => 'lib.pkp.classes.language.LanguageDAO', // DEPRECATED
			'LibraryFileDAO' => 'lib.pkp.classes.context.LibraryFileDAO',
			'NavigationMenuDAO' => 'lib.pkp.classes.navigationMenu.NavigationMenuDAO',
			'NavigationMenuItemDAO' => 'lib.pkp.classes.navigationMenu.NavigationMenuItemDAO',
			'NavigationMenuItemAssignmentDAO' => 'lib.pkp.classes.navigationMenu.NavigationMenuItemAssignmentDAO',
			'NoteDAO' => 'lib.pkp.classes.note.NoteDAO',
			'NotificationDAO' => 'lib.pkp.classes.notification.NotificationDAO',
			'NotificationSettingsDAO' => 'lib.pkp.classes.notification.NotificationSettingsDAO',
			'NotificationSubscriptionSettingsDAO' => 'lib.pkp.classes.notification.NotificationSubscriptionSettingsDAO',
			'PluginGalleryDAO' => 'lib.pkp.classes.plugins.PluginGalleryDAO',
			'PluginSettingsDAO' => 'lib.pkp.classes.plugins.PluginSettingsDAO',
			'PublicationDAO' => 'classes.publication.PublicationDAO',
			'QueuedPaymentDAO' => 'lib.pkp.classes.payment.QueuedPaymentDAO',
			'ReviewAssignmentDAO' => 'lib.pkp.classes.submission.reviewAssignment.ReviewAssignmentDAO',
			'ReviewFilesDAO' => 'lib.pkp.classes.submission.ReviewFilesDAO',
			'ReviewFormDAO' => 'lib.pkp.classes.reviewForm.ReviewFormDAO',
			'ReviewFormElementDAO' => 'lib.pkp.classes.reviewForm.ReviewFormElementDAO',
			'ReviewFormResponseDAO' => 'lib.pkp.classes.reviewForm.ReviewFormResponseDAO',
			'ReviewRoundDAO' => 'lib.pkp.classes.submission.reviewRound.ReviewRoundDAO',
			'RoleDAO' => 'lib.pkp.classes.security.RoleDAO',
			'ScheduledTaskDAO' => 'lib.pkp.classes.scheduledTask.ScheduledTaskDAO',
			'SessionDAO' => 'lib.pkp.classes.session.SessionDAO',
			'SiteDAO' => 'lib.pkp.classes.site.SiteDAO',
			'StageAssignmentDAO' => 'lib.pkp.classes.stageAssignment.StageAssignmentDAO',
			'SubEditorsDAO' => 'lib.pkp.classes.context.SubEditorsDAO',
			'SubmissionAgencyDAO' => 'lib.pkp.classes.submission.SubmissionAgencyDAO',
			'SubmissionAgencyEntryDAO' => 'lib.pkp.classes.submission.SubmissionAgencyEntryDAO',
			'SubmissionCommentDAO' => 'lib.pkp.classes.submission.SubmissionCommentDAO',
			'SubmissionDisciplineDAO' => 'lib.pkp.classes.submission.SubmissionDisciplineDAO',
			'SubmissionDisciplineEntryDAO' => 'lib.pkp.classes.submission.SubmissionDisciplineEntryDAO',
			'SubmissionEmailLogDAO' => 'lib.pkp.classes.log.SubmissionEmailLogDAO',
			'SubmissionEventLogDAO' => 'lib.pkp.classes.log.SubmissionEventLogDAO',
			'SubmissionFileDAO' => 'classes.submission.SubmissionFileDAO',
			'SubmissionFileEventLogDAO' => 'lib.pkp.classes.log.SubmissionFileEventLogDAO',
			'QueryDAO' => 'lib.pkp.classes.query.QueryDAO',
			'SubmissionLanguageDAO' => 'lib.pkp.classes.submission.SubmissionLanguageDAO',
			'SubmissionLanguageEntryDAO' => 'lib.pkp.classes.submission.SubmissionLanguageEntryDAO',
			'SubmissionKeywordDAO' => 'lib.pkp.classes.submission.SubmissionKeywordDAO',
			'SubmissionKeywordEntryDAO' => 'lib.pkp.classes.submission.SubmissionKeywordEntryDAO',
			'SubmissionSubjectDAO' => 'lib.pkp.classes.submission.SubmissionSubjectDAO',
			'SubmissionSubjectEntryDAO' => 'lib.pkp.classes.submission.SubmissionSubjectEntryDAO',
			'TimeZoneDAO' => 'lib.pkp.classes.i18n.TimeZoneDAO',
			'TemporaryFileDAO' => 'lib.pkp.classes.file.TemporaryFileDAO',
			'UserGroupAssignmentDAO' => 'lib.pkp.classes.security.UserGroupAssignmentDAO',
			'UserDAO' => 'lib.pkp.classes.user.UserDAO',
			'UserGroupDAO' => 'lib.pkp.classes.security.UserGroupDAO',
			'UserSettingsDAO' => 'lib.pkp.classes.user.UserSettingsDAO',
			'UserStageAssignmentDAO' => 'lib.pkp.classes.user.UserStageAssignmentDAO',
			'VersionDAO' => 'lib.pkp.classes.site.VersionDAO',
			'ViewsDAO' => 'lib.pkp.classes.views.ViewsDAO',
			'WorkflowStageDAO' => 'lib.pkp.classes.workflow.WorkflowStageDAO',
			'XMLDAO' => 'lib.pkp.classes.db.XMLDAO',
		);
	}

	/**
	 * Return the fully-qualified (e.g. page.name.ClassNameDAO) name of the
	 * given DAO.
	 * @param $name string
	 * @return string
	 */
	public function getQualifiedDAOName($name) {
		$map =& Registry::get('daoMap', true, $this->getDAOMap()); // Ref req'd
		if (isset($map[$name])) return $map[$name];
		return null;
	}


	//
	// Statistics API
	//
	/**
	 * Return all metric types supported by this application.
	 *
	 * @return array An array of strings of supported metric type identifiers.
	 */
	public function getMetricTypes($withDisplayNames = false) {
		// Retrieve site-level report plugins.
		$reportPlugins = PluginRegistry::loadCategory('reports', true, CONTEXT_SITE);
		if (empty($reportPlugins)) return array();

		// Run through all report plugins and retrieve all supported metrics.
		$metricTypes = array();
		foreach ($reportPlugins as $reportPlugin) {
			/* @var $reportPlugin ReportPlugin */
			$pluginMetricTypes = $reportPlugin->getMetricTypes();
			if ($withDisplayNames) {
				foreach ($pluginMetricTypes as $metricType) {
					$metricTypes[$metricType] = $reportPlugin->getMetricDisplayType($metricType);
				}
			} else {
				$metricTypes = array_merge($metricTypes, $pluginMetricTypes);
			}
		}

		return $metricTypes;
	}

	/**
	 * Returns the currently configured default metric type for this site.
	 * If no specific metric type has been set for this site then null will
	 * be returned.
	 *
	 * @return null|string A metric type identifier or null if no default metric
	 *   type could be identified.
	 */
	public function getDefaultMetricType() {
		$request = $this->getRequest();
		$site = $request->getSite();
		if (!is_a($site, 'Site')) return null;
		$defaultMetricType = $site->getData('defaultMetricType');

		// Check whether the selected metric type is valid.
		$availableMetrics = $this->getMetricTypes();
		if (empty($defaultMetricType)) {
			// If there is only a single available metric then use it.
			if (count($availableMetrics) === 1) {
				$defaultMetricType = $availableMetrics[0];
			} else {
				return null;
			}
		} else {
			if (!in_array($defaultMetricType, $availableMetrics)) return null;
		}
		return $defaultMetricType;
	}

	/**
	 * Main entry point for PKP statistics reports.
	 *
	 * @see <https://pkp.sfu.ca/wiki/index.php/OJSdeStatisticsConcept#Input_and_Output_Formats_.28Aggregation.2C_Filters.2C_Metrics_Data.29>
	 * for a full specification of the input and output format of this method.
	 *
	 * @param $metricType null|string|array metrics selection
	 *   NB: If you want to use the default metric on journal level then you must
	 *   set $metricType = null and add an explicit filter on a single journal ID.
	 *   Otherwise the default site-level metric will be used.
	 * @param $columns string|array column (aggregation level) selection
	 * @param $filters array report-level filter selection
	 * @param $orderBy array order criteria
	 * @param $range null|DBResultRange paging specification
	 *
	 * @return null|array The selected data as a simple tabular result set or
	 *   null if the given parameter combination is not supported.
	 */
	public function getMetrics($metricType = null, $columns = array(), $filter = array(), $orderBy = array(), $range = null) {
		import('classes.statistics.StatisticsHelper');
		$statsHelper = new StatisticsHelper();

		// Check the parameter format.
		if (!(is_array($filter) && is_array($orderBy))) return null;

		// Check whether which context we are.
		$context = $statsHelper->getContext($filter);

		// Identify and canonicalize filtered metric types.
		$defaultSiteMetricType = $this->getDefaultMetricType();
		$siteMetricTypes = $this->getMetricTypes();
		$metricType = $statsHelper->canonicalizeMetricTypes($metricType, $context, $defaultSiteMetricType, $siteMetricTypes);
		if (!is_array($metricType)) return null;
		$metricTypeCount = count($metricType);

		// Canonicalize columns.
		if (is_scalar($columns)) $columns = array($columns);

		// The metric type dimension is not additive. This imposes two important
		// restrictions on valid report descriptions:
		// 1) We need at least one metric Type to be specified.
		if ($metricTypeCount === 0) return null;
		// 2) If we have multiple metrics then we have to force inclusion of
		// the metric type column to avoid aggregation over several metric types.
		if ($metricTypeCount > 1) {
			if (!in_array(STATISTICS_DIMENSION_METRIC_TYPE, $columns)) {
				array_push($columns, STATISTICS_DIMENSION_METRIC_TYPE);
			}
		}

		// Retrieve report plugins.
		if (is_a($context, 'Context')) {
			$contextId = $context->getId();
		} else {
			$contextId = CONTEXT_SITE;
		}
		$reportPlugins = PluginRegistry::loadCategory('reports', true, $contextId);
		if (empty($reportPlugins)) return null;

		// Run through all report plugins and try to retrieve the requested metrics.
		$report = array();
		foreach ($reportPlugins as $reportPlugin) {
			// Check whether one (or more) of the selected metrics can be
			// provided by this plugin.
			$availableMetrics = $reportPlugin->getMetricTypes();
			$availableMetrics = array_intersect($availableMetrics, $metricType);
			if (count($availableMetrics) == 0) continue;

			// Retrieve a (partial) report.
			$partialReport = $reportPlugin->getMetrics($availableMetrics, $columns, $filter, $orderBy, $range);

			// Merge the partial report with the main report.
			$report = array_merge($report, (array) $partialReport);

			// Remove the found metric types from the metric type array.
			$metricType = array_diff($metricType, $availableMetrics);
		}

		// Check whether we found all requested metric types.
		if (count($metricType) > 0) return null;

		// Return the report.
		return $report;
	}

	/**
	 * Return metric in the primary metric type
	 * for the passed associated object.
	 * @param $assocType int
	 * @param $assocId int
	 * @return int
	 */
	public function getPrimaryMetricByAssoc($assocType, $assocId) {
		$filter = array(
			STATISTICS_DIMENSION_ASSOC_ID => $assocId,
			STATISTICS_DIMENSION_ASSOC_TYPE => $assocType);

		$request = $this->getRequest();
		$router = $request->getRouter();
		$context = $router->getContext($request);
		if ($context) {
			$filter[STATISTICS_DIMENSION_CONTEXT_ID] = $context->getId();
		}

		$metric = $this->getMetrics(null, array(), $filter);
		if (is_array($metric)) {
			if (!is_null($metric[0][STATISTICS_METRIC])) return $metric[0][STATISTICS_METRIC];
		}

		return 0;
	}

	/**
	 * Get a mapping of license URL to license locale key for common
	 * creative commons licenses.
	 * @return array
	 */
	public static function getCCLicenseOptions() {
		return array(
			'https://creativecommons.org/licenses/by-nc-nd/4.0' => 'submission.license.cc.by-nc-nd4',
			'https://creativecommons.org/licenses/by-nc/4.0' => 'submission.license.cc.by-nc4',
			'https://creativecommons.org/licenses/by-nc-sa/4.0' => 'submission.license.cc.by-nc-sa4',
			'https://creativecommons.org/licenses/by-nd/4.0' => 'submission.license.cc.by-nd4',
			'https://creativecommons.org/licenses/by/4.0' => 'submission.license.cc.by4',
			'https://creativecommons.org/licenses/by-sa/4.0' => 'submission.license.cc.by-sa4'
		);
	}

	/**
	 * Get the Creative Commons license badge associated with a given
	 * license URL.
	 * @param $ccLicenseURL URL to creative commons license
	 * @param $locale string Optional locale to return badge in
	 * @return string HTML code for CC license
	 */
	public function getCCLicenseBadge($ccLicenseURL, $locale = null) {
		$licenseKeyMap = array(
			'|http[s]?://(www\.)?creativecommons.org/licenses/by-nc-nd/4.0[/]?|' => 'submission.license.cc.by-nc-nd4.footer',
			'|http[s]?://(www\.)?creativecommons.org/licenses/by-nc/4.0[/]?|' => 'submission.license.cc.by-nc4.footer',
			'|http[s]?://(www\.)?creativecommons.org/licenses/by-nc-sa/4.0[/]?|' => 'submission.license.cc.by-nc-sa4.footer',
			'|http[s]?://(www\.)?creativecommons.org/licenses/by-nd/4.0[/]?|' => 'submission.license.cc.by-nd4.footer',
			'|http[s]?://(www\.)?creativecommons.org/licenses/by/4.0[/]?|' => 'submission.license.cc.by4.footer',
			'|http[s]?://(www\.)?creativecommons.org/licenses/by-sa/4.0[/]?|' => 'submission.license.cc.by-sa4.footer',
			'|http[s]?://(www\.)?creativecommons.org/licenses/by-nc-nd/3.0[/]?|' => 'submission.license.cc.by-nc-nd3.footer',
			'|http[s]?://(www\.)?creativecommons.org/licenses/by-nc/3.0[/]?|' => 'submission.license.cc.by-nc3.footer',
			'|http[s]?://(www\.)?creativecommons.org/licenses/by-nc-sa/3.0[/]?|' => 'submission.license.cc.by-nc-sa3.footer',
			'|http[s]?://(www\.)?creativecommons.org/licenses/by-nd/3.0[/]?|' => 'submission.license.cc.by-nd3.footer',
			'|http[s]?://(www\.)?creativecommons.org/licenses/by/3.0[/]?|' => 'submission.license.cc.by3.footer',
			'|http[s]?://(www\.)?creativecommons.org/licenses/by-sa/3.0[/]?|' => 'submission.license.cc.by-sa3.footer'
		);
		if ($locale === null) $locale = AppLocale::getLocale();

		foreach($licenseKeyMap as $pattern => $key) {
			if (preg_match($pattern, $ccLicenseURL)) {
				PKPLocale::requireComponents(LOCALE_COMPONENT_PKP_SUBMISSION, $locale);
				return __($key, array(), $locale);
			}
		}
		return null;
	}

	/**
	 * Get a mapping of role keys and i18n key names.
	 * @param boolean $contextOnly If false, also returns site-level roles (Site admin)
	 * @param array|null $roleIds Only return role names of these IDs
	 * @return array
	 */
	public static function getRoleNames($contextOnly = false, $roleIds = null) {
		$siteRoleNames = array(ROLE_ID_SITE_ADMIN => 'user.role.siteAdmin');
		$appRoleNames = array(
			ROLE_ID_MANAGER => 'user.role.manager',
			ROLE_ID_SUB_EDITOR => 'user.role.subEditor',
			ROLE_ID_ASSISTANT => 'user.role.assistant',
			ROLE_ID_AUTHOR => 'user.role.author',
			ROLE_ID_REVIEWER => 'user.role.reviewer',
			ROLE_ID_READER => 'user.role.reader',
		);
		$roleNames = $contextOnly ? $appRoleNames : $siteRoleNames + $appRoleNames;
		if (!empty($roleIds)) $roleNames = array_intersect_key($roleNames, array_flip($roleIds));

		return $roleNames;
	}

	/**
	 * Get a mapping of roles allowed to access particular workflows
	 * @return array
	 */
	public static function getWorkflowTypeRoles() {
		return array(
			WORKFLOW_TYPE_EDITORIAL => array(ROLE_ID_SITE_ADMIN, ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT),
			WORKFLOW_TYPE_AUTHOR => array(ROLE_ID_SITE_ADMIN, ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_AUTHOR),
		);
	}

	/**
	 * Get the name of a workflow stage
	 *
	 * @param int $stageId One of the WORKFLOW_STAGE_* constants
	 * @return string
	 */
	public static function getWorkflowStageName($stageId) {
		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_SUBMISSION, LOCALE_COMPONENT_APP_SUBMISSION);
		switch ($stageId) {
			case WORKFLOW_STAGE_ID_SUBMISSION: return 'submission.submission';
			case WORKFLOW_STAGE_ID_INTERNAL_REVIEW: return 'workflow.review.internalReview';
			case WORKFLOW_STAGE_ID_EXTERNAL_REVIEW: return 'workflow.review.externalReview';
			case WORKFLOW_STAGE_ID_EDITING: return 'submission.editorial';
			case WORKFLOW_STAGE_ID_PRODUCTION: return 'submission.production';
		}
		throw new Exception('Name requested for an unrecognized stage id.');
	}

	/**
	 * Get the hex color (#000000) of a workflow stage
	 *
	 * @param int $stageId One of the WORKFLOW_STAGE_* constants
	 * @return string
	 */
	public static function getWorkflowStageColor($stageId) {
		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_SUBMISSION, LOCALE_COMPONENT_APP_SUBMISSION);
		switch ($stageId) {
			case WORKFLOW_STAGE_ID_SUBMISSION: return '#d00a0a';
			case WORKFLOW_STAGE_ID_INTERNAL_REVIEW: return '#e05c14';
			case WORKFLOW_STAGE_ID_EXTERNAL_REVIEW: return '#e08914';
			case WORKFLOW_STAGE_ID_EDITING: return '#006798';
			case WORKFLOW_STAGE_ID_PRODUCTION: return '#00b28d';
		}
		throw new Exception('Color requested for an unrecognized stage id.');
	}

	/**
	 * Get a human-readable version of the max file upload size
	 *
	 * @return string
	 */
	public static function getReadableMaxFileSize() {
		return strtolower(UPLOAD_MAX_FILESIZE) . 'b';
	}

	/**
	 * Convert the max upload size to an integer in MBs
	 *
	 * @return int
	 */
	public static function getIntMaxFileMBs() {
		$num = substr(UPLOAD_MAX_FILESIZE, 0, (strlen(UPLOAD_MAX_FILESIZE) - 1));
		$scale = strtolower(substr(UPLOAD_MAX_FILESIZE, -1));
		switch ($scale) {
			case 'g':
				$num = $num / 1024;
			case 'k':
				$num = $num * 1024;
		}
		return floor($num);
	}

	/**
	 * Get the supported metadata setting names for this application
	 *
	 * @return array
	 */
	public static function getMetadataFields() {
		return [
			'coverage',
			'languages',
			'rights',
			'source',
			'subjects',
			'type',
			'disciplines',
			'keywords',
			'agencies',
			'citations',
		];
	}
}

define('REALLY_BIG_NUMBER', 10000);
define('UPLOAD_MAX_FILESIZE', ini_get('upload_max_filesize'));

define('WORKFLOW_STAGE_ID_PUBLISHED', 0); // FIXME? See bug #6463.
define('WORKFLOW_STAGE_ID_SUBMISSION', 1);
define('WORKFLOW_STAGE_ID_INTERNAL_REVIEW', 2);
define('WORKFLOW_STAGE_ID_EXTERNAL_REVIEW', 3);
define('WORKFLOW_STAGE_ID_EDITING', 4);
define('WORKFLOW_STAGE_ID_PRODUCTION', 5);

/* TextArea insert tag variable types used to change their display when selected */
define('INSERT_TAG_VARIABLE_TYPE_PLAIN_TEXT', 'PLAIN_TEXT');

// To expose LISTBUILDER_SOURCE_TYPE_... constants via JS
import('lib.pkp.classes.controllers.listbuilder.ListbuilderHandler');

// To expose ORDER_CATEGORY_GRID_... constants via JS
import('lib.pkp.classes.controllers.grid.feature.OrderCategoryGridItemsFeature');
