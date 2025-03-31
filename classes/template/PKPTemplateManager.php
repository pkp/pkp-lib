<?php

/**
 * @defgroup template Template
 * Implements template management.
 */

/**
 * @file classes/template/PKPTemplateManager.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPTemplateManager
 *
 * @ingroup template
 *
 * @brief Class for accessing the underlying template engine.
 * Currently integrated with Smarty (from http://smarty.php.net/).
 */

namespace PKP\template;

use APP\core\Application;
use APP\core\PageRouter;
use APP\core\Request;
use APP\core\Services;
use APP\facades\Repo;
use APP\file\PublicFileManager;
use APP\notification\Notification;
use APP\submission\Submission;
use APP\template\TemplateManager;
use Exception;
use Less_Parser;
use PKP\cache\CacheManager;
use PKP\config\Config;
use PKP\context\Context;
use PKP\controllers\listbuilder\ListbuilderHandler;
use PKP\core\Core;
use PKP\core\JSONMessage;
use PKP\core\PKPApplication;
use PKP\core\PKPRequest;
use PKP\core\PKPString;
use PKP\core\Registry;
use PKP\db\DAORegistry;
use PKP\facades\Locale;
use PKP\file\FileManager;
use PKP\form\FormBuilderVocabulary;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\NullAction;
use PKP\navigationMenu\NavigationMenuDAO;
use PKP\notification\NotificationDAO;
use PKP\plugins\Hook;
use PKP\plugins\PluginRegistry;
use PKP\plugins\ThemePlugin;
use PKP\security\Role;
use PKP\security\Validation;
use PKP\session\SessionManager;
use PKP\site\VersionDAO;
use PKP\submission\GenreDAO;
use Smarty;
use Smarty_Internal_Template;

require_once('./lib/pkp/lib/vendor/smarty/smarty/libs/plugins/modifier.escape.php'); // Seems to be needed?

/* This definition is required by Smarty */
define('SMARTY_DIR', Core::getBaseDir() . '/lib/pkp/lib/vendor/smarty/smarty/libs/');

class PKPTemplateManager extends Smarty
{
    public const CACHEABILITY_NO_CACHE = 'no-cache';
    public const CACHEABILITY_NO_STORE = 'no-store';
    public const CACHEABILITY_PUBLIC = 'public';
    public const CACHEABILITY_MUST_REVALIDATE = 'must-revalidate';
    public const CACHEABILITY_PROXY_REVALIDATE = 'proxy-revalidate';

    public const STYLE_SEQUENCE_CORE = 0;
    public const STYLE_SEQUENCE_NORMAL = 10;
    public const STYLE_SEQUENCE_LATE = 15;
    public const STYLE_SEQUENCE_LAST = 20;

    public const CSS_FILENAME_SUFFIX = 'css';

    public const PAGE_WIDTH_NARROW = 'narrow';
    public const PAGE_WIDTH_NORMAL = 'normal';
    public const PAGE_WIDTH_WIDE = 'wide';
    public const PAGE_WIDTH_FULL = 'full';

    /** @var array of URLs to stylesheets */
    private $_styleSheets = [];

    /** @var array of URLs to javascript files */
    private $_javaScripts = [];

    /** @var array of HTML head content to output */
    private $_htmlHeaders = [];

    /** @var array Key/value list of constants to expose in the JS interface */
    private $_constants = [];

    /** @var array Key/value list of locale keys to expose in the JS interface */
    private $_localeKeys = [];

    /** @var array Initial state data to be managed by the page's Vue.js component */
    protected $_state = [];

    /** @var string Type of cacheability (Cache-Control). */
    private $_cacheability;

    /** @var object The form builder vocabulary class. */
    private $_fbv;

    /** @var PKPRequest */
    private $_request;

    /** @var string[] */
    private array $headers = [];


    /** @var bool Track whether its backend page */
    private bool $isBackendPage = false;


    /**
     * Constructor.
     * Initialize template engine and assign basic template variables.
     */
    public function __construct()
    {
        parent::__construct();

        // Set up Smarty configuration
        $baseDir = Core::getBaseDir();
        $cachePath = CacheManager::getFileCachePath();

        $this->compile_dir = "{$cachePath}/t_compile";
        $this->config_dir = "{$cachePath}/t_config";
        $this->cache_dir = "{$cachePath}/t_cache";

        $this->_cacheability = self::CACHEABILITY_NO_STORE; // Safe default

        // Register the template resources.
        $this->registerResource('core', new PKPTemplateResource($coreTemplateDir = 'lib/pkp/templates'));
        $this->registerResource('app', new PKPTemplateResource(['templates', $coreTemplateDir]));
        $this->default_resource_type = 'app';

        $this->error_reporting = E_ALL & ~E_NOTICE & ~E_WARNING;
    }

    /**
     * Initialize the template manager.
     *
     * @param PKPRequest $request
     */
    public function initialize($request)
    {
        assert($request instanceof PKPRequest);
        $this->_request = $request;

        $locale = Locale::getLocale();
        $application = Application::get();
        $router = $request->getRouter();
        assert($router instanceof \PKP\core\PKPRouter);
        $currentContext = $request->getContext();

        $this->assign([
            'defaultCharset' => 'utf-8',
            'baseUrl' => $request->getBaseUrl(),
            'currentContext' => $currentContext,
            'currentLocale' => $locale,
            'currentLocaleLangDir' => Locale::getMetadata($locale)?->isRightToLeft() ? 'rtl' : 'ltr',
            'applicationName' => __($application->getNameKey()),
        ]);

        // Assign date and time format
        if ($currentContext) {
            $this->assign([
                'dateFormatShort' => PKPString::convertStrftimeFormat($currentContext->getLocalizedDateFormatShort()),
                'dateFormatLong' => PKPString::convertStrftimeFormat($currentContext->getLocalizedDateFormatLong()),
                'datetimeFormatShort' => PKPString::convertStrftimeFormat($currentContext->getLocalizedDateTimeFormatShort()),
                'datetimeFormatLong' => PKPString::convertStrftimeFormat($currentContext->getLocalizedDateTimeFormatLong()),
                'timeFormat' => PKPString::convertStrftimeFormat($currentContext->getLocalizedTimeFormat()),
                'displayPageHeaderTitle' => $currentContext->getLocalizedData('name'),
                'displayPageHeaderLogo' => $currentContext->getLocalizedData('pageHeaderLogoImage'),
                'displayPageHeaderLogoAltText' => $currentContext->getLocalizedData('pageHeaderLogoImageAltText'),
            ]);
        } else {
            $this->assign([
                'dateFormatShort' => PKPString::convertStrftimeFormat(Config::getVar('general', 'date_format_short')),
                'dateFormatLong' => PKPString::convertStrftimeFormat(Config::getVar('general', 'date_format_long')),
                'datetimeFormatShort' => PKPString::convertStrftimeFormat(Config::getVar('general', 'datetime_format_short')),
                'datetimeFormatLong' => PKPString::convertStrftimeFormat(Config::getVar('general', 'datetime_format_long')),
                'timeFormat' => PKPString::convertStrftimeFormat(Config::getVar('general', 'time_format')),
            ]);
        }

        if (Application::isInstalled() && !$currentContext) {
            $site = $request->getSite();
            $this->assign([
                'displayPageHeaderTitle' => $site->getLocalizedTitle(),
                'displayPageHeaderLogo' => $site->getLocalizedData('pageHeaderTitleImage'),
            ]);
        }

        // Assign meta tags
        if ($currentContext) {
            $favicon = $currentContext->getLocalizedFavicon();
            if (!empty($favicon)) {
                $publicFileManager = new PublicFileManager();
                $faviconDir = $request->getBaseUrl() . '/' . $publicFileManager->getContextFilesPath($currentContext->getId());
                $this->addHeader('favicon', '<link rel="icon" href="' . $faviconDir . '/' . $favicon['uploadName'] . '" />', ['contexts' => ['frontend', 'backend']]);
            }
        }

        if (Application::isInstalled()) {
            $activeTheme = null;
            $contextOrSite = $currentContext ? $currentContext : $request->getSite();
            $allThemes = PluginRegistry::getPlugins('themes');
            foreach ($allThemes as $theme) {
                if ($contextOrSite->getData('themePluginPath') === $theme->getDirName()) {
                    $activeTheme = $theme;
                    break;
                }
            }
            $this->assign(['activeTheme' => $activeTheme]);
        }

        if ($router instanceof \PKP\core\PKPPageRouter) {
            $this->assign([
                'requestedPage' => $router->getRequestedPage($request),
                'requestedOp' => $router->getRequestedOp($request),
            ]);

            // A user-uploaded stylesheet
            if ($currentContext) {
                $contextStyleSheet = $currentContext->getData('styleSheet');
                if ($contextStyleSheet) {
                    $publicFileManager = new PublicFileManager();
                    $this->addStyleSheet(
                        'contextStylesheet',
                        $request->getBaseUrl() . '/' . $publicFileManager->getContextFilesPath($currentContext->getId()) . '/' . $contextStyleSheet['uploadName'] . '?d=' . urlencode($contextStyleSheet['dateUploaded']),
                        ['priority' => self::STYLE_SEQUENCE_LATE]
                    );
                }
            }

            // Register recaptcha on relevant pages
            if (Config::getVar('captcha', 'recaptcha')) {
                $contexts = [];
                if (Config::getVar('captcha', 'captcha_on_register')) {
                    array_push($contexts, 'frontend-user-register', 'frontend-user-registerUser');
                }
                if (Config::getVar('captcha', 'captcha_on_login')) {
                    array_push($contexts, 'frontend-login-index', 'frontend-login-signIn');
                }
                if (count($contexts)) {
                    $this->addJavaScript(
                        'recaptcha',
                        'https://www.recaptcha.net/recaptcha/api.js?hl=' . substr(Locale::getLocale(), 0, 2),
                        [
                            'contexts' => $contexts,
                        ]
                    );
                }
            }

            // Register meta tags
            if (Application::isInstalled()) {
                if (($request->getRequestedPage() == '' || $request->getRequestedPage() == 'index') && $currentContext && $currentContext->getLocalizedData('searchDescription')) {
                    $this->addHeader('searchDescription', '<meta name="description" content="' . $currentContext->getLocalizedData('searchDescription') . '" />');
                }

                $this->addHeader(
                    'generator',
                    '<meta name="generator" content="' . __($application->getNameKey()) . ' ' . $application->getCurrentVersion()->getVersionString(false) . '" />',
                    [
                        'contexts' => ['frontend', 'backend'],
                    ]
                );

                if ($currentContext) {
                    $customHeaders = $currentContext->getLocalizedData('customHeaders');
                    if (!empty($customHeaders)) {
                        $this->addHeader('customHeaders', $customHeaders);
                    }
                }
            }

            if ($currentContext && !$currentContext->getEnabled()) {
                $this->addHeader(
                    'noindex',
                    '<meta name="robots" content="noindex,nofollow" />',
                    [
                        'contexts' => ['frontend', 'backend'],
                    ]
                );
            }

            // Register Navigation Menus
            $nmService = Services::get('navigationMenu');

            if (Application::isInstalled()) {
                Hook::add('LoadHandler', [$nmService, '_callbackHandleCustomNavigationMenuItems']);
            }
        }

        // Register custom functions
        $this->registerPlugin('modifier', 'trim', 'trim');
        $this->registerPlugin('modifier', 'date_format', [$this, 'smartyDateFormat']);
        $this->registerPlugin('modifier', 'intval', 'intval');
        $this->registerPlugin('modifier', 'json_encode', 'json_encode');
        $this->registerPlugin('modifier', 'uniqid', 'uniqid');
        $this->registerPlugin('modifier', 'substr', 'substr');
        $this->registerPlugin('modifier', 'strstr', 'strstr');
        $this->registerPlugin('modifier', 'strval', 'strval');
        $this->registerPlugin('modifier', 'substr_replace', 'substr_replace');
        $this->registerPlugin('modifier', 'array_key_exists', 'array_key_exists');
        $this->registerPlugin('modifier', 'array_key_first', 'array_key_first');
        $this->registerPlugin('modifier', 'array_values', 'array_values');
        $this->registerPlugin('modifier', 'fatalError', 'fatalError');
        $this->registerPlugin('modifier', 'translate', [$this, 'smartyTranslateModifier']);
        $this->registerPlugin('modifier', 'strip_unsafe_html', '\PKP\core\PKPString::stripUnsafeHtml');
        $this->registerPlugin('modifier', 'parse_url', 'parse_url');
        $this->registerPlugin('modifier', 'parse_str', 'parse_str');
        $this->registerPlugin('modifier', 'strtok', 'strtok');
        $this->registerPlugin('modifier', 'array_pop', 'array_pop');
        $this->registerPlugin('modifier', 'array_keys', 'array_keys');
        $this->registerPlugin('modifier', 'String_substr', '\PKP\core\PKPString::substr');
        $this->registerPlugin('modifier', 'dateformatPHP2JQueryDatepicker', '\PKP\core\PKPString::dateformatPHP2JQueryDatepicker');
        $this->registerPlugin('modifier', 'to_array', [$this, 'smartyToArray']);
        $this->registerPlugin('modifier', 'compare', [$this, 'smartyCompare']);
        $this->registerPlugin('modifier', 'concat', [$this, 'smartyConcat']);
        $this->registerPlugin('modifier', 'strtotime', [$this, 'smartyStrtotime']);
        $this->registerPlugin('modifier', 'explode', [$this, 'smartyExplode']);
        $this->registerPlugin('modifier', 'escape', [$this, 'smartyEscape']);
        $this->registerPlugin('function', 'csrf', [$this, 'smartyCSRF']);
        $this->registerPlugin('function', 'translate', [$this, 'smartyTranslate']);
        $this->registerPlugin('function', 'null_link_action', [$this, 'smartyNullLinkAction']);
        $this->registerPlugin('function', 'help', [$this, 'smartyHelp']);
        $this->registerPlugin('function', 'flush', [$this, 'smartyFlush']);
        $this->registerPlugin('function', 'call_hook', [$this, 'smartyCallHook']);
        $this->registerPlugin('function', 'html_options_translate', [$this, 'smartyHtmlOptionsTranslate']);
        $this->registerPlugin('block', 'iterate', [$this, 'smartyIterate']);
        $this->registerPlugin('function', 'page_links', [$this, 'smartyPageLinks']);
        $this->registerPlugin('function', 'page_info', [$this, 'smartyPageInfo']);
        $this->registerPlugin('function', 'pluck_files', [$this, 'smartyPluckFiles']);
        $this->registerPlugin('function', 'locale_direction', [$this, 'smartyLocaleDirection']);
        $this->registerPlugin('function', 'html_select_date_a11y', [$this, 'smartyHtmlSelectDateA11y']);

        $this->registerPlugin('function', 'title', [$this, 'smartyTitle']);
        $this->registerPlugin('function', 'url', [$this, 'smartyUrl']);

        // load stylesheets/scripts/headers from a given context
        $this->registerPlugin('function', 'load_stylesheet', [$this, 'smartyLoadStylesheet']);
        $this->registerPlugin('function', 'load_script', [$this, 'smartyLoadScript']);
        $this->registerPlugin('function', 'load_header', [$this, 'smartyLoadHeader']);

        // load NavigationMenu Areas from context
        $this->registerPlugin('function', 'load_menu', [$this, 'smartyLoadNavigationMenuArea']);

        // Load form builder vocabulary
        $fbv = $this->getFBV();
        $this->registerPlugin('block', 'fbvFormSection', [$fbv, 'smartyFBVFormSection']);
        $this->registerPlugin('block', 'fbvFormArea', [$fbv, 'smartyFBVFormArea']);
        $this->registerPlugin('function', 'fbvFormButtons', [$fbv, 'smartyFBVFormButtons']);
        $this->registerPlugin('function', 'fbvElement', [$fbv, 'smartyFBVElement']);
        $this->registerPlugin('function', 'fieldLabel', [$fbv, 'smartyFieldLabel']);
        $this->assign('fbvStyles', $fbv->getStyles());

        // ajax load into a div or any element
        $this->registerPlugin('function', 'load_url_in_el', [$this, 'smartyLoadUrlInEl']);
        $this->registerPlugin('function', 'load_url_in_div', [$this, 'smartyLoadUrlInDiv']);

        // Always pass these ListBuilder constants to the browser
        // because a ListBuilder may be loaded in an ajax request
        // and won't have an opportunity to pass its constants to
        // the template manager. This is not a recommended practice,
        // but these are the only constants from a controller that are
        // required on the frontend. We can remove them once the
        // ListBuilderHandler is no longer needed.
        $this->setConstants([
            'LISTBUILDER_SOURCE_TYPE_TEXT' => ListbuilderHandler::LISTBUILDER_SOURCE_TYPE_TEXT,
            'LISTBUILDER_SOURCE_TYPE_SELECT' => ListbuilderHandler::LISTBUILDER_SOURCE_TYPE_SELECT,
            'LISTBUILDER_OPTGROUP_LABEL' => ListbuilderHandler::LISTBUILDER_OPTGROUP_LABEL,
        ]);

        /**
         * Kludge to make sure no code that tries to connect to the
         * database is executed (e.g., when loading installer pages).
         */
        if (!SessionManager::isDisabled()) {
            $this->assign([
                'isUserLoggedIn' => Validation::isLoggedIn(),
                'isUserLoggedInAs' => (bool) Validation::loggedInAs(),
                'itemsPerPage' => Config::getVar('interface', 'items_per_page'),
                'numPageLinks' => Config::getVar('interface', 'page_links'),
                'siteTitle' => $request->getSite()->getLocalizedData('title'),
            ]);

            $user = $request->getUser();
            if ($user) {
                /** @var NotificationDAO */
                $notificationDao = DAORegistry::getDAO('NotificationDAO');
                $this->assign([
                    'currentUser' => $user,
                    // Assign the user name to be used in the sitenav
                    'loggedInUsername' => $user->getUsername(),
                    // Assign a count of unread tasks
                    'unreadNotificationCount' => $notificationDao->getNotificationCount(false, $user->getId(), null, Notification::NOTIFICATION_LEVEL_TASK),
                ]);
            }
        }

        if (Application::isInstalled()) {
            // Respond to the sidebar hook
            if ($currentContext) {
                $this->assign('hasSidebar', !empty($currentContext->getData('sidebar')));
            } else {
                $this->assign('hasSidebar', !empty($request->getSite()->getData('sidebar')));
            }
            Hook::add('Templates::Common::Sidebar', [$this, 'displaySidebar']);

            // Clear the cache whenever the active theme is changed
            Hook::add('Context::edit', [$this, 'clearThemeTemplateCache']);
            Hook::add('Site::edit', [$this, 'clearThemeTemplateCache']);
        }
    }


    /**
     * Flag the page as cacheable (or not).
     *
     * @param string $cacheability optional
     */
    public function setCacheability($cacheability = self::CACHEABILITY_PUBLIC)
    {
        $this->_cacheability = $cacheability;
    }

    /**
     * Compile a LESS stylesheet
     *
     * @param string $name Unique name for this LESS stylesheet
     * @param string $lessFile Path to the LESS file to compile
     * @param array $args Optional arguments. Supports:
     *   'baseUrl': Base URL to use when rewriting URLs in the LESS file.
     *   'addLess': Array of additional LESS files to parse before compiling
     *
     * @return string Compiled CSS styles
     */
    public function compileLess($name, $lessFile, $args = [])
    {
        $less = new Less_Parser([
            'relativeUrls' => false,
            'compress' => true,
        ]);

        $request = $this->_request;

        // Allow plugins to intervene
        Hook::call('PageHandler::compileLess', [&$less, &$lessFile, &$args, $name, $request]);

        // Read the stylesheet
        $less->parseFile($lessFile);

        // Add extra LESS files before compiling
        if (isset($args['addLess']) && is_array($args['addLess'])) {
            foreach ($args['addLess'] as $addless) {
                $less->parseFile($addless);
            }
        }

        // Add extra LESS variables before compiling
        if (isset($args['addLessVariables'])) {
            foreach ((array) $args['addLessVariables'] as $addlessVariables) {
                $less->parse($addlessVariables);
            }
        }

        // Set the @baseUrl variable
        $baseUrl = !empty($args['baseUrl']) ? $args['baseUrl'] : $request->getBaseUrl(true);
        $less->parse("@baseUrl: '{$baseUrl}';");

        return $less->getCSS();
    }

    /**
     * Save LESS styles to a cached file
     *
     * @param string $path File path to save the compiled styles
     * @param string $styles CSS styles compiled from the LESS
     *
     * @return bool success/failure
     */
    public function cacheLess($path, $styles)
    {
        if (file_put_contents($path, $styles) === false) {
            error_log("Unable to write \"{$path}\".");
            return false;
        }

        return true;
    }

    /**
     * Retrieve the file path for a cached LESS file
     *
     * @param string $name Unique name for the LESS file
     *
     * @return string Path to the less file or false if not found
     */
    public function getCachedLessFilePath($name)
    {
        $directory = CacheManager::getFileCachePath();
        $contextId = $this->_request->getContext()?->getId() ?? 0;
        $hash = crc32($this->_request->getBaseUrl());
        return "{$directory}/{$contextId}-{$name}-{$hash}.css";
    }

    /**
     * Register a stylesheet with the style handler
     *
     * @param string $name Unique name for the stylesheet
     * @param string $style The stylesheet to be included. Should be a URL
     *   or, if the `inline` argument is included, stylesheet data to be output.
     * @param array $args Key/value array defining display details
     *   `priority` int The order in which to print this stylesheet.
     *      Default: STYLE_SEQUENCE_NORMAL
     *   `contexts` string|array Where the stylesheet should be loaded.
     *      Default: array('frontend')
     *   `inline` bool Whether the $stylesheet value should be output directly as
     *      stylesheet data. Used to pass backend data to the scripts.
     */
    public function addStyleSheet($name, $style, $args = [])
    {
        $args = array_merge(
            [
                'priority' => self::STYLE_SEQUENCE_NORMAL,
                'contexts' => ['frontend'],
                'inline' => false,
            ],
            $args
        );

        $args['contexts'] = (array) $args['contexts'];
        foreach ($args['contexts'] as $context) {
            $this->_styleSheets[$context][$args['priority']][$name] = [
                'style' => $style,
                'inline' => $args['inline'],
            ];
        }
    }

    /**
     * Register a script with the script handler
     *
     * @param string $name Unique name for the script
     * @param string $script The script to be included. Should be a URL or, if
     *   the `inline` argument is included, script data to be output.
     * @param array $args Key/value array defining display details
     *   `priority` int The order in which to print this script.
     *      Default: STYLE_SEQUENCE_NORMAL
     *   `contexts` string|array Where the script should be loaded.
     *      Default: array('frontend')
     *   `inline` bool Whether the $script value should be output directly as
     *      script data. Used to pass backend data to the scripts.
     */
    public function addJavaScript($name, $script, $args = [])
    {
        $args = array_merge(
            [
                'priority' => self::STYLE_SEQUENCE_NORMAL,
                'contexts' => ['frontend'],
                'inline' => false,
                'type' => 'text/javascript',
            ],
            $args
        );

        $args['contexts'] = (array) $args['contexts'];
        foreach ($args['contexts'] as $context) {
            $this->_javaScripts[$context][$args['priority']][$name] = [
                'script' => $script,
                'inline' => $args['inline'],
                'type' => $args['type'],
            ];
        }
    }

    /**
     * Add a page-specific item to the <head>.
     *
     * @param string $name Unique name for the header
     * @param string $header The header to be included.
     * @param array $args Key/value array defining display details
     *   `priority` int The order in which to print this header.
     *      Default: STYLE_SEQUENCE_NORMAL
     *   `contexts` string|array Where the header should be loaded.
     *      Default: array('frontend')
     */
    public function addHeader($name, $header, $args = [])
    {
        $args = array_merge(
            [
                'priority' => self::STYLE_SEQUENCE_NORMAL,
                'contexts' => ['frontend'],
            ],
            $args
        );

        $args['contexts'] = (array) $args['contexts'];
        foreach ($args['contexts'] as $context) {
            $this->_htmlHeaders[$context][$args['priority']][$name] = [
                'header' => $header,
            ];
        }
    }

    /**
     * Set constants to be exposed in JavaScript at pkp.const.<constant>
     *
     * @param array $names Array mapping constant names to values
     */
    public function setConstants($names)
    {
        foreach ($names as $name => $value) {
            $this->_constants[$name] = $value;
        }
    }

    /**
     * Set locale keys to be exposed in JavaScript at pkp.localeKeys.<key>
     *
     * @param array $keys Array of locale keys
     */
    public function setLocaleKeys($keys)
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $this->_localeKeys)) {
                $this->_localeKeys[$key] = __($key);
            }
        }
    }

    /**
     * Get a piece of the state data
     *
     */
    public function getState(string $key)
    {
        return array_key_exists($key, $this->_state)
            ? $this->_state[$key]
            : null;
    }

    /**
     * Set initial state data to be managed by the Vue.js component on this page
     *
     * @param array $data
     */
    public function setState($data)
    {
        $this->_state = array_merge($this->_state, $data);
    }

    /**
     * Register all files required by the core JavaScript library
     */
    public function registerJSLibrary()
    {
        $baseUrl = $this->_request->getBaseUrl();
        $localeChecks = [Locale::getLocale(), strtolower(substr(Locale::getLocale(), 0, 2))];

        // Common $args array used for all our core JS files
        $args = [
            'priority' => self::STYLE_SEQUENCE_CORE,
            'contexts' => 'backend',
        ];

        // Load jQuery validate separately because it can not be linted
        // properly by our build script
        $this->addJavaScript(
            'jqueryValidate',
            $baseUrl . '/lib/pkp/js/lib/jquery/plugins/validate/jquery.validate.min.js',
            $args
        );
        $jqvLocalePath = 'lib/pkp/js/lib/jquery/plugins/validate/localization/messages_';
        foreach ($localeChecks as $localeCheck) {
            if (file_exists($jqvLocalePath . $localeCheck . '.js')) {
                $this->addJavaScript('jqueryValidateLocale', $baseUrl . '/' . $jqvLocalePath . $localeCheck . '.js', $args);
            }
        }

        $this->addJavaScript(
            'plUpload',
            $baseUrl . '/lib/pkp/lib/vendor/moxiecode/plupload/js/plupload.full.min.js',
            $args
        );
        $this->addJavaScript(
            'jQueryPlUpload',
            $baseUrl . '/lib/pkp/lib/vendor/moxiecode/plupload/js/jquery.ui.plupload/jquery.ui.plupload.js',
            $args
        );
        $plLocalePath = 'lib/pkp/lib/vendor/moxiecode/plupload/js/i18n/';
        foreach ($localeChecks as $localeCheck) {
            if (file_exists($plLocalePath . $localeCheck . '.js')) {
                $this->addJavaScript('plUploadLocale', $baseUrl . '/' . $plLocalePath . $localeCheck . '.js', $args);
            }
        }

        // Load new component library bundle
        $this->addJavaScript(
            'pkpApp',
            $baseUrl . '/js/build.js',
            [
                'priority' => self::STYLE_SEQUENCE_LATE,
                'contexts' => ['backend']
            ]
        );

        // Load minified file if it exists
        if (Config::getVar('general', 'enable_minified')) {
            $this->addJavaScript(
                'pkpLib',
                $baseUrl . '/js/pkp.min.js',
                [
                    'priority' => self::STYLE_SEQUENCE_CORE,
                    'contexts' => ['backend']
                ]
            );
            return;
        }

        // Otherwise retrieve and register all script files
        $minifiedScripts = array_filter(array_map('trim', file('registry/minifiedScripts.txt')), function ($s) {
            return strlen($s) && $s[0] != '#'; // Exclude empty and commented (#) lines
        });
        foreach ($minifiedScripts as $key => $script) {
            $this->addJavaScript('pkpLib' . $key, "{$baseUrl}/{$script}", $args);
        }
    }

    /**
     * Register JavaScript data used by the core JS library
     *
     * This function registers script data that is required by the core JS
     * library. This data is queued after jQuery but before the pkp-lib
     * framework, allowing dynamic data to be passed to the framework. It is
     * intended to be used for passing constants and locale strings, but plugins
     * may also take advantage of a hook to include data required by their own
     * scripts, when integrating with the pkp-lib framework.
     */
    public function registerJSLibraryData()
    {
        $context = $this->_request->getContext();

        // Instantiate the namespace
        $output = '$.pkp = $.pkp || {};';

        $app_data = [
            'currentLocale' => Locale::getLocale(),
            'primaryLocale' => Locale::getPrimaryLocale(),
            'baseUrl' => $this->_request->getBaseUrl(),
            'contextPath' => isset($context) ? $context->getPath() : '',
            'apiBasePath' => '/api/v1',
            'restfulUrlsEnabled' => Config::getVar('general', 'restful_urls') ? true : false,
            'tinyMceContentCSS' => $this->_request->getBaseUrl() . '/plugins/generic/tinymce/styles/content.css',
            'tinyMceOneLineContentCSS' => $this->_request->getBaseUrl() . '/plugins/generic/tinymce/styles/content_oneline.css',
        ];

        // Add an array of rtl languages (right-to-left)
        if (Application::isInstalled() && !SessionManager::isDisabled()) {
            $allLocales = [];
            if ($context) {
                $allLocales = array_merge(
                    $context->getSupportedLocales() ?? [],
                    $context->getSupportedFormLocales() ?? [],
                    $context->getSupportedSubmissionLocales() ?? []
                );
            } else {
                $allLocales = $this->_request->getSite()->getSupportedLocales();
            }
            $allLocales = array_unique($allLocales);
            $rtlLocales = array_filter($allLocales, fn (string $locale) => Locale::getMetadata($locale)?->isRightToLeft());
            $app_data['rtlLocales'] = array_values($rtlLocales);
        }

        $output .= '$.pkp.app = ' . json_encode($app_data) . ';';

        // Load exposed constants
        $output .= '$.pkp.cons = ' . json_encode($this->_constants) . ';';

        // Allow plugins to load data within their own namespace
        $output .= '$.pkp.plugins = {};';

        $this->addJavaScript(
            'pkpLibData',
            $output,
            [
                'priority' => self::STYLE_SEQUENCE_CORE,
                'contexts' => 'backend',
                'inline' => true,
            ]
        );
    }

    /**
     * Set up the template requirements for editorial backend pages
     */
    public function setupBackendPage()
    {
        $this->isBackendPage = true;
        $request = Application::get()->getRequest();
        $dispatcher = $request->getDispatcher();
        /** @var PageRouter */
        $router = $request->getRouter();

        if (empty($this->getTemplateVars('pageComponent'))) {
            $this->assign('pageComponent', 'Page');
        }

        $this->setConstants([
            'REALLY_BIG_NUMBER' => REALLY_BIG_NUMBER,
            'UPLOAD_MAX_FILESIZE' => UPLOAD_MAX_FILESIZE,
            'WORKFLOW_STAGE_ID_PUBLISHED' => WORKFLOW_STAGE_ID_PUBLISHED,
            'WORKFLOW_STAGE_ID_SUBMISSION' => WORKFLOW_STAGE_ID_SUBMISSION,
            'WORKFLOW_STAGE_ID_INTERNAL_REVIEW' => WORKFLOW_STAGE_ID_INTERNAL_REVIEW,
            'WORKFLOW_STAGE_ID_EXTERNAL_REVIEW' => WORKFLOW_STAGE_ID_EXTERNAL_REVIEW,
            'WORKFLOW_STAGE_ID_EDITING' => WORKFLOW_STAGE_ID_EDITING,
            'WORKFLOW_STAGE_ID_PRODUCTION' => WORKFLOW_STAGE_ID_PRODUCTION,
            'INSERT_TAG_VARIABLE_TYPE_PLAIN_TEXT' => INSERT_TAG_VARIABLE_TYPE_PLAIN_TEXT,
            'ROLE_ID_MANAGER' => Role::ROLE_ID_MANAGER,
            'ROLE_ID_SITE_ADMIN' => Role::ROLE_ID_SITE_ADMIN,
            'ROLE_ID_AUTHOR' => Role::ROLE_ID_AUTHOR,
            'ROLE_ID_REVIEWER' => Role::ROLE_ID_REVIEWER,
            'ROLE_ID_ASSISTANT' => Role::ROLE_ID_ASSISTANT,
            'ROLE_ID_READER' => Role::ROLE_ID_READER,
            'ROLE_ID_SUB_EDITOR' => Role::ROLE_ID_SUB_EDITOR,
            'ROLE_ID_SUBSCRIPTION_MANAGER' => Role::ROLE_ID_SUBSCRIPTION_MANAGER,
            'STATUS_QUEUED' => Submission::STATUS_QUEUED,
            'STATUS_PUBLISHED' => Submission::STATUS_PUBLISHED,
            'STATUS_DECLINED' => Submission::STATUS_DECLINED,
            'STATUS_SCHEDULED' => Submission::STATUS_SCHEDULED,
        ]);

        // Common locale keys available in the browser for every page
        $this->setLocaleKeys([
            'common.attachFiles',
            'common.cancel',
            'common.clearSearch',
            'common.close',
            'common.commaListSeparator',
            'common.confirm',
            'common.delete',
            'common.edit',
            'common.editItem',
            'common.error',
            'common.filter',
            'common.filterAdd',
            'common.filterRemove',
            'common.insertContent',
            'common.loading',
            'common.no',
            'common.noItemsFound',
            'common.none',
            'common.ok',
            'common.order',
            'common.orderUp',
            'common.orderDown',
            'common.pageNumber',
            'common.pagination.goToPage',
            'common.pagination.label',
            'common.pagination.next',
            'common.pagination.previous',
            'common.remove',
            'common.required',
            'common.save',
            'common.saving',
            'common.search',
            'common.selectWithName',
            'common.unknownError',
            'common.uploadedBy',
            'common.uploadedByAndWhen',
            'common.view',
            'list.viewLess',
            'list.viewMore',
            'common.viewWithName',
            'common.yes',
            'form.dataHasChanged',
            'form.errorA11y',
            'form.errorGoTo',
            'form.errorMany',
            'form.errorOne',
            'form.errors',
            'form.multilingualLabel',
            'form.multilingualProgress',
            'form.saved',
            'help.help',
            'navigation.backTo',
            'validator.required'
        ]);

        // Set up the document type icons
        $documentTypeIcons = [
            FileManager::DOCUMENT_TYPE_DEFAULT => 'file-o',
            FileManager::DOCUMENT_TYPE_AUDIO => 'file-audio-o',
            FileManager::DOCUMENT_TYPE_EPUB => 'file-text-o',
            FileManager::DOCUMENT_TYPE_EXCEL => 'file-excel-o',
            FileManager::DOCUMENT_TYPE_HTML => 'file-code-o',
            FileManager::DOCUMENT_TYPE_IMAGE => 'file-image-o',
            FileManager::DOCUMENT_TYPE_PDF => 'file-pdf-o',
            FileManager::DOCUMENT_TYPE_WORD => 'file-word-o',
            FileManager::DOCUMENT_TYPE_VIDEO => 'file-video-o',
            FileManager::DOCUMENT_TYPE_ZIP => 'file-archive-o',
            FileManager::DOCUMENT_TYPE_URL => 'external-link',
        ];
        $this->addJavaScript(
            'documentTypeIcons',
            'pkp.documentTypeIcons = ' . json_encode($documentTypeIcons) . ';',
            [
                'priority' => self::STYLE_SEQUENCE_LAST,
                'contexts' => 'backend',
                'inline' => true,
            ]
        );

        // Register the jQuery script
        $min = Config::getVar('general', 'enable_minified') ? '.min' : '';
        $this->addJavaScript(
            'jquery',
            $request->getBaseUrl() . '/lib/pkp/lib/vendor/components/jquery/jquery' . $min . '.js',
            [
                'priority' => self::STYLE_SEQUENCE_CORE,
                'contexts' => 'backend',
            ]
        );
        $this->addJavaScript(
            'jqueryUI',
            $request->getBaseUrl() . '/lib/pkp/lib/vendor/components/jqueryui/jquery-ui' . $min . '.js',
            [
                'priority' => self::STYLE_SEQUENCE_CORE,
                'contexts' => 'backend',
            ]
        );

        // Register the pkp-lib JS library
        $this->registerJSLibraryData();
        $this->registerJSLibrary();

        // FontAwesome - http://fontawesome.io/
        $this->addStyleSheet(
            'fontAwesome',
            $request->getBaseUrl() . '/lib/pkp/styles/fontawesome/fontawesome.css',
            [
                'priority' => self::STYLE_SEQUENCE_CORE,
                'contexts' => 'backend',
            ]
        );

        // Stylesheet compiled from Vue.js single-file components
        $this->addStyleSheet(
            'build',
            $request->getBaseUrl() . '/styles/build.css',
            [
                'priority' => self::STYLE_SEQUENCE_CORE,
                'contexts' => 'backend',
            ]
        );

        // The legacy stylesheet for the backend
        $this->addStyleSheet(
            'pkpLib',
            $dispatcher->url($request, PKPApplication::ROUTE_COMPONENT, null, 'page.PageHandler', 'css'),
            [
                'priority' => self::STYLE_SEQUENCE_CORE,
                'contexts' => 'backend',
            ]
        );

        // Set up required state properties
        $this->setState([
            'menu' => [],
            'tinyMCE' => [
                'skinUrl' => $this->getTinyMceSkinUrl($request),
            ],
        ]);

        /**
         * Kludge to make sure no code that tries to connect to the
         * database is executed (e.g., when loading installer pages).
         */
        if (Application::isInstalled() && !SessionManager::isDisabled()) {
            if ($request->getUser()) {
                // Get a count of unread tasks
                $notificationDao = DAORegistry::getDAO('NotificationDAO'); /** @var NotificationDAO $notificationDao */
                $unreadTasksCount = (int) $notificationDao->getNotificationCount(false, $request->getUser()->getId(), null, Notification::NOTIFICATION_LEVEL_TASK);

                // Get a URL to load the tasks grid
                $tasksUrl = $request->getDispatcher()->url($request, PKPApplication::ROUTE_COMPONENT, null, 'page.PageHandler', 'tasks');

                // Load system notifications in SiteHandler.js
                $notificationDao = DAORegistry::getDAO('NotificationDAO'); /** @var NotificationDAO $notificationDao */
                $notificationsCount = count($notificationDao->getByUserId($request->getUser()->getId(), Notification::NOTIFICATION_LEVEL_TRIVIAL)->toArray());

                // Load context switcher
                $isAdmin = in_array(Role::ROLE_ID_SITE_ADMIN, $this->getTemplateVars('userRoles'));
                if ($isAdmin) {
                    $args = [];
                } else {
                    $args = ['userId' => $request->getUser()->getId()];
                }
                $availableContexts = Services::get('context')->getManySummary($args);
                if ($request->getContext()) {
                    $availableContexts = array_filter($availableContexts, function ($context) use ($request) {
                        return $context->id !== $request->getContext()->getId();
                    });
                }
                // Admins should switch to the same page on another context where possible
                $requestedOp = $request->getRequestedOp() === 'index' ? null : $request->getRequestedOp();
                $isSwitchable = $isAdmin && in_array($request->getRequestedPage(), [
                    'submissions',
                    'manageIssues',
                    'management',
                    'payment',
                    'stats',
                ]);
                foreach ($availableContexts as $availableContext) {
                    // Site admins redirected to the same page. Everyone else to submission lists
                    if ($isSwitchable) {
                        $availableContext->url = $dispatcher->url($request, PKPApplication::ROUTE_PAGE, $availableContext->urlPath, $request->getRequestedPage(), $requestedOp, $request->getRequestedArgs());
                    } else {
                        $availableContext->url = $dispatcher->url($request, PKPApplication::ROUTE_PAGE, $availableContext->urlPath, 'submissions');
                    }
                }

                // Create main navigation menu
                $userRoles = (array) $router->getHandler()->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES);

                $menu = [];

                if ($request->getContext()) {
                    if (count(array_intersect([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT, Role::ROLE_ID_REVIEWER, Role::ROLE_ID_AUTHOR], $userRoles))) {
                        $menu['submissions'] = [
                            'name' => __('navigation.submissions'),
                            'url' => $router->url($request, null, 'submissions'),
                            'isCurrent' => $router->getRequestedPage($request) === 'submissions',
                        ];
                    } elseif (count($userRoles) === 1 && in_array(Role::ROLE_ID_READER, $userRoles)) {
                        $menu['submit'] = [
                            'name' => __('author.submit'),
                            'url' => $router->url($request, null, 'submission'),
                            'isCurrent' => $router->getRequestedPage($request) === 'submission',
                        ];
                    }

                    if (count(array_intersect([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN], $userRoles))) {
                        if ($request->getContext()->getData('enableAnnouncements')) {
                            $menu['announcements'] = [
                                'name' => __('announcement.announcements'),
                                'url' => $router->url($request, null, 'management', 'settings', 'announcements'),
                                'isCurrent' => $router->getRequestedPage($request) === 'management' && in_array('announcements', (array) $router->getRequestedArgs($request)),
                            ];
                        }

                        if ($request->getContext()->getData(Context::SETTING_ENABLE_DOIS) && !empty($request->getContext()->getData(Context::SETTING_ENABLED_DOI_TYPES))) {
                            $menu['dois'] = [
                                'name' => __('doi.manager.displayName'),
                                'url' => $router->url($request, null, 'dois'),
                                'isCurrent' => $request->getRequestedPage() === 'dois',
                            ];
                        }

                        if ($request->getContext()->isInstitutionStatsEnabled($request->getSite())) {
                            $menu['institutions'] = [
                                'name' => __('institution.institutions'),
                                'url' => $router->url($request, null, 'management', 'settings', 'institutions'),
                                'isCurrent' => $request->getRequestedPage() === 'management' && in_array('institutions', (array) $request->getRequestedArgs()),
                            ];
                        }
                        $menu['settings'] = [
                            'name' => __('navigation.settings'),
                            'submenu' => [
                                'context' => [
                                    'name' => __('context.context'),
                                    'url' => $router->url($request, null, 'management', 'settings', 'context'),
                                    'isCurrent' => $router->getRequestedPage($request) === 'management' && in_array('context', (array) $router->getRequestedArgs($request)),
                                ],
                                'website' => [
                                    'name' => __('manager.website'),
                                    'url' => $router->url($request, null, 'management', 'settings', 'website'),
                                    'isCurrent' => $router->getRequestedPage($request) === 'management' && in_array('website', (array) $router->getRequestedArgs($request)),
                                ],
                                'workflow' => [
                                    'name' => __('manager.workflow'),
                                    'url' => $router->url($request, null, 'management', 'settings', 'workflow'),
                                    'isCurrent' => $router->getRequestedPage($request) === 'management' && in_array('workflow', (array) $router->getRequestedArgs($request)),
                                ],
                                'distribution' => [
                                    'name' => __('manager.distribution'),
                                    'url' => $router->url($request, null, 'management', 'settings', 'distribution'),
                                    'isCurrent' => $router->getRequestedPage($request) === 'management' && in_array('distribution', (array) $router->getRequestedArgs($request)),
                                ],
                                'access' => [
                                    'name' => __('navigation.access'),
                                    'url' => $router->url($request, null, 'management', 'settings', 'access'),
                                    'isCurrent' => $router->getRequestedPage($request) === 'management' && in_array('access', (array) $router->getRequestedArgs($request)),
                                ]
                            ]
                        ];
                    }

                    if (count(array_intersect([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_SUB_EDITOR], $userRoles))) {
                        $menu['statistics'] = [
                            'name' => __('navigation.tools.statistics'),
                            'submenu' => [
                                'publications' => [
                                    'name' => __('common.publications'),
                                    'url' => $router->url($request, null, 'stats', 'publications', 'publications'),
                                    'isCurrent' => $router->getRequestedPage($request) === 'stats' && $router->getRequestedOp($request) === 'publications',
                                ],
                                'context' => [
                                    'name' => __('context.context'),
                                    'url' => $router->url($request, null, 'stats', 'context', 'context'),
                                    'isCurrent' => $router->getRequestedPage($request) === 'stats' && $router->getRequestedOp($request) === 'context',
                                ],
                                'editorial' => [
                                    'name' => __('stats.editorialActivity'),
                                    'url' => $router->url($request, null, 'stats', 'editorial', 'editorial'),
                                    'isCurrent' => $router->getRequestedPage($request) === 'stats' && $router->getRequestedOp($request) === 'editorial',
                                ],
                                'users' => [
                                    'name' => __('manager.users'),
                                    'url' => $router->url($request, null, 'stats', 'users', 'users'),
                                    'isCurrent' => $router->getRequestedPage($request) === 'stats' && $router->getRequestedOp($request) === 'users',
                                ],
                                'counterR5' => [
                                    'name' => __('manager.statistics.counterR5'),
                                    'url' => $router->url($request, null, 'stats', 'counterR5', 'counterR5'),
                                    'isCurrent' => $router->getRequestedPage($request) === 'stats' && $router->getRequestedOp($request) === 'counterR5',
                                ]
                            ]
                        ];
                        if (count(array_intersect([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN], $userRoles))) {
                            $menu['statistics']['submenu'] += [
                                'reports' => [
                                    'name' => __('manager.statistics.reports'),
                                    'url' => $router->url($request, null, 'stats', 'reports'),
                                    'isCurrent' => $router->getRequestedPage($request) === 'stats' && $router->getRequestedOp($request) === 'reports',
                                ]
                            ];
                        }
                    }

                    if (count(array_intersect([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN], $userRoles))) {
                        $menu['tools'] = [
                            'name' => __('navigation.tools'),
                            'url' => $router->url($request, null, 'management', 'tools'),
                            'isCurrent' => $router->getRequestedPage($request) === 'management' && $router->getRequestedOp($request) === 'tools',
                        ];
                    }

                    if (in_array(Role::ROLE_ID_SITE_ADMIN, $userRoles)) {
                        $menu['admin'] = [
                            'name' => __('navigation.admin'),
                            'url' => $router->url($request, 'index', 'admin'),
                            'isCurrent' => $router->getRequestedPage($request) === 'admin',
                        ];
                    }
                }

                $this->setState([
                    'menu' => $menu,
                    'tasksUrl' => $tasksUrl,
                    'unreadTasksCount' => $unreadTasksCount,
                ]);

                $this->assign([
                    'availableContexts' => $availableContexts,
                    'hasSystemNotifications' => $notificationsCount > 0,
                ]);
            }
        }

        Hook::call('TemplateManager::setupBackendPage');
    }

    /**
     * @copydoc Smarty::fetch()
     *
     * @param null|mixed $template
     * @param null|mixed $cache_id
     * @param null|mixed $compile_id
     * @param null|mixed $parent
     */
    public function fetch($template = null, $cache_id = null, $compile_id = null, $parent = null)
    {
        // If no compile ID was assigned, get one.
        if (!$compile_id) {
            $compile_id = $this->getCompileId($template);
        }

        // Give hooks an opportunity to override
        $result = null;
        if (Hook::call('TemplateManager::fetch', [$this, $template, $cache_id, $compile_id, &$result])) {
            return $result;
        }

        return parent::fetch($template, $cache_id, $compile_id, $parent);
    }

    /**
     * Fetch content via AJAX and add it to the DOM, wrapped in a container element.
     *
     * @param string $id ID to use for the generated container element.
     * @param string $url URL to fetch the contents from.
     * @param string $element Element to use for container.
     *
     * @return JSONMessage The JSON-encoded result.
     */
    public function fetchAjax($id, $url, $element = 'div')
    {
        return new JSONMessage(true, $this->smartyLoadUrlInEl(
            [
                'url' => $url,
                'id' => $id,
                'el' => $element,
            ],
            $this
        ));
    }

    /**
     * Calculate a compile ID for a resource.
     *
     * @param string $resourceName Resource name.
     *
     * @return string
     */
    public function getCompileId($resourceName)
    {
        if (Application::isInstalled()) {
            $context = $this->_request->getContext();
            if ($context instanceof Context) {
                $resourceName .= $context->getData('themePluginPath');
            }
        }

        return sha1($resourceName);
    }

    /**
     * Returns the template results as a JSON message.
     *
     * @param string $template Template filename (or Smarty resource name)
     * @param bool $status
     *
     * @return JSONMessage JSON object
     */
    public function fetchJson($template, $status = true)
    {
        return new JSONMessage($status, $this->fetch($template));
    }

    /**
     * @copydoc Smarty::display()
     *
     * @param null|mixed $template
     * @param null|mixed $cache_id
     * @param null|mixed $compile_id
     * @param null|mixed $parent
     */
    public function display($template = null, $cache_id = null, $compile_id = null, $parent = null)
    {

        if($this->isBackendPage) {
            $this->unregisterPlugin('modifier', 'escape');

            /** prevent {{ JS }} injection  */
            $this->registerPlugin('modifier', 'escape', function ($string, $esc_type = 'html', $char_set = 'ISO-8859-1') {
                $result = $string;
                if($esc_type === 'html') {
                    $result = $this->smartyEscape($result, $esc_type, $char_set);
                    $result = str_replace('{{', '<span v-pre>{{</span>', $result);
                    $result = str_replace('}}', '<span v-pre>}}</span>', $result);
                    return $result;
                }


                return $this->smartyEscape($result, $esc_type, $char_set);

            });

            $this->unregisterPlugin('modifier', 'strip_unsafe_html');

            /** prevent {{ JS }} injection  */
            $this->registerPlugin('modifier', 'strip_unsafe_html', function ($input, $configKey = 'allowed_html') {
                $result = \PKP\core\PKPString::stripUnsafeHtml($input, $configKey);
                $result = str_replace('{{', '<span v-pre>{{</span>', $result);
                $result = str_replace('}}', '<span v-pre>}}</span>', $result);
                return $result;
            });


        }
        // Output global constants and locale keys used in new component library
        $output = '';
        if (!empty($this->_constants)) {
            $output .= 'pkp.const = ' . json_encode($this->_constants) . ';';
        }
        if (!empty($this->_localeKeys)) {
            $output .= 'pkp.localeKeys = ' . json_encode($this->_localeKeys) . ';';
        }

        // Load current user data
        if (Application::isInstalled()) {
            $user = $this->_request->getUser();
            if ($user) {
                $userGroups = Repo::userGroup()->userUserGroups($user->getId());

                $userRoles = [];
                foreach ($userGroups as $userGroup) {
                    $userRoles[] = (int) $userGroup->getRoleId();
                }
                $currentUser = [
                    'csrfToken' => $this->_request->getSession()->getCSRFToken(),
                    'id' => (int) $user->getId(),
                    'roles' => array_values(array_unique($userRoles)),
                ];
                $output .= 'pkp.currentUser = ' . json_encode($currentUser) . ';';
            }
        }

        $this->addJavaScript(
            'pkpAppData',
            $output,
            [
                'priority' => self::STYLE_SEQUENCE_LATE,
                'contexts' => ['backend'],
                'inline' => true,
            ]
        );

        // Give any hooks registered against the TemplateManager
        // the opportunity to modify behavior; otherwise, display
        // the template as usual.
        $output = null;
        if (Hook::call('TemplateManager::display', [$this, &$template, &$output])) {
            echo $output;
            return;
        }

        // Pass the initial state data for this page
        $this->assign('state', $this->_state);

        // Explicitly set the character encoding. Required in
        // case server is using Apache's AddDefaultCharset
        // directive (which can prevent browser auto-detection
        // of the proper character set).
        header('content-type: text/html; charset=utf-8');
        header("cache-control: {$this->_cacheability}");

        foreach ($this->headers as $header) {
            header($header);
        }

        // If no compile ID was assigned, get one.
        if (!$compile_id) {
            $compile_id = $this->getCompileId($template);
        }

        // Actually display the template.
        parent::display($template, $cache_id, $compile_id, $parent);
    }

    /**
     * Clear template compile and cache directories.
     */
    public function clearTemplateCache()
    {
        $this->clearCompiledTemplate();
        $this->clearAllCache();
    }

    /**
     * Clear all compiled CSS files
     */
    public function clearCssCache()
    {
        $cacheDirectory = CacheManager::getFileCachePath();
        $files = scandir($cacheDirectory);
        array_map('unlink', glob(CacheManager::getFileCachePath() . '/*.' . self::CSS_FILENAME_SUFFIX));
    }

    /**
     * Clear the cache when a context or site has changed it's active theme
     *
     * @param string $hookName
     * @param array $args [
     *
     * 	@option Context|Site The new values
     * 	@option Context|Site The old values
     * 	@option array Key/value of params that were modified
     * 	@option Request
     * ]
     */
    public function clearThemeTemplateCache($hookName, $args)
    {
        $newContextOrSite = $args[0];
        $contextOrSite = $args[1];
        if ($newContextOrSite->getData('themePluginPath') !== $contextOrSite->getData('themePluginPath')) {
            $this->clearTemplateCache();
            $this->clearCssCache();
        }
    }

    /**
     * Return an instance of the template manager.
     *
     * @param ?PKPRequest $request
     *
     * @return TemplateManager the template manager object
     */
    public static function &getManager($request = null)
    {
        if (!isset($request)) {
            $request = Registry::get('request');
            if (Config::getVar('debug', 'deprecation_warnings')) {
                throw new Exception('Deprecated call without request object.');
            }
        }
        assert($request instanceof PKPRequest);

        $instance = & Registry::get('templateManager', true, null); // Reference required

        if ($instance === null) {
            $instance = new TemplateManager();
            $themes = PluginRegistry::getPlugins('themes');
            if (empty($themes)) {
                $themes = PluginRegistry::loadCategory('themes', true);
            }
            $instance->initialize($request);
        }

        return $instance;
    }

    /**
     * Return an instance of the Form Builder Vocabulary class.
     *
     * @return TemplateManager the template manager object
     */
    public function getFBV()
    {
        if (!$this->_fbv) {
            $this->_fbv = new FormBuilderVocabulary();
        }
        return $this->_fbv;
    }

    /**
     * Display the sidebar
     *
     * @param string $hookName
     * @param array $args [
     *
     *		@option array Params passed to the hook
     *		@option Smarty
     *		@option string The output
     * ]
     */
    public function displaySidebar($hookName, $args)
    {
        $params = & $args[0];
        $smarty = & $args[1];
        $output = & $args[2];

        if ($this->_request->getContext()) {
            $blocks = $this->_request->getContext()->getData('sidebar');
        } else {
            $blocks = $this->_request->getSite()->getData('sidebar');
        }

        if (empty($blocks)) {
            return false;
        }

        $plugins = PluginRegistry::loadCategory('blocks', true);
        if (empty($plugins)) {
            return false;
        }

        foreach ($blocks as $pluginName) {
            if (!empty($plugins[$pluginName])) {
                $output .= $plugins[$pluginName]->getContents($smarty, $this->_request);
            }
        }

        return false;
    }

    /**
     * Get the URL to the TinyMCE skin
     */
    public function getTinyMceSkinUrl(Request $request): string
    {
        return $request->getBaseUrl() . '/lib/pkp/styles/tinymce';
    }


    //
    // Custom template functions, modifiers, etc.
    //

    /**
     * Smarty usage:
     * Simple translation
     * {translate key="localization.key.name" [paramName="paramValue" ...]}
     *
     * Pluralized translation
     * {translate key="localization.key.name" count="10" [paramName="paramValue" ...]}
     * Custom Smarty function for translating localization keys.
     * Substitution works by replacing tokens like "{$foo}" with the value of the parameter named "foo" (if supplied).
     *
     * The params named "key", "count", "locale" and "params" are reserved. If you need to pass one of them as a translation variable specify them using the "params":
     * $smarty->assign('params', ['key' => "Golden key"]);
     * {translate key="pluralized.key" locale="en" count="10" params=$params}
     *
     * @param array $params associative array, must contain "key" parameter for string to translate plus zero or more named parameters for substitution.
     * 	Translation variables can be specified also as an optional associative array named "params".
     * @param Smarty $smarty
     *
     * @return string the localized string, including any parameter substitutions
     */
    public function smartyTranslate(array $params, Smarty_Internal_Template $smarty): string
    {
        // Save reserved params before removing them
        $key = $params['key'] ?? '';
        $count = $params['count'] ?? null;
        $locale = $params['locale'] ?? null;
        $variables = $params['params'] ?? [];
        // Remove reserved params
        unset($params['key'], $params['count'], $params['params'], $params['locale']);
        // Merge variables
        $variables = $params + $variables;
        // Decides between the simple/pluralized version
        return $count === null ? __($key, $variables, $locale) : __p($key, $count, $variables, $locale);
    }

    /**
     * Applies a translation modifier, where the value to be transformed is used as locale key
     * Simple translation
     * {$foo|translate}
     *
     * Passing variables for the translation
     * {$foo|translate:varFoo:valueFoo:varBar:valueBar}
     *
     * Pluralized translation with a different locale
     * {$foo|translate:count:123:locale:pt_BR}
     */
    public function smartyTranslateModifier(): string
    {
        $params = func_get_args();
        $key = array_shift($params);
        $variables = [];
        if (count($params)) {
            $name = null;
            foreach ($params as $i => $value) {
                if ($i % 2) {
                    $variables[$name] = $value;
                } else {
                    $name = $value;
                }
            }
        }
        $count = $variables['count'] ?? null;
        $locale = $variables['locale'] ?? null;
        return $count === null ? __($key, $variables, $locale) : __p($key, $count, $variables, $locale);
    }

    /**
     * Smarty usage: {null_link_action id="linkId" key="localization.key.name" image="imageClassName"}
     *
     * Custom Smarty function for displaying a null link action; these will
     * typically be attached and handled in Javascript.
     *
     * @param Smarty $smarty
     *
     * @return string the HTML for the generated link action
     */
    public function smartyNullLinkAction($params, $smarty)
    {
        assert(isset($params['id']));

        $id = $params['id'];
        $key = $params['key'] ?? null;
        $hoverTitle = isset($params['hoverTitle']) ? true : false;
        $image = $params['image'] ?? null;
        $translate = isset($params['translate']) ? false : true;

        $key = $translate ? __($key) : $key;
        $this->assign('action', new LinkAction(
            $id,
            new NullAction(),
            $key,
            $image
        ));

        $this->assign('hoverTitle', $hoverTitle);
        return $this->fetch('linkAction/linkAction.tpl');
    }

    /**
     * Smarty usage: {help file="someFile" section="someSection" textKey="some.text.key"}
     *
     * Custom Smarty function for displaying a context-sensitive help link.
     *
     * @param Smarty $smarty
     *
     * @return string the HTML for the generated link action
     */
    public function smartyHelp($params, $smarty)
    {
        assert(isset($params['file']));

        $params = array_merge(
            [
                'file' => null, // The name of the Markdown file
                'section' => null, // The (optional) anchor within the Markdown file
                'textKey' => 'help.help', // An (optional) locale key for the link
                'text' => null, // An (optional) literal text for the link
                'class' => null, // An (optional) CSS class string for the link
            ],
            $params
        );

        $this->assign([
            'helpFile' => $params['file'],
            'helpSection' => $params['section'],
            'helpTextKey' => $params['textKey'],
            'helpText' => $params['text'],
            'helpClass' => $params['class'],
        ]);

        return $this->fetch('common/helpLink.tpl');
    }

    /**
     * Smarty usage: {html_options_translate ...}
     * For parameter usage, see http://smarty.php.net/manual/en/language.function.html.options.php
     *
     * Identical to Smarty's "html_options" function except option values are translated from i18n keys.
     *
     * @param array $params
     * @param Smarty $smarty
     */
    public function smartyHtmlOptionsTranslate($params, $smarty)
    {
        if (isset($params['options'])) {
            if (isset($params['translateValues'])) {
                // Translate values AND output
                $newOptions = [];
                foreach ($params['options'] as $k => $v) {
                    $newOptions[__($k)] = __($v);
                }
                $params['options'] = $newOptions;
            } else {
                // Just translate output
                $params['options'] = array_map('__', $params['options']);
            }
        }

        if (isset($params['output'])) {
            $params['output'] = array_map('__', $params['output']);
        }

        if (isset($params['values']) && isset($params['translateValues'])) {
            $params['values'] = array_map('__', $params['values']);
        }

        require_once('lib/pkp/lib/vendor/smarty/smarty/libs/plugins/function.html_options.php');
        /** @var Smarty_Internal_Template $smarty */
        return smarty_function_html_options($params, $smarty);
    }

    /**
     * Iterator function for looping through objects extending the
     * ItemIterator class.
     * Parameters:
     *  - from: Name of template variable containing iterator
     *  - item: Name of template variable to receive each item
     *  - key: (optional) Name of variable to receive index of current item
     */
    public function smartyIterate($params, $content, $smarty, &$repeat)
    {
        $iterator = $smarty->getTemplateVars($params['from']);

        if (isset($params['key'])) {
            if (empty($content)) {
                $smarty->assign($params['key'], 1);
            } else {
                $smarty->assign($params['key'], $smarty->getTemplateVars($params['key']) + 1);
            }
        }

        // If the iterator is empty, we're finished.
        if (!$iterator || $iterator->eof()) {
            if (!$repeat) {
                return $content;
            }
            $repeat = false;
            return '';
        }

        $repeat = true;

        if (isset($params['key'])) {
            [$key, $value] = $iterator->nextWithKey();
            $smarty->assign($params['item'], $value);
            $smarty->assign($params['key'], $key);
        } else {
            $smarty->assign($params['item'], $iterator->next());
        }
        return $content;
    }

    /**
     * Display page information for a listing of items that has been
     * divided onto multiple pages.
     * Usage:
     * {page_info from=$myIterator}
     */
    public function smartyPageInfo($params, $smarty)
    {
        $iterator = $params['iterator'];

        if (isset($params['itemsPerPage'])) {
            $itemsPerPage = $params['itemsPerPage'];
        } else {
            $itemsPerPage = $smarty->getTemplateVars('itemsPerPage');
            if (!is_numeric($itemsPerPage)) {
                $itemsPerPage = 25;
            }
        }

        $page = $iterator->getPage();
        $pageCount = $iterator->getPageCount();
        $itemTotal = $iterator->getCount();

        if ($pageCount < 1) {
            return '';
        }

        $from = (($page - 1) * $itemsPerPage) + 1;
        $to = min($itemTotal, $page * $itemsPerPage);

        return __('navigation.items', [
            'from' => ($to === 0 ? 0 : $from),
            'to' => $to,
            'total' => $itemTotal
        ]);
    }

    /**
     * Flush the output buffer. This is useful in cases where Smarty templates
     * are calling functions that take a while to execute so that they can display
     * a progress indicator or a message stating that the operation may take a while.
     */
    public function smartyFlush($params, $smarty)
    {
        $smarty->flush();
    }

    public function flush()
    {
        while (ob_get_level()) {
            ob_end_flush();
        }
        flush();
    }

    /**
     * Call hooks from a template.
     */
    public function smartyCallHook($params, $smarty)
    {
        $output = null;
        Hook::call($params['name'], [&$params, $smarty, &$output]);
        return $output;
    }

    /**
     * Generate a URL into a PKPApp.
     *
     * @param object $smarty
     * Available parameters:
     * - router: which router to use
     * - context
     * - page
     * - component
     * - op
     * - path (array)
     * - anchor
     * - escape (default to true unless otherwise specified)
     * - params: parameters to include in the URL if available as an array
     */
    public function smartyUrl($parameters, $smarty)
    {
        if (!isset($parameters['context'])) {
            // Extract the variables named in $paramList, and remove them
            // from the parameters array. Variables remaining in params will be
            // passed along to Request::url as extra parameters.
            $contextName = Application::get()->getContextName();
            if (isset($parameters[$contextName])) {
                $context = $parameters[$contextName];
                unset($parameters[$contextName]);
            } else {
                $context = null;
            }
            $parameters['context'] = $context;
        }

        // Extract the reserved variables named in $paramList, and remove them
        // from the parameters array. Variables remaining in parameters will be passed
        // along to Request::url as extra parameters.
        $params = $router = $page = $component = $anchor = $escape = $op = $path = null;
        $paramList = ['params', 'router', 'context', 'page', 'component', 'op', 'path', 'anchor', 'escape'];
        foreach ($paramList as $parameter) {
            if (isset($parameters[$parameter])) {
                $$parameter = $parameters[$parameter];
            } else {
                $$parameter = null;
            }
            unset($parameters[$parameter]);
        }

        // Merge parameters specified in the {url paramName=paramValue} format with
        // those optionally supplied in {url params=$someAssociativeArray} format
        $parameters = array_merge($parameters, (array) $params);

        // Set the default router
        if (is_null($router)) {
            if ($this->_request->getRouter() instanceof \PKP\core\PKPComponentRouter) {
                $router = PKPApplication::ROUTE_COMPONENT;
            } else {
                $router = PKPApplication::ROUTE_PAGE;
            }
        }

        // Check the router
        $dispatcher = Application::get()->getDispatcher();
        $routerShortcuts = array_keys($dispatcher->getRouterNames());
        assert(in_array($router, $routerShortcuts));

        // Identify the handler
        switch ($router) {
            case PKPApplication::ROUTE_PAGE:
                $handler = $page;
                break;

            case PKPApplication::ROUTE_COMPONENT:
                $handler = $component;
                break;

            default:
                // Unknown router type
                assert(false);
        }
        // Let the dispatcher create the url
        return $dispatcher->url($this->_request, $router, $context, $handler, $op, $path, $parameters, $anchor, !isset($escape) || $escape);
    }

    /**
     * Generate the <title> tag for a page
     *
     * Usage: {title value="Journal Settings"}
     *
     * @param array $parameters
     * @param object $smarty
     * Available parameters:
     * - router: which router to use
     * - context
     * - page
     * - component
     * - op
     * - path (array)
     * - anchor
     * - escape (default to true unless otherwise specified)
     * - params: parameters to include in the URL if available as an array
     */
    public function smartyTitle($parameters, $smarty)
    {
        $page = $parameters['value'] ?? '';
        if ($smarty->getTemplateVars('currentContext')) {
            $siteTitle = $smarty->getTemplateVars('currentContext')->getLocalizedData('name');
        } elseif ($smarty->getTemplateVars('siteTitle')) {
            $siteTitle = $smarty->getTemplateVars('siteTitle');
        } else {
            $siteTitle = __('common.software');
        }

        if (empty($parameters['value'])) {
            return $siteTitle;
        }

        return $parameters['value'] . __('common.titleSeparator') . $siteTitle;
    }

    /**
     * Display page links for a listing of items that has been
     * divided onto multiple pages.
     * Usage:
     * {page_links
     * 	name="nameMustMatchGetRangeInfoCall"
     * 	iterator=$myIterator
     *	additional_param=myAdditionalParameterValue
     * }
     */
    public function smartyPageLinks($params, $smarty)
    {
        $iterator = $params['iterator'];
        $name = $params['name'];
        if (isset($params['params']) && is_array($params['params'])) {
            $extraParams = $params['params'];
            unset($params['params']);
            $params = array_merge($params, $extraParams);
        }
        if (isset($params['anchor'])) {
            $anchor = $params['anchor'];
            unset($params['anchor']);
        } else {
            $anchor = null;
        }
        if (isset($params['all_extra'])) {
            $allExtra = ' ' . $params['all_extra'];
            unset($params['all_extra']);
        } else {
            $allExtra = '';
        }

        unset($params['iterator']);
        unset($params['name']);

        $numPageLinks = $smarty->getTemplateVars('numPageLinks');
        if (!is_numeric($numPageLinks)) {
            $numPageLinks = 10;
        }

        $page = $iterator->getPage();
        $pageCount = $iterator->getPageCount();

        $pageBase = max($page - floor($numPageLinks / 2), 1);
        $paramName = $name . 'Page';

        if ($pageCount <= 1) {
            return '';
        }

        $value = '';

        $router = $this->_request->getRouter();
        $requestedArgs = null;
        if ($router instanceof PageRouter) {
            $requestedArgs = $router->getRequestedArgs($this->_request);
        }

        if ($page > 1) {
            $params[$paramName] = 1;
            $value .= '<a href="' . $this->_request->url(null, null, null, $requestedArgs, $params, $anchor) . '"' . $allExtra . '>&lt;&lt;</a>&nbsp;';
            $params[$paramName] = $page - 1;
            $value .= '<a href="' . $this->_request->url(null, null, null, $requestedArgs, $params, $anchor) . '"' . $allExtra . '>&lt;</a>&nbsp;';
        }

        for ($i = $pageBase; $i < min($pageBase + $numPageLinks, $pageCount + 1); $i++) {
            if ($i == $page) {
                $value .= "<strong>{$i}</strong>&nbsp;";
            } else {
                $params[$paramName] = $i;
                $value .= '<a href="' . $this->_request->url(null, null, null, $requestedArgs, $params, $anchor) . '"' . $allExtra . '>' . $i . '</a>&nbsp;';
            }
        }
        if ($page < $pageCount) {
            $params[$paramName] = $page + 1;
            $value .= '<a href="' . $this->_request->url(null, null, null, $requestedArgs, $params, $anchor) . '"' . $allExtra . '>&gt;</a>&nbsp;';
            $params[$paramName] = $pageCount;
            $value .= '<a href="' . $this->_request->url(null, null, null, $requestedArgs, $params, $anchor) . '"' . $allExtra . '>&gt;&gt;</a>&nbsp;';
        }

        return $value;
    }

    /**
     * Convert the parameters of a function to an array.
     */
    public function smartyToArray()
    {
        return func_get_args();
    }

    /**
     * Concatenate the parameters and return the result.
     */
    public function smartyConcat()
    {
        $args = func_get_args();
        return implode('', $args);
    }

    /**
     * Compare the parameters.
     *
     * @param mixed $a Parameter A
     * @param mixed $a Parameter B
     * @param bool $strict True iff a strict (===) compare should be used
     * @param bool $invert True iff the output should be inverted
     */
    public function smartyCompare($a, $b, $strict = false, $invert = false)
    {
        $result = $strict ? $a === $b : $a == $b;
        return $invert ? !$result : $result;
    }

    /**
     * Convert a string to a numeric time.
     */
    public function smartyStrtotime($string)
    {
        return strtotime($string);
    }

    /**
     * Split the supplied string by the supplied separator.
     */
    public function smartyExplode($string, $separator)
    {
        return explode($separator, $string);
    }

    /**
     * Override the built-in smarty date format modifier to support translated formats.
     * (Work-around for https://github.com/smarty-php/smarty/issues/810)
     */
    public function smartyDateFormat($string, $format = null, $default_date = '', $formatter = 'auto')
    {
        return (new \Carbon\Carbon($string))->locale(Locale::getLocale())->translatedFormat($format);
    }

    /**
     * Override the built-in smarty escape modifier to
     * add the jqselector escaping method.
     */
    public function smartyEscape($string, $esc_type = 'html', $char_set = 'ISO-8859-1')
    {
        $pattern = "/(:|\.|\[|\]|,|=|@)/";
        $replacement = '\\\\\\\$1';
        switch ($esc_type) {
            // Because jQuery uses CSS syntax for selecting elements
            // some characters are interpreted as CSS notation.
            // In order to tell jQuery to treat these characters literally rather
            // than as CSS notation, they must be escaped by placing two backslashes
            // in front of them.
            case 'jqselector':
                $result = smarty_modifier_escape($string, 'html', $char_set);
                $result = preg_replace($pattern, $replacement, $result);
                return $result;

            case 'jsid':
                $result = smarty_modifier_escape($string, 'javascript', $char_set);
                $result = preg_replace($pattern, $replacement, $result);
                return $result;

            default:
                return smarty_modifier_escape($string, $esc_type, $char_set);
        }
    }

    /**
     * Smarty usage: {load_url_in_el el="htmlElement" id="someHtmlId" url="http://the.url.to.be.loaded.into.the.grid"}
     *
     * Custom Smarty function for loading a URL via AJAX into any HTML element
     *
     * @param array $params associative array
     * @param Smarty $smarty
     *
     * @return string of HTML/Javascript
     */
    public function smartyLoadUrlInEl($params, $smarty)
    {
        // Required Params
        if (!isset($params['el'])) {
            throw new Exception('el parameter is missing from load_url_in_el');
        }
        if (!isset($params['url'])) {
            throw new Exception('url parameter is missing from load_url_in_el');
        }
        if (!isset($params['id'])) {
            throw new Exception('id parameter is missing from load_url_in_el');
        }

        $this->assign([
            'inEl' => $params['el'],
            'inElUrl' => $params['url'],
            'inElElId' => $params['id'],
            'inElClass' => $params['class'] ?? null,
            'inVueEl' => $params['inVueEl'] ?? null,
            'refreshOn' => $params['refreshOn'] ?? null,
        ]);

        if (isset($params['placeholder'])) {
            $this->assign('inElPlaceholder', $params['placeholder']);
        } elseif (isset($params['loadMessageId'])) {
            $loadMessageId = $params['loadMessageId'];
            $this->assign('inElPlaceholder', __($loadMessageId, $params));
        } else {
            $this->assign('inElPlaceholder', $this->fetch('common/loadingContainer.tpl'));
        }

        return $this->fetch('common/urlInEl.tpl');
    }

    /**
     * Smarty usage: {load_url_in_div id="someHtmlId" url="http://the.url.to.be.loaded.into.the.grid"}
     *
     * Custom Smarty function for loading a URL via AJAX into a DIV. Convenience
     * wrapper for smartyLoadUrlInEl.
     *
     * @param array $params associative array
     * @param Smarty $smarty
     *
     * @return string of HTML/Javascript
     */
    public function smartyLoadUrlInDiv($params, $smarty)
    {
        $params['el'] = 'div';
        return $this->smartyLoadUrlInEl($params, $smarty);
    }

    /**
     * Smarty usage: {csrf}
     *
     * Custom Smarty function for inserting a CSRF token.
     *
     * @param array $params associative array
     * @param Smarty $smarty
     *
     * @return string of HTML
     */
    public function smartyCSRF($params, $smarty)
    {
        $csrfToken = $this->_request->getSession()->getCSRFToken();
        switch ($params['type'] ?? null) {
            case 'raw': return $csrfToken;
            case 'json': return json_encode($csrfToken);
            case 'html':
            default:
                return '<input type="hidden" name="csrfToken" value="' . htmlspecialchars($csrfToken) . '" />';
        }
    }

    /**
     * Smarty usage: {load_stylesheet context="frontend" stylesheets=$stylesheets}
     *
     * Custom Smarty function for printing stylesheets attached to a context.
     *
     * @param array $params associative array
     * @param Smarty $smarty
     *
     * @return string of HTML/Javascript
     */
    public function smartyLoadStylesheet($params, $smarty)
    {
        if (empty($params['context'])) {
            $params['context'] = 'frontend';
        }

        if (!SessionManager::isDisabled()) {
            $versionDao = DAORegistry::getDAO('VersionDAO'); /** @var VersionDAO $versionDao */
            $appVersion = $versionDao->getCurrentVersion()->getVersionString();
        } else {
            $appVersion = null;
        }

        $stylesheets = $this->getResourcesByContext($this->_styleSheets, $params['context']);

        ksort($stylesheets);

        $output = '';
        foreach ($stylesheets as $priorityList) {
            foreach ($priorityList as $style) {
                if (!empty($style['inline'])) {
                    $output .= '<style type="text/css">' . $style['style'] . '</style>';
                } else {
                    if ($appVersion && strpos($style['style'], '?') === false) {
                        $style['style'] .= '?v=' . $appVersion;
                    }
                    $output .= '<link rel="stylesheet" href="' . $style['style'] . '" type="text/css" />';
                }
            }
        }

        return $output;
    }

    /**
     * Inject default styles into a HTML galley
     *
     * Any styles assigned to the `htmlGalley` context will be injected into the
     * galley unless the galley already has an embedded CSS file.
     *
     * @param string $htmlContent The HTML file content
     * @param array $embeddedFiles Additional files embedded in this galley
     */
    public function loadHtmlGalleyStyles($htmlContent, $embeddedFiles)
    {
        if (empty($htmlContent)) {
            return $htmlContent;
        }

        $hasEmbeddedStyle = false;
        foreach ($embeddedFiles as $embeddedFile) {
            if ($embeddedFile->getData('mimetype') === 'text/css') {
                $hasEmbeddedStyle = true;
                break;
            }
        }

        if ($hasEmbeddedStyle) {
            return $htmlContent;
        }

        $links = '';
        $styles = $this->getResourcesByContext($this->_styleSheets, 'htmlGalley');

        if (!empty($styles)) {
            ksort($styles);
            foreach ($styles as $priorityGroup) {
                foreach ($priorityGroup as $htmlStyle) {
                    if (!empty($htmlStyle['inline'])) {
                        $links .= '<style type="text/css">' . $htmlStyle['style'] . '</style>' . "\n";
                    } else {
                        $links .= '<link rel="stylesheet" href="' . $htmlStyle['style'] . '" type="text/css" />' . "\n";
                    }
                }
            }
        }

        return str_ireplace('<head>', '<head>' . "\n" . $links, $htmlContent);
    }

    /**
     * Smarty usage: {load_script context="backend" scripts=$scripts}
     *
     * Custom Smarty function for printing scripts attached to a context.
     *
     * @param array $params associative array
     * @param Smarty $smarty
     *
     * @return string of HTML/Javascript
     */
    public function smartyLoadScript($params, $smarty)
    {
        if (empty($params['context'])) {
            $params['context'] = 'frontend';
        }

        if (!SessionManager::isDisabled()) {
            $versionDao = DAORegistry::getDAO('VersionDAO'); /** @var VersionDAO $versionDao */
            $appVersion = SessionManager::isDisabled() ? null : $versionDao->getCurrentVersion()->getVersionString();
        } else {
            $appVersion = null;
        }

        $scripts = $this->getResourcesByContext($this->_javaScripts, $params['context']);

        ksort($scripts);

        $output = '';
        foreach ($scripts as $priorityList) {
            foreach ($priorityList as $name => $data) {
                if ($data['inline']) {
                    $output .= '<script type="' . $data['type'] . '">' . $data['script'] . '</script>';
                } else {
                    if ($appVersion && strpos($data['script'], '?') === false) {
                        $data['script'] .= '?v=' . $appVersion;
                    }
                    $output .= '<script src="' . $data['script'] . '" type="' . $data['type'] . '"></script>';
                }
            }
        }

        return $output;
    }

    /**
     * Smarty usage: {load_header context="frontend" headers=$headers}
     *
     * Custom Smarty function for printing scripts attached to a context.
     *
     * @param array $params associative array
     * @param Smarty $smarty
     *
     * @return string of HTML/Javascript
     */
    public function smartyLoadHeader($params, $smarty)
    {
        if (empty($params['context'])) {
            $params['context'] = 'frontend';
        }

        $headers = $this->getResourcesByContext($this->_htmlHeaders, $params['context']);

        ksort($headers);

        $output = '';
        foreach ($headers as $priorityList) {
            foreach ($priorityList as $name => $data) {
                $output .= "\n" . $data['header'];
            }
        }

        return $output;
    }

    /**
     * Smarty usage: {load_menu name=$areaName path=$declaredMenuTemplatePath id=$id ulClass=$ulClass liClass=$liClass}
     *
     * Custom Smarty function for printing navigation menu areas attached to a context.
     *
     * @param array $params associative array
     * @param Smarty $smarty
     *
     * @return string of HTML/Javascript
     */
    public function smartyLoadNavigationMenuArea($params, $smarty)
    {
        $areaName = $params['name'];
        $declaredMenuTemplatePath = $params['path'] ?? null;
        $currentContext = $this->_request->getContext();
        $contextId = Application::CONTEXT_ID_NONE;
        if ($currentContext) {
            $contextId = $currentContext->getId();
        }

        // Don't load menus for an area that's not registered by the active theme
        $themePlugins = PluginRegistry::getPlugins('themes');
        if (empty($themePlugins)) {
            $themePlugins = PluginRegistry::loadCategory('themes', true);
        }
        /** @var ThemePlugin[] $themePlugins */
        $activeThemeNavigationAreas = [];
        foreach ($themePlugins as $themePlugin) {
            if ($themePlugin->isActive()) {
                $areas = $themePlugin->getMenuAreas();
                if (!in_array($areaName, $areas)) {
                    return '';
                }
            }
        }

        $menuTemplatePath = 'frontend/components/navigationMenu.tpl';
        if (isset($declaredMenuTemplatePath)) {
            $menuTemplatePath = $declaredMenuTemplatePath;
        }

        $navigationMenuDao = DAORegistry::getDAO('NavigationMenuDAO'); /** @var NavigationMenuDAO $navigationMenuDao */

        $output = '';
        $navigationMenus = $navigationMenuDao->getByArea($contextId, $areaName)->toArray();
        if (isset($navigationMenus[0])) {
            $navigationMenu = $navigationMenus[0];
            Services::get('navigationMenu')->getMenuTree($navigationMenu);
        }


        $this->assign([
            'navigationMenu' => $navigationMenu,
            'id' => $params['id'],
            'ulClass' => $params['ulClass'] ?? '',
            'liClass' => $params['liClass'] ?? '',
        ]);

        return $this->fetch($menuTemplatePath);
    }

    /**
     * Get resources assigned to a context
     *
     * A helper function which retrieves script, style and header assets
     * assigned to a particular context.
     *
     * @param array $resources Requested resources
     * @param string $context Requested context
     *
     * @return array Resources assigned to these contexts
     */
    public function getResourcesByContext($resources, $context)
    {
        $matches = [];

        if (array_key_exists($context, $resources)) {
            $matches = $resources[$context];
        }

        $page = $this->getTemplateVars('requestedPage');
        $page = empty($page) ? 'index' : $page;
        $op = $this->getTemplateVars('requestedOp');
        $op = empty($op) ? 'index' : $op;

        $contexts = [
            join('-', [$context, $page]),
            join('-', [$context, $page, $op]),
        ];

        foreach ($contexts as $context) {
            if (array_key_exists($context, $resources)) {
                foreach ($resources[$context] as $priority => $priorityList) {
                    if (!array_key_exists($priority, $matches)) {
                        $matches[$priority] = [];
                    }
                    $matches[$priority] = array_merge($matches[$priority], $resources[$context][$priority]);
                }
                $matches += $resources[$context];
            }
        }

        return $matches;
    }

    /**
     * Smarty usage: {pluck_files files=$availableFiles by="chapter" value=$chapterId}
     *
     * Custom Smarty function for plucking files from the array of $availableFiles
     * related to a submission. Intended to be used on the frontend
     *
     * @param array $params associative array
     * @param Smarty $smarty
     */
    public function smartyPluckFiles($params, $smarty)
    {
        // The variable to assign the result to.
        if (empty($params['assign'])) {
            error_log('Smarty: {pluck_files} function called without required `assign` param. Called in ' . __FILE__ . ':' . __LINE__);
            return;
        }

        // $params['files'] should be an array of SubmissionFile objects
        if (!is_array($params['files'])) {
            error_log('Smarty: {pluck_files} function called without required `files` param. Called in ' . __FILE__ . ':' . __LINE__);
            $smarty->assign($params['assign'], []);
            return;
        }

        // $params['by'] is one of an approved list of attributes to select by
        if (empty($params['by'])) {
            error_log('Smarty: {pluck_files} function called without required `by` param. Called in ' . __FILE__ . ':' . __LINE__);
            $smarty->assign($params['assign'], []);
            return;
        }

        // The approved list of `by` attributes
        // chapter Any files assigned to a chapter ID. A value of `any` will return files assigned to any chapter. A value of 0 will return files not assigned to chapter
        // publicationFormat Any files in a given publicationFormat ID
        // genre Any files with a genre ID (file genres are configurable but typically refer to Manuscript, Bibliography, etc)
        if (!in_array($params['by'], ['chapter','publicationFormat','fileExtension','genre'])) {
            error_log('Smarty: {pluck_files} function called without a valid `by` param. Called in ' . __FILE__ . ':' . __LINE__);
            $smarty->assign($params['assign'], []);
            return;
        }

        // The value to match against. See docs for `by` param
        if (!isset($params['value'])) {
            error_log('Smarty: {pluck_files} function called without required `value` param. Called in ' . __FILE__ . ':' . __LINE__);
            $smarty->assign($params['assign'], []);
            return;
        }

        $matching_files = [];

        $genreDao = DAORegistry::getDAO('GenreDAO'); /** @var GenreDAO $genreDao */
        foreach ($params['files'] as $file) {
            switch ($params['by']) {
                case 'chapter':
                    $genre = $genreDao->getById($file->getGenreId());
                    if (!$genre->getDependent() && method_exists($file, 'getChapterId')) {
                        if ($params['value'] === 'any' && $file->getChapterId()) {
                            $matching_files[] = $file;
                        } elseif ($file->getChapterId() == $params['value']) {
                            $matching_files[] = $file;
                        } elseif ($params['value'] == 0 && !$file->getChapterId()) {
                            $matching_files[] = $file;
                        }
                    }
                    break;

                case 'publicationFormat':
                    if ($file->getData('assocId') == $params['value']) {
                        $matching_files[] = $file;
                    }
                    break;

                case 'genre':
                    if ($file->getGenreId() == $params['value']) {
                        $matching_files[] = $file;
                    }
                    break;
            }
        }

        $smarty->assign($params['assign'], $matching_files);
    }

    /**
     * Get the direction of a locale
     *
     * @param array $params
     * @param TemplateManager $smarty
     */
    public function smartyLocaleDirection($params, $smarty)
    {
        $locale = empty($params['locale']) ? Locale::getLocale() : $params['locale'];
        return Locale::getMetadata($locale)?->isRightToLeft() ? 'rtl' : 'ltr';
    }

    /**
     * Smarty usage: {html_select_date_a11y legend="Published After" prefix="dateFrom" time=$dateFrom start_year=$yearStart end_year=$yearEnd}
     *
     * Get a fieldset of select fields to select a date
     *
     * Mimics basic features of Smarty's html_select_date function but
     * gives each select field a label and returns all fields within
     * a fieldset in order to be accessible.
     *
     * @param array $params
     * @param TemplateManager $smarty
     *
     * @return string
     */
    public function smartyHtmlSelectDateA11y($params, $smarty)
    {
        if (!isset($params['prefix'], $params['legend'], $params['start_year'], $params['end_year'])) {
            throw new Exception('You must provide a prefix, legend, start_year and end_year when using html_select_date_a11y.');
        }
        $prefix = $params['prefix'];
        $legend = $params['legend'];
        $time = $params['time'] ?? '';
        $startYear = $params['start_year'];
        $endYear = $params['end_year'];
        $yearEmpty = $params['year_empty'] ?? '';
        $monthEmpty = $params['month_empty'] ?? '';
        $dayEmpty = $params['day_empty'] ?? '';
        $yearLabel = $params['year_label'] ?? __('common.year');
        $monthLabel = $params['month_label'] ?? __('common.month');
        $dayLabel = $params['day_label'] ?? __('common.day');

        $years = [];
        $i = $startYear;
        while ($i <= $endYear) {
            $years[$i] = $i;
            $i++;
        }

        $months = [];
        for ($i = 1; $i <= 12; $i++) {
            $months[$i] = date('M', strtotime('2020-' . $i . '-01'));
        }

        $days = [];
        for ($i = 1; $i <= 31; $i++) {
            $days[$i] = $i;
        }

        $currentYear = $currentMonth = $currentDay = '';
        if ($time) {
            $currentYear = (int) substr($time, 0, 4);
            $currentMonth = (int) substr($time, 5, 2);
            $currentDay = (int) substr($time, 8, 2);
        }

        $output = '<fieldset><legend>' . $legend . '</legend>';
        $output .= '<label for="' . $prefix . 'Year">' . $yearLabel . '</label>';
        $output .= '<select id="' . $prefix . 'Year" name="' . $prefix . 'Year">';
        $output .= '<option>' . $yearEmpty . '</option>';
        foreach ($years as $value => $label) {
            $selected = $currentYear === $value ? ' selected' : '';
            $output .= '<option value="' . $value . '"' . $selected . '>' . $label . '</option>';
        }
        $output .= '</select>';
        $output .= '<label for="' . $prefix . 'Month">' . $monthLabel . '</label>';
        $output .= '<select id="' . $prefix . 'Month" name="' . $prefix . 'Month">';
        $output .= '<option>' . $monthEmpty . '</option>';
        foreach ($months as $value => $label) {
            $selected = $currentMonth === $value ? ' selected' : '';
            $output .= '<option value="' . $value . '"' . $selected . '>' . $label . '</option>';
        }
        $output .= '</select>';
        $output .= '<label for="' . $prefix . 'Day">' . $dayLabel . '</label>';
        $output .= '<select id="' . $prefix . 'Day" name="' . $prefix . 'Day">';
        $output .= '<option>' . $dayEmpty . '</option>';
        foreach ($days as $value => $label) {
            $selected = $currentDay === $value ? ' selected' : '';
            $output .= '<option value="' . $value . '"' . $selected . '>' . $label . '</option>';
        }
        $output .= '</select>';
        $output .= '</fieldset>';

        return $output;
    }

    /**
     * Defines the HTTP headers which will be appended to the output once the display() method gets called
     *
     * @param string[] List of formatted headers (['header: content', ...])
     */
    public function setHeaders(array $headers): static
    {
        $this->headers = $headers;
        return $this;
    }

    /**
     * Retrieves the headers
     *
     * @return string[]
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\template\PKPTemplateManager', '\PKPTemplateManager');
    foreach ([
        'CACHEABILITY_NO_CACHE', 'CACHEABILITY_NO_STORE', 'CACHEABILITY_PUBLIC', 'CACHEABILITY_MUST_REVALIDATE', 'CACHEABILITY_PROXY_REVALIDATE',
        'STYLE_SEQUENCE_CORE', 'STYLE_SEQUENCE_NORMAL', 'STYLE_SEQUENCE_LATE', 'STYLE_SEQUENCE_LAST',
        'CSS_FILENAME_SUFFIX',
        'PAGE_WIDTH_NARROW', 'PAGE_WIDTH_NORMAL', 'PAGE_WIDTH_WIDE', 'PAGE_WIDTH_FULL',
    ] as $constantName) {
        define($constantName, constant('\PKPTemplateManager::' . $constantName));
    }
}
