<?php

/**
 * @file classes/core/PKPApplication.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPApplication
 *
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
use Illuminate\Database\MariaDbConnection;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\PostgresConnection;
use Illuminate\Support\Facades\DB;
use PKP\config\Config;
use PKP\context\Context;
use PKP\db\DAO;
use PKP\db\DAORegistry;
use PKP\facades\Locale;
use PKP\plugins\Hook;
use PKP\security\Role;
use PKP\site\Version;
use PKP\site\VersionDAO;
use PKP\submission\RepresentationDAOInterface;

interface iPKPApplicationInfoProvider
{
    /**
     * Get the top-level context DAO.
     *
     * @hook PKPApplication::execute::catch ['throwable' => $t]
     */
    public static function getContextDAO(): \PKP\context\ContextDAO;

    /**
     * Get the representation DAO.
     *
     * @hook PKPApplication::execute::catch ['throwable' => $t]
     */
    public static function getRepresentationDAO(): DAO|RepresentationDAOInterface;

    /**
     * Get a SubmissionSearchIndex instance.
     *
     * @hook PKPApplication::execute::catch ['throwable' => $t]
     */
    public static function getSubmissionSearchIndex(): \PKP\search\SubmissionSearchIndex;

    /**
     * Get a SubmissionSearchDAO instance.
     *
     * @hook PKPApplication::execute::catch ['throwable' => $t]
     */
    public static function getSubmissionSearchDAO(): \PKP\search\SubmissionSearchDAO;

    /**
     * Get the stages used by the application.
     *
     * @hook PKPApplication::execute::catch ['throwable' => $t]
     */
    public static function getApplicationStages(): array;

    /**
     * Get the file directory array map used by the application.
     * should return array('context' => ..., 'submission' => ...)
     *
     * @hook PKPApplication::execute::catch ['throwable' => $t]
     */
    public static function getFileDirectories(): array;

    /**
     * Returns the context type for this application.
     *
     * @hook PKPApplication::execute::catch ['throwable' => $t]
     */
    public static function getContextAssocType(): int;

    /**
     * Get the review workflow stages used by this application.
     *
     * @hook PKPApplication::execute::catch ['throwable' => $t]
     */
    public function getReviewStages(): array;

    /**
     * Define if the application has customizable reviewer recommendation functionality
     *
     * @hook PKPApplication::execute::catch ['throwable' => $t]
     */
    public function hasCustomizableReviewerRecommendation(): bool;
}

abstract class PKPApplication implements iPKPApplicationInfoProvider
{
    public const PHP_REQUIRED_VERSION = '8.2.0';

    // Constant used to distinguish between editorial and author workflows
    public const WORKFLOW_TYPE_EDITORIAL = 'editorial';
    public const WORKFLOW_TYPE_AUTHOR = 'author';

    public const API_VERSION = 'v1';

    public const ROUTE_COMPONENT = 'component';
    public const ROUTE_PAGE = 'page';
    public const ROUTE_API = 'api';

    public const SITE_CONTEXT_ID_ALL = -1;
    public const SITE_CONTEXT_ID = null;
    public const SITE_CONTEXT_PATH = 'index';
    /** @deprecated 3.5 Use Application::SITE_CONTEXT_ID, which had the value modified to null */
    public const CONTEXT_SITE = self::SITE_CONTEXT_ID;
    /** @deprecated 3.5 Use Application::SITE_CONTEXT_ID, which had the value modified to null */
    public const CONTEXT_ID_NONE = self::SITE_CONTEXT_ID;
    /** @deprecated 3.5 Use Application::SITE_CONTEXT_PATH, which had the value modified to "index" */
    public const CONTEXT_ID_ALL = self::SITE_CONTEXT_ID_ALL;

    public const ASSOC_TYPE_SITE = 0x0;
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

    public array $enabledProducts = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        if (!defined('PKP_STRICT_MODE')) {
            define('PKP_STRICT_MODE', (bool) Config::getVar('general', 'strict'));
            class_alias('\PKP\payment\QueuedPayment', '\QueuedPayment'); // QueuedPayment instances may be serialized
        }

        // Ensure that nobody registers for hooks that are no longer supported
        Hook::addUnsupportedHooks('API::_submissions::params', 'Template::Workflow::Publication', 'Template::Workflow', 'Workflow::Recommendations'); // pkp/pkp-lib#10766 Removed with new submission lists for 3.5.0
        Hook::addUnsupportedHooks('APIHandler::endpoints'); // pkp/pkp-lib#9434 Unavailable since stable-3_4_0; remove for 3.6.0 development branch
        Hook::addUnsupportedHooks('Mail::send', 'EditorAction::modifyDecisionOptions', 'EditorAction::recordDecision', 'Announcement::getProperties', 'Author::getProperties::values', 'EmailTemplate::getProperties', 'Galley::getProperties::values', 'Issue::getProperties::fullProperties', 'Issue::getProperties::summaryProperties', 'Issue::getProperties::values', 'Publication::getProperties', 'Section::getProperties::fullProperties', 'Section::getProperties::summaryProperties', 'Section::getProperties::values', 'Submission::getProperties::values', 'SubmissionFile::getProperties', 'User::getProperties::fullProperties', 'User::getProperties::reviewerSummaryProperties', 'User::getProperties::summaryProperties', 'User::getProperties::values', 'Announcement::getMany::queryBuilder', 'Announcement::getMany::queryObject', 'Author::getMany::queryBuilder', 'Author::getMany::queryObject', 'EmailTemplate::getMany::queryBuilder', 'EmailTemplate::getMany::queryObject::custom', 'EmailTemplate::getMany::queryObject::default', 'Galley::getMany::queryBuilder', 'Issue::getMany::queryBuilder', 'Publication::getMany::queryBuilder', 'Publication::getMany::queryObject', 'Stats::getOrderedObjects::queryBuilder', 'Stats::getRecords::queryBuilder', 'Stats::queryBuilder', 'Stats::queryObject', 'Submission::getMany::queryBuilder', 'Submission::getMany::queryObject', 'SubmissionFile::getMany::queryBuilder', 'SubmissionFile::getMany::queryObject', 'User::getMany::queryBuilder', 'User::getMany::queryObject', 'User::getReviewers::queryBuilder', 'CategoryDAO::_fromRow', 'IssueDAO::_fromRow', 'IssueDAO::_returnIssueFromRow', 'SectionDAO::_fromRow', 'UserDAO::_returnUserFromRow', 'UserDAO::_returnUserFromRowWithData', 'UserDAO::_returnUserFromRowWithReviewerStats', 'UserGroupDAO::_returnFromRow', 'ReviewerSubmissionDAO::_fromRow', 'API::stats::publication::abstract::params', 'API::stats::publication::galley::params', 'API::stats::publications::abstract::params', 'API::stats::publications::galley::params', 'PKPLocale::installLocale', 'PKPLocale::registerLocaleFile', 'PKPLocale::registerLocaleFile::isValidLocaleFile', 'PKPLocale::translate', 'API::submissions::files::params', 'ArticleGalleyDAO::getLocalizedGalleysByArticle', 'PluginGridHandler::plugin', 'PluginGridHandler::plugin', 'SubmissionFile::assignedFileStages', 'SubmissionHandler::saveSubmit'); // From the 3.4.0 Release Notebook; remove for 3.6.0 development branch
        Hook::addUnsupportedHooks('AcronPlugin::parseCronTab'); // pkp/pkp-lib#9678 Unavailable since stable-3_5_0;
        Hook::addUnsupportedHooks('Announcement::delete::before', 'Announcement::delete', 'Announcement::Collector'); // pkp/pkp-lib#10328 Unavailable since stable-3_5_0, use Eloquent Model events instead
        Hook::addUnsupportedHooks('UserGroup::delete::before', 'UserGroup::delete'); // unavailable since stable-3_6_0, use Eloquent Model events instead
        Hook::addUnsupportedHooks('CitationDAO::afterImportCitations'); // pkp/pkp-lib#11238 Renamed since stable-3_5_0
        // If not in strict mode, globally expose constants on this class.
        if (!PKP_STRICT_MODE) {
            foreach ([
                'ASSOC_TYPE_SITE',
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
        }

        ini_set('display_errors', Config::getVar('debug', 'display_errors', ini_get('display_errors')));

        if (!static::isInstalled() && !PKPSessionGuard::isSessionDisable()) {
            PKPSessionGuard::disableSession();
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
            $statement = match (true) {
                DB::connection() instanceof MySqlConnection,
                DB::connection() instanceof MariaDbConnection
                    => "SET time_zone = '{$offset}'",
                DB::connection() instanceof PostgresConnection
                    => "SET TIME ZONE INTERVAL '{$offset}' HOUR TO MINUTE"
            };
            DB::statement($statement);
        }
    }

    /**
     * Get the current application object
     */
    public static function get(): static
    {
        return Registry::get('application');
    }

    /**
     * Get the unique site ID
     */
    public function getUUID(): string
    {
        $site = $this->getRequest()->getSite();
        $uniqueSiteId = $site->getUniqueSiteID();
        if (!strlen((string) $uniqueSiteId)) {
            $uniqueSiteId = PKPString::generateUUID();
            $site->setUniqueSiteID($uniqueSiteId);
            /** @var \PKP\site\SiteDAO */
            $siteDao = DAORegistry::getDAO('SiteDAO');
            $siteDao->updateObject($site);
        }
        return $uniqueSiteId;
    }

    /**
     * Return a HTTP client implementation.
     */
    public function getHttpClient(): Client
    {
        if (PKPContainer::getInstance()->runningUnitTests()) {
            $client = Registry::get(\PKP\tests\PKPTestCase::MOCKED_GUZZLE_CLIENT_NAME);
            if ($client) {
                return $client;
            }
        }

        $application = Application::get();
        $userAgent = $application->getName() . '/';
        if (static::isInstalled() && !static::isUpgrading()) {
            $currentVersion = $application->getCurrentVersion();
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
            'allow_redirects' => ['strict' => true],
        ]);
    }

    /**
     * Get the request implementation singleton
     */
    public function getRequest(): Request
    {
        $request = &Registry::get('request', true, null); // Ref req'd

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
        $dispatcher = &Registry::get('dispatcher', true, null); // Ref req'd
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
     *
     * @hook PKPApplication::execute::catch ['throwable' => $t]
     */
    public function execute(): void
    {
        try {
            // Give the Dispatcher::dispatch::catch hook a chance to handle errors first
            try {
                // Dispatch the request to the correct handler
                $dispatcher = $this->getDispatcher();
                $dispatcher->dispatch($this->getRequest());
            } catch (\Throwable $t) {
                if (Hook::run('PKPApplication::execute::catch', ['throwable' => $t]) !== Hook::ABORT) {
                    // No hook handler took ownership; throw again
                    throw $t;
                }
            }
        } catch (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
            header('HTTP/1.0 404 Not Found');
            echo "<h1>404 Not Found</h1>\n";
            exit;
        } catch (\Symfony\Component\HttpKernel\Exception\GoneHttpException) {
            header('HTTP/1.0 410 Gone');
            echo "<h1>404 Not Found</h1>\n";
            exit;
        }
    }

    /**
     * Get the review workflow stages used by this application.
     */
    public function getReviewStages(): array
    {
        return [];
    }

    /**
     * Get the symbolic name of this application
     */
    public static function getName(): string
    {
        return 'pkp-lib';
    }

    /**
     * Get the locale key for the name of this application.
     */
    abstract public function getNameKey(): string;

    /**
     * Get the name of the context for this application
     */
    abstract public function getContextName(): string;

    /**
     * Get the URL to the XML descriptor for the current version of this
     * application.
     */
    abstract public function getVersionDescriptorUrl(): string;

    /**
     * Get the help URL for this application.
     */
    abstract public static function getHelpUrl(): string;

    /**
     * This function retrieves all enabled product versions once
     * from the database and caches the result for further
     * access.
     *
     * @param string $category
     * @param int $mainContextId Optional ID of the top-level context
     * (e.g. Journal, Conference, Press) to query for enabled products
     */
    public function getEnabledProducts($category = null, ?int $mainContextId = null): array
    {
        if ($mainContextId === null) {
            $request = $this->getRequest();
            $router = $request->getRouter();

            $mainContextId = $router->getContext($request)?->getId() ?? self::SITE_CONTEXT_ID;
        }
        $versionDao = DAORegistry::getDAO('VersionDAO'); /** @var \PKP\site\VersionDAO $versionDao */
        $enabledProducts = $this->enabledProducts[(int) $mainContextId] ??= $versionDao->getCurrentProducts($mainContextId);

        return $category ? ($enabledProducts[$category] ?? []) : $enabledProducts;
    }

    /**
     * Get the list of plugin categories for this application.
     */
    abstract public function getPluginCategories(): array;

    /**
     * Return the current version of the application.
     */
    public function getCurrentVersion(): Version
    {
        $currentVersion = $this->getEnabledProducts('core');
        return $currentVersion[$this->getName()];
    }

    /**
     * Get the map of DAOName => full.class.Path for this application.
     */
    public function getDAOMap(): array
    {
        return [
            'AnnouncementTypeDAO' => 'PKP\announcement\AnnouncementTypeDAO',
            'CitationDAO' => 'PKP\citation\CitationDAO',
            'DataObjectTombstoneDAO' => 'PKP\tombstone\DataObjectTombstoneDAO',
            'DataObjectTombstoneSettingsDAO' => 'PKP\tombstone\DataObjectTombstoneSettingsDAO',
            'FilterDAO' => 'PKP\filter\FilterDAO',
            'FilterGroupDAO' => 'PKP\filter\FilterGroupDAO',
            'LibraryFileDAO' => 'PKP\context\LibraryFileDAO',
            'NavigationMenuDAO' => 'PKP\navigationMenu\NavigationMenuDAO',
            'NavigationMenuItemDAO' => 'PKP\navigationMenu\NavigationMenuItemDAO',
            'NavigationMenuItemAssignmentDAO' => 'PKP\navigationMenu\NavigationMenuItemAssignmentDAO',
            'NotificationSettingsDAO' => 'PKP\notification\NotificationSettingsDAO',
            'NotificationSubscriptionSettingsDAO' => 'PKP\notification\NotificationSubscriptionSettingsDAO',
            'PluginGalleryDAO' => 'PKP\plugins\PluginGalleryDAO',
            'PluginSettingsDAO' => 'PKP\plugins\PluginSettingsDAO',
            'QueuedPaymentDAO' => 'PKP\payment\QueuedPaymentDAO',
            'ReviewFilesDAO' => 'PKP\submission\ReviewFilesDAO',
            'ReviewFormDAO' => 'PKP\reviewForm\ReviewFormDAO',
            'ReviewFormElementDAO' => 'PKP\reviewForm\ReviewFormElementDAO',
            'ReviewFormResponseDAO' => 'PKP\reviewForm\ReviewFormResponseDAO',
            'ReviewRoundDAO' => 'PKP\submission\reviewRound\ReviewRoundDAO',
            'RoleDAO' => 'PKP\security\RoleDAO',
            'SiteDAO' => 'PKP\site\SiteDAO',
            'SubEditorsDAO' => 'PKP\context\SubEditorsDAO',
            'SubmissionCommentDAO' => 'PKP\submission\SubmissionCommentDAO',
            'TemporaryFileDAO' => 'PKP\file\TemporaryFileDAO',
            'TemporaryInstitutionsDAO' => 'PKP\statistics\TemporaryInstitutionsDAO',
            'VersionDAO' => 'PKP\site\VersionDAO',
            'WorkflowStageDAO' => 'PKP\workflow\WorkflowStageDAO',
            'XMLDAO' => 'PKP\db\XMLDAO',
        ];
    }

    /**
     * Return the fully-qualified (e.g. page.name.ClassNameDAO) name of the
     * given DAO.
     */
    public function getQualifiedDAOName(string $name): ?string
    {
        $map = &Registry::get('daoMap', true, $this->getDAOMap()); // Ref req'd
        if (isset($map[$name])) {
            return $map[$name];
        }
        return null;
    }

    /**
     * Get a mapping of license URL to license locale key for common
     * creative commons licenses.
     */
    public static function getCCLicenseOptions(): array
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
     * @param ?string $ccLicenseURL URL to creative commons license
     * @param ?string $locale Optional locale to return badge in
     *
     * @return ?string HTML code for CC license
     */
    public function getCCLicenseBadge(?string $ccLicenseURL, ?string $locale = null): ?string
    {
        if (!$ccLicenseURL) {
            return null;
        }

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

        $locale ??= Locale::getLocale();
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
     */
    public static function getRoleNames(bool $contextOnly = false, ?array $roleIds = null): array
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
     */
    public static function getWorkflowTypeRoles(): array
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
     */
    public static function getWorkflowStageName(int $stageId): string
    {
        return match ($stageId) {
            WORKFLOW_STAGE_ID_SUBMISSION => 'submission.submission',
            WORKFLOW_STAGE_ID_INTERNAL_REVIEW => 'workflow.review.internalReview',
            WORKFLOW_STAGE_ID_EXTERNAL_REVIEW => 'workflow.review.externalReview',
            WORKFLOW_STAGE_ID_EDITING => 'submission.editorial',
            WORKFLOW_STAGE_ID_PRODUCTION => 'submission.production',
        };
    }

    /**
     * Get the hex color (#000000) of a workflow stage
     *
     * @param int $stageId One of the WORKFLOW_STAGE_* constants
     */
    public static function getWorkflowStageColor($stageId): string
    {
        return match ($stageId) {
            WORKFLOW_STAGE_ID_SUBMISSION => '#d00a0a',
            WORKFLOW_STAGE_ID_INTERNAL_REVIEW => '#e05c14',
            WORKFLOW_STAGE_ID_EXTERNAL_REVIEW => '#e08914',
            WORKFLOW_STAGE_ID_EDITING => '#006798',
            WORKFLOW_STAGE_ID_PRODUCTION => '#00b28d',
        };
    }

    /**
     * Get a human-readable version of the max file upload size
     */
    public static function getReadableMaxFileSize(): string
    {
        return strtoupper(UPLOAD_MAX_FILESIZE) . 'B';
    }

    /**
     * Convert the max upload size to an integer in MBs
     */
    public static function getIntMaxFileMBs(): int
    {
        $size = (int) UPLOAD_MAX_FILESIZE;
        $unit = strtolower(substr(UPLOAD_MAX_FILESIZE, -1));
        // No suffix fallbacks to "byte"
        match (ctype_alpha($unit) ? $unit : 'b') {
            'g' => $size <<= 10,
            'm' => null,
            'k' => $size >>= 10,
            'b' => $size >>= 20,
            default => error_log(sprintf('Invalid value for the PHP configuration upload_max_filesize "%s"', UPLOAD_MAX_FILESIZE))
        };
        return floor($size);
    }

    /**
     * Get the supported metadata setting names for this application
     */
    public static function getMetadataFields(): array
    {
        return [
            'coverage',
            'rights',
            'source',
            'subjects',
            'type',
            'disciplines',
            'keywords',
            'agencies',
            'citations',
            'dataAvailability',
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

    /**
     * Get the property name for a section id
     *
     * In OMP, the section is referred to as a series and the
     * property name is different.
     */
    public static function getSectionIdPropName(): string
    {
        return 'sectionId';
    }

    /**
     * Get the payment manager.
     */
    public function getPaymentManager(Context $context): \PKP\payment\PaymentManager
    {
        throw new \Exception('Payments not implemented.');
    }
}

define('REALLY_BIG_NUMBER', 10000);
define('UPLOAD_MAX_FILESIZE', trim(ini_get('upload_max_filesize')));

define('WORKFLOW_STAGE_ID_PUBLISHED', 0); // FIXME? See bug #6463.
define('WORKFLOW_STAGE_ID_SUBMISSION', 1);
define('WORKFLOW_STAGE_ID_INTERNAL_REVIEW', 2);
define('WORKFLOW_STAGE_ID_EXTERNAL_REVIEW', 3);
define('WORKFLOW_STAGE_ID_EDITING', 4);
define('WORKFLOW_STAGE_ID_PRODUCTION', 5);

/* TextArea insert tag variable types used to change their display when selected */
define('INSERT_TAG_VARIABLE_TYPE_PLAIN_TEXT', 'PLAIN_TEXT');
