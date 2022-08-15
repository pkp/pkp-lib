<?php

/**
 * @file classes/core/PKPApplication.php
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

namespace PKP\core;

use APP\core\Application;

use APP\core\Request;
use DateTime;
use DateTimeZone;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\MySqlConnection;
use Illuminate\Support\Facades\DB;
use PKP\config\Config;
use PKP\db\DAORegistry;
use PKP\facades\Locale;
use PKP\security\Role;
use PKP\session\SessionManager;
use PKP\submission\RepresentationDAOInterface;

interface iPKPApplicationInfoProvider
{
    /**
     * Get the top-level context DAO.
     */
    public static function getContextDAO();

    /**
     * Get the section DAO.
     *
     * @return DAO
     */
    public static function getSectionDAO();

    /**
     * Get the representation DAO.
     */
    public static function getRepresentationDAO(): RepresentationDAOInterface;

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

abstract class PKPApplication implements iPKPApplicationInfoProvider
{
    public const PHP_REQUIRED_VERSION = '8.0.2';

    // Constant used to distinguish between editorial and author workflows
    public const WORKFLOW_TYPE_EDITORIAL = 'editorial';
    public const WORKFLOW_TYPE_AUTHOR = 'author';

    public const API_VERSION = 'v1';

    public const ROUTE_COMPONENT = 'component';
    public const ROUTE_PAGE = 'page';
    public const ROUTE_API = 'api';

    public const CONTEXT_SITE = 0;
    public const CONTEXT_ID_NONE = 0;
    public const CONTEXT_ID_ALL = '_';
    public const REVIEW_ROUND_NONE = 0;

    public const ASSOC_TYPE_PRODUCTION_ASSIGNMENT = 0x0000202;
    public const ASSOC_TYPE_SUBMISSION_FILE = 0x0000203;
    public const ASSOC_TYPE_REVIEW_RESPONSE = 0x0000204;
    public const ASSOC_TYPE_REVIEW_ASSIGNMENT = 0x0000205;
    public const ASSOC_TYPE_SUBMISSION_EMAIL_LOG_ENTRY = 0x0000206;
    public const ASSOC_TYPE_WORKFLOW_STAGE = 0x0000207;
    public const ASSOC_TYPE_NOTE = 0x0000208;
    public const ASSOC_TYPE_REPRESENTATION = 0x0000209;
    public const ASSOC_TYPE_ANNOUNCEMENT = 0x000020A;
    public const ASSOC_TYPE_REVIEW_ROUND = 0x000020B;
    public const ASSOC_TYPE_SUBMISSION_FILES = 0x000020F;
    public const ASSOC_TYPE_PLUGIN = 0x0000211;
    public const ASSOC_TYPE_SECTION = 0x0000212;
    public const ASSOC_TYPE_CATEGORY = 0x000020D;
    public const ASSOC_TYPE_USER = 0x0001000; // This value used because of bug #6068
    public const ASSOC_TYPE_USER_GROUP = 0x0100002;
    public const ASSOC_TYPE_CITATION = 0x0100003;
    public const ASSOC_TYPE_AUTHOR = 0x0100004;
    public const ASSOC_TYPE_EDITOR = 0x0100005;
    public const ASSOC_TYPE_USER_ROLES = 0x0100007;
    public const ASSOC_TYPE_ACCESSIBLE_WORKFLOW_STAGES = 0x0100008;
    public const ASSOC_TYPE_SUBMISSION = 0x0100009;
    public const ASSOC_TYPE_QUERY = 0x010000a;
    public const ASSOC_TYPE_QUEUED_PAYMENT = 0x010000b;
    public const ASSOC_TYPE_PUBLICATION = 0x010000c;
    public const ASSOC_TYPE_ACCESSIBLE_FILE_STAGES = 0x010000d;
    public const ASSOC_TYPE_NONE = 0x010000e;
    public const ASSOC_TYPE_DECISION_TYPE = 0x010000f;

    // Constant used in UsageStats for submission files that are not full texts
    public const ASSOC_TYPE_SUBMISSION_FILE_COUNTER_OTHER = 0x0000213;

    public $enabledProducts = [];
    public $allProducts;

    /**
     * Constructor
     */
    public function __construct()
    {
        // Seed random number generator
        mt_srand(intval(((float) microtime()) * 1000000));

        if (!defined('PKP_STRICT_MODE')) {
            define('PKP_STRICT_MODE', (bool) Config::getVar('general', 'strict'));
            class_alias('\PKP\config\Config', '\Config');
            class_alias('\PKP\core\Registry', '\Registry');
            class_alias('\PKP\core\Core', '\Core');
            class_alias('\PKP\cache\CacheManager', '\CacheManager');
            class_alias('\PKP\handler\PKPHandler', '\PKPHandler');
            class_alias('\PKP\payment\QueuedPayment', '\QueuedPayment'); // QueuedPayment instances may be serialized
        }

        // If not in strict mode, globally expose constants on this class.
        if (!PKP_STRICT_MODE) {
            foreach ([
                'WORKFLOW_TYPE_EDITORIAL', 'WORKFLOW_TYPE_AUTHOR', 'PHP_REQUIRED_VERSION',
                'API_VERSION',
                'ROUTE_COMPONENT', 'ROUTE_PAGE', 'ROUTE_API',
                'CONTEXT_SITE', 'CONTEXT_ID_NONE', 'CONTEXT_ID_ALL', 'REVIEW_ROUND_NONE',

                'ASSOC_TYPE_PRODUCTION_ASSIGNMENT',
                'ASSOC_TYPE_SUBMISSION_FILE',
                'ASSOC_TYPE_REVIEW_RESPONSE',
                'ASSOC_TYPE_REVIEW_ASSIGNMENT',
                'ASSOC_TYPE_SUBMISSION_EMAIL_LOG_ENTRY',
                'ASSOC_TYPE_WORKFLOW_STAGE',
                'ASSOC_TYPE_NOTE',
                'ASSOC_TYPE_REPRESENTATION',
                'ASSOC_TYPE_ANNOUNCEMENT',
                'ASSOC_TYPE_REVIEW_ROUND',
                'ASSOC_TYPE_SUBMISSION_FILES',
                'ASSOC_TYPE_PLUGIN',
                'ASSOC_TYPE_SECTION',
                'ASSOC_TYPE_CATEGORY',
                'ASSOC_TYPE_USER',
                'ASSOC_TYPE_USER_GROUP',
                'ASSOC_TYPE_CITATION',
                'ASSOC_TYPE_AUTHOR',
                'ASSOC_TYPE_EDITOR',
                'ASSOC_TYPE_USER_ROLES',
                'ASSOC_TYPE_ACCESSIBLE_WORKFLOW_STAGES',
                'ASSOC_TYPE_SUBMISSION',
                'ASSOC_TYPE_QUERY',
                'ASSOC_TYPE_QUEUED_PAYMENT',
                'ASSOC_TYPE_PUBLICATION',
                'ASSOC_TYPE_ACCESSIBLE_FILE_STAGES',
                'ASSOC_TYPE_SUBMISSION_FILE_COUNTER_OTHER',
            ] as $constantName) {
                if (!defined($constantName)) {
                    define($constantName, constant('self::' . $constantName));
                }
            }
            if (!class_exists('\PKPApplication')) {
                class_alias('\PKP\core\PKPApplication', '\PKPApplication');
            }
        }

        ini_set('display_errors', Config::getVar('debug', 'display_errors', ini_get('display_errors')));
        if (!static::isInstalled()) {
            SessionManager::disable();
        }

        Registry::set('application', $this);

        $microTime = Core::microtime();
        Registry::set('system.debug.startTime', $microTime);

        $this->initializeLaravelContainer();
        PKPString::initialize();

        // Load default locale files
        Locale::registerPath(BASE_SYS_DIR . '/lib/pkp/locale');

        if (static::isInstalled() && !static::isUpgrading()) {
            $versionDao = DAORegistry::getDAO('VersionDAO'); /** @var VersionDAO $versionDao */
            $appVersion = $versionDao->getCurrentVersion()->getVersionString();
            Registry::set('appVersion', $appVersion);
        }
    }

    /**
     * Initialize Laravel container and register service providers
     */
    public function initializeLaravelContainer(): void
    {
        // Ensure multiple calls to this function don't cause trouble
        static $containerInitialized = false;
        if ($containerInitialized) {
            return;
        }

        $containerInitialized = true;

        // Initialize Laravel's container and set it globally
        $laravelContainer = new PKPContainer();
        $laravelContainer->registerConfiguredProviders();

        $this->initializeTimeZone();

        if (Config::getVar('database', 'debug')) {
            DB::listen(fn (QueryExecuted $query) => error_log("Database query\n{$query->sql}\n" . json_encode($query->bindings)));
        }
    }

    /**
     * Setup the internal time zone for the database and PHP.
     */
    protected function initializeTimeZone(): void
    {
        $timeZone = null;
        // Loads the time zone from the configuration file
        if ($setting = Config::getVar('general', 'time_zone')) {
            try {
                $timeZone = (new DateTimeZone($setting))->getName();
            } catch (Exception $e) {
                $setting = strtolower($setting);
                foreach (DateTimeZone::listIdentifiers() as $identifier) {
                    // Backward compatibility identification
                    if ($setting == strtolower(preg_replace(['/^\w+\//', '/_/'], ['', ' '], $identifier))) {
                        $timeZone = $identifier;
                        break;
                    }
                }
            }
        }
        // Set the default timezone
        date_default_timezone_set($timeZone ?: ini_get('date.timezone') ?: 'UTC');

        // Synchronize the database time zone
        if (Application::isInstalled()) {
            // Retrieve the current offset
            $offset = (new DateTime())->format('P');
            $statement = DB::connection() instanceof MySqlConnection
                ? "SET time_zone = '${offset}'"
                : "SET TIME ZONE INTERVAL '${offset}' HOUR TO MINUTE";
            DB::statement($statement);
        }
    }

    /**
     * @copydoc PKPApplication::get()
     *
     * @deprecated Use PKPApplication::get() instead.
     */
    public static function getApplication()
    {
        return self::get();
    }

    /**
     * Get the current application object
     *
     * @return Application
     */
    public static function get()
    {
        return Registry::get('application');
    }

    /**
     * Return a HTTP client implementation.
     *
     * @return Client
     */
    public function getHttpClient()
    {
        $application = Application::get();
        $userAgent = $application->getName() . '/';
        if (static::isInstalled() && !static::isUpgrading()) {
            /** @var \PKP\site\VersionDAO */
            $versionDao = DAORegistry::getDAO('VersionDAO');
            $currentVersion = $versionDao->getCurrentVersion();
            $userAgent .= $currentVersion->getVersionString();
        } else {
            $userAgent .= '?';
        }

        return new Client([
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
     *
     * @return Request
     */
    public function getRequest()
    {
        $request = & Registry::get('request', true, null); // Ref req'd

        if (is_null($request)) {
            // Implicitly set request by ref in the registry
            $request = new Request();
        }

        return $request;
    }

    /**
     * Get the dispatcher implementation singleton
     *
     * @return Dispatcher
     */
    public function getDispatcher()
    {
        $dispatcher = & Registry::get('dispatcher', true, null); // Ref req'd
        if (is_null($dispatcher)) {
            // Implicitly set dispatcher by ref in the registry
            $dispatcher = new Dispatcher();

            // Inject dependency
            $dispatcher->setApplication(PKPApplication::get());

            // Inject router configuration
            $dispatcher->addRouterName('\PKP\core\APIRouter', self::ROUTE_API);
            $dispatcher->addRouterName('\PKP\core\PKPComponentRouter', self::ROUTE_COMPONENT);
            $dispatcher->addRouterName('\APP\core\PageRouter', self::ROUTE_PAGE);
        }

        return $dispatcher;
    }

    /**
     * This executes the application by delegating the
     * request to the dispatcher.
     */
    public function execute()
    {
        // Dispatch the request to the correct handler
        $dispatcher = $this->getDispatcher();
        $dispatcher->dispatch($this->getRequest());
    }

    /**
     * Get the symbolic name of this application
     *
     * @return string
     */
    public static function getName()
    {
        return 'pkp-lib';
    }

    /**
     * Get the locale key for the name of this application.
     *
     * @return string
     */
    abstract public function getNameKey();

    /**
     * Get the "context depth" of this application, i.e. the number of
     * parts of the URL after index.php that represent the context of
     * the current request (e.g. Journal [1], or Conference and
     * Scheduled Conference [2]).
     *
     * @return int
     */
    abstract public function getContextDepth();

    /**
     * Get the list of the contexts available for this application
     * i.e. the various parameters that are needed to represent the
     * (e.g. array('journal') or array('conference', 'schedConf'))
     *
     * @return array
     */
    abstract public function getContextList();

    /**
     * Get the URL to the XML descriptor for the current version of this
     * application.
     *
     * @return string
     */
    abstract public function getVersionDescriptorUrl();

    /**
     * This function retrieves all enabled product versions once
     * from the database and caches the result for further
     * access.
     *
     * @param string $category
     * @param int $mainContextId Optional ID of the top-level context
     * (e.g. Journal, Conference, Press) to query for enabled products
     *
     * @return array
     */
    public function &getEnabledProducts($category = null, $mainContextId = null)
    {
        $contextDepth = $this->getContextDepth();
        if (is_null($mainContextId)) {
            $request = $this->getRequest();
            $router = $request->getRouter();

            // Try to identify the main context (e.g. journal, conference, press),
            // will be null if none found.
            $mainContext = $router->getContext($request, 1);
            if ($mainContext) {
                $mainContextId = $mainContext->getId();
            } else {
                $mainContextId = self::CONTEXT_SITE;
            }
        }
        if (!isset($this->enabledProducts[$mainContextId])) {
            $settingContext = [];
            if ($contextDepth > 0) {
                // Create the context for the setting if found
                $settingContext[] = $mainContextId;
                $settingContext = array_pad($settingContext, $contextDepth, 0);
                $settingContext = array_combine($this->getContextList(), $settingContext);
            }

            $versionDao = DAORegistry::getDAO('VersionDAO'); /** @var \PKP\site\VersionDAO $versionDao */
            $this->enabledProducts[$mainContextId] = $versionDao->getCurrentProducts($settingContext);
        }

        if (is_null($category)) {
            return $this->enabledProducts[$mainContextId];
        } elseif (isset($this->enabledProducts[$mainContextId][$category])) {
            return $this->enabledProducts[$mainContextId][$category];
        } else {
            $returner = [];
            return $returner;
        }
    }

    /**
     * Get the list of plugin categories for this application.
     *
     * @return array
     */
    abstract public function getPluginCategories();

    /**
     * Return the current version of the application.
     *
     * @return Version
     */
    public function &getCurrentVersion()
    {
        $currentVersion = & $this->getEnabledProducts('core');
        assert(count($currentVersion)) == 1;
        return $currentVersion[$this->getName()];
    }

    /**
     * Get the map of DAOName => full.class.Path for this application.
     *
     * @return array
     */
    public function getDAOMap()
    {
        return [
            'AccessKeyDAO' => 'PKP\security\AccessKeyDAO',
            'AnnouncementDAO' => 'PKP\announcement\AnnouncementDAO',
            'AnnouncementTypeDAO' => 'PKP\announcement\AnnouncementTypeDAO',
            'CitationDAO' => 'PKP\citation\CitationDAO',
            'ControlledVocabDAO' => 'PKP\controlledVocab\ControlledVocabDAO',
            'ControlledVocabEntryDAO' => 'PKP\controlledVocab\ControlledVocabEntryDAO',
            'DataObjectTombstoneDAO' => 'PKP\tombstone\DataObjectTombstoneDAO',
            'DataObjectTombstoneSettingsDAO' => 'PKP\tombstone\DataObjectTombstoneSettingsDAO',
            'FilterDAO' => 'PKP\filter\FilterDAO',
            'FilterGroupDAO' => 'PKP\filter\FilterGroupDAO',
            'GenreDAO' => 'PKP\submission\GenreDAO',
            'InterestDAO' => 'PKP\user\InterestDAO',
            'InterestEntryDAO' => 'PKP\user\InterestEntryDAO',
            'LibraryFileDAO' => 'PKP\context\LibraryFileDAO',
            'NavigationMenuDAO' => 'PKP\navigationMenu\NavigationMenuDAO',
            'NavigationMenuItemDAO' => 'PKP\navigationMenu\NavigationMenuItemDAO',
            'NavigationMenuItemAssignmentDAO' => 'PKP\navigationMenu\NavigationMenuItemAssignmentDAO',
            'NoteDAO' => 'PKP\note\NoteDAO',
            'NotificationDAO' => 'PKP\notification\NotificationDAO',
            'NotificationSettingsDAO' => 'PKP\notification\NotificationSettingsDAO',
            'NotificationSubscriptionSettingsDAO' => 'PKP\notification\NotificationSubscriptionSettingsDAO',
            'PluginGalleryDAO' => 'PKP\plugins\PluginGalleryDAO',
            'PluginSettingsDAO' => 'PKP\plugins\PluginSettingsDAO',
            'PublicationDAO' => 'APP\publication\PublicationDAO',
            'QueuedPaymentDAO' => 'PKP\payment\QueuedPaymentDAO',
            'ReviewAssignmentDAO' => 'PKP\submission\reviewAssignment\ReviewAssignmentDAO',
            'ReviewFilesDAO' => 'PKP\submission\ReviewFilesDAO',
            'ReviewFormDAO' => 'PKP\reviewForm\ReviewFormDAO',
            'ReviewFormElementDAO' => 'PKP\reviewForm\ReviewFormElementDAO',
            'ReviewFormResponseDAO' => 'PKP\reviewForm\ReviewFormResponseDAO',
            'ReviewRoundDAO' => 'PKP\submission\reviewRound\ReviewRoundDAO',
            'RoleDAO' => 'PKP\security\RoleDAO',
            'ScheduledTaskDAO' => 'PKP\scheduledTask\ScheduledTaskDAO',
            'SessionDAO' => 'PKP\session\SessionDAO',
            'SiteDAO' => 'PKP\site\SiteDAO',
            'StageAssignmentDAO' => 'PKP\stageAssignment\StageAssignmentDAO',
            'SubEditorsDAO' => 'PKP\context\SubEditorsDAO',
            'SubmissionAgencyDAO' => 'PKP\submission\SubmissionAgencyDAO',
            'SubmissionAgencyEntryDAO' => 'PKP\submission\SubmissionAgencyEntryDAO',
            'SubmissionCommentDAO' => 'PKP\submission\SubmissionCommentDAO',
            'SubmissionDisciplineDAO' => 'PKP\submission\SubmissionDisciplineDAO',
            'SubmissionDisciplineEntryDAO' => 'PKP\submission\SubmissionDisciplineEntryDAO',
            'SubmissionEmailLogDAO' => 'PKP\log\SubmissionEmailLogDAO',
            'SubmissionEventLogDAO' => 'PKP\log\SubmissionEventLogDAO',
            'SubmissionFileEventLogDAO' => 'PKP\log\SubmissionFileEventLogDAO',
            'QueryDAO' => 'PKP\query\QueryDAO',
            'SubmissionLanguageDAO' => 'PKP\submission\SubmissionLanguageDAO',
            'SubmissionLanguageEntryDAO' => 'PKP\submission\SubmissionLanguageEntryDAO',
            'SubmissionKeywordDAO' => 'PKP\submission\SubmissionKeywordDAO',
            'SubmissionKeywordEntryDAO' => 'PKP\submission\SubmissionKeywordEntryDAO',
            'SubmissionSubjectDAO' => 'PKP\submission\SubmissionSubjectDAO',
            'SubmissionSubjectEntryDAO' => 'PKP\submission\SubmissionSubjectEntryDAO',
            'TemporaryFileDAO' => 'PKP\file\TemporaryFileDAO',
            'TemporaryInstitutionsDAO' => 'PKP\statistics\TemporaryInstitutionsDAO',
            'UserGroupAssignmentDAO' => 'PKP\security\UserGroupAssignmentDAO',
            'UserGroupDAO' => 'PKP\security\UserGroupDAO',
            'UserStageAssignmentDAO' => 'PKP\user\UserStageAssignmentDAO',
            'VersionDAO' => 'PKP\site\VersionDAO',
            'ViewsDAO' => 'PKP\views\ViewsDAO',
            'WorkflowStageDAO' => 'PKP\workflow\WorkflowStageDAO',
            'XMLDAO' => 'PKP\db\XMLDAO',
        ];
    }

    /**
     * Return the fully-qualified (e.g. page.name.ClassNameDAO) name of the
     * given DAO.
     *
     * @param string $name
     *
     * @return string
     */
    public function getQualifiedDAOName($name)
    {
        $map = & Registry::get('daoMap', true, $this->getDAOMap()); // Ref req'd
        if (isset($map[$name])) {
            return $map[$name];
        }
        return null;
    }

    /**
     * Get a mapping of license URL to license locale key for common
     * creative commons licenses.
     *
     * @return array
     */
    public static function getCCLicenseOptions()
    {
        return [
            'https://creativecommons.org/licenses/by-nc-nd/4.0' => 'submission.license.cc.by-nc-nd4',
            'https://creativecommons.org/licenses/by-nc/4.0' => 'submission.license.cc.by-nc4',
            'https://creativecommons.org/licenses/by-nc-sa/4.0' => 'submission.license.cc.by-nc-sa4',
            'https://creativecommons.org/licenses/by-nd/4.0' => 'submission.license.cc.by-nd4',
            'https://creativecommons.org/licenses/by/4.0' => 'submission.license.cc.by4',
            'https://creativecommons.org/licenses/by-sa/4.0' => 'submission.license.cc.by-sa4'
        ];
    }

    /**
     * Get the Creative Commons license badge associated with a given
     * license URL.
     *
     * @param string $ccLicenseURL URL to creative commons license
     * @param string $locale Optional locale to return badge in
     *
     * @return string HTML code for CC license
     */
    public function getCCLicenseBadge($ccLicenseURL, $locale = null)
    {
        $licenseKeyMap = [
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
        ];
        if ($locale === null) {
            $locale = Locale::getLocale();
        }

        foreach ($licenseKeyMap as $pattern => $key) {
            if (preg_match($pattern, $ccLicenseURL)) {
                return __($key, [], $locale);
            }
        }
        return null;
    }

    /**
     * Get a mapping of role keys and i18n key names.
     *
     * @param bool $contextOnly If false, also returns site-level roles (Site admin)
     * @param array|null $roleIds Only return role names of these IDs
     *
     * @return array
     */
    public static function getRoleNames($contextOnly = false, $roleIds = null)
    {
        $siteRoleNames = [Role::ROLE_ID_SITE_ADMIN => 'user.role.siteAdmin'];
        $appRoleNames = [
            Role::ROLE_ID_MANAGER => 'user.role.manager',
            Role::ROLE_ID_SUB_EDITOR => 'user.role.subEditor',
            Role::ROLE_ID_ASSISTANT => 'user.role.assistant',
            Role::ROLE_ID_AUTHOR => 'user.role.author',
            Role::ROLE_ID_REVIEWER => 'user.role.reviewer',
            Role::ROLE_ID_READER => 'user.role.reader',
        ];
        $roleNames = $contextOnly ? $appRoleNames : $siteRoleNames + $appRoleNames;
        if (!empty($roleIds)) {
            $roleNames = array_intersect_key($roleNames, array_flip($roleIds));
        }

        return $roleNames;
    }

    /**
     * Get a mapping of roles allowed to access particular workflows
     *
     * @return array
     */
    public static function getWorkflowTypeRoles()
    {
        return [
            self::WORKFLOW_TYPE_EDITORIAL => [Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT],
            self::WORKFLOW_TYPE_AUTHOR => [Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_AUTHOR],
        ];
    }

    /**
     * Get the name of a workflow stage
     *
     * @param int $stageId One of the WORKFLOW_STAGE_* constants
     *
     * @return string
     */
    public static function getWorkflowStageName($stageId)
    {
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
     *
     * @return string
     */
    public static function getWorkflowStageColor($stageId)
    {
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
    public static function getReadableMaxFileSize()
    {
        return strtolower(UPLOAD_MAX_FILESIZE) . 'b';
    }

    /**
     * Convert the max upload size to an integer in MBs
     *
     * @return int
     */
    public static function getIntMaxFileMBs()
    {
        $num = substr(UPLOAD_MAX_FILESIZE, 0, (strlen(UPLOAD_MAX_FILESIZE) - 1));
        $scale = strtolower(substr(UPLOAD_MAX_FILESIZE, -1));
        switch ($scale) {
            case 'g':
                $num = $num * 1024;
                break;
            case 'k':
                $num = $num / 1024;
                break;
            case 'm':
                break; // Is set as MB already, do nothing.
            default:
                // No suffix, so this is "b" (Byte)
                // Reset $num to the limit without cut the last digit
                $num = UPLOAD_MAX_FILESIZE / 1024 / 1024;
                break;
        }
        return floor($num);
    }

    /**
     * Get the supported metadata setting names for this application
     *
     * @return array
     */
    public static function getMetadataFields()
    {
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

    /**
     * Retrieves whether the application is installed
     */
    public static function isInstalled(): bool
    {
        return !!Config::getVar('general', 'installed');
    }

    /**
     * Retrieves whether the application is running an upgrade
     */
    public static function isUpgrading(): bool
    {
        return defined('RUNNING_UPGRADE');
    }

    /**
     * Retrieves whether the application is under maintenance (not installed or being upgraded)
     */
    public static function isUnderMaintenance(): bool
    {
        return !static::isInstalled() || static::isUpgrading();
    }

    /**
     * Signals the application is undergoing an upgrade
     */
    public static function upgrade(): void
    {
        // Constant kept for backwards compatibility
        if (!defined('RUNNING_UPGRADE')) {
            define('RUNNING_UPGRADE', true);
        }
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
