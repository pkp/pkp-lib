<?php

/**
 * @defgroup template Template
 * Implements template management.
 */

/**
 * @file classes/template/PKPTemplateManager.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class TemplateManager
 * @ingroup template
 *
 * @brief Class for accessing the underlying template engine.
 * Currently integrated with Smarty (from http://smarty.php.net/).
 */

use function PHP81_BC\strftime;

/* This definition is required by Smarty */
define('SMARTY_DIR', Core::getBaseDir() . '/lib/pkp/lib/vendor/smarty/smarty/libs/');

require_once('./lib/pkp/lib/vendor/smarty/smarty/libs/plugins/modifier.escape.php'); // Seems to be needed?

define('CACHEABILITY_NO_CACHE',		'no-cache');
define('CACHEABILITY_NO_STORE',		'no-store');
define('CACHEABILITY_PUBLIC',		'public');
define('CACHEABILITY_MUST_REVALIDATE',	'must-revalidate');
define('CACHEABILITY_PROXY_REVALIDATE',	'proxy-revalidate');

define('STYLE_SEQUENCE_CORE', 0);
define('STYLE_SEQUENCE_NORMAL', 10);
define('STYLE_SEQUENCE_LATE', 15);
define('STYLE_SEQUENCE_LAST', 20);

define('CSS_FILENAME_SUFFIX', 'css');

define('PAGE_WIDTH_NARROW', 'narrow');
define('PAGE_WIDTH_NORMAL', 'normal');
define('PAGE_WIDTH_WIDE', 'wide');
define('PAGE_WIDTH_FULL', 'full');

import('lib.pkp.classes.template.PKPTemplateResource');

class PKPTemplateManager extends Smarty {
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
	private $_headers = [];

	/** @var bool Track whether its backend page */
	private $isBackendPage = false;


	/**
	 * Constructor.
	 * Initialize template engine and assign basic template variables.
	 */
	function __construct() {
		parent::__construct();

		// Set up Smarty configuration
		$baseDir = Core::getBaseDir();
		$cachePath = CacheManager::getFileCachePath();

		$this->compile_dir = $cachePath . DIRECTORY_SEPARATOR . 't_compile';
		$this->config_dir = $cachePath . DIRECTORY_SEPARATOR . 't_config';
		$this->cache_dir = $cachePath . DIRECTORY_SEPARATOR . 't_cache';

		$this->_cacheability = CACHEABILITY_NO_STORE; // Safe default

		// Register the template resources.
		$this->registerResource('core', new PKPTemplateResource($coreTemplateDir = 'lib' . DIRECTORY_SEPARATOR . 'pkp' . DIRECTORY_SEPARATOR . 'templates'));
		$this->registerResource('app', new PKPTemplateResource(['templates', $coreTemplateDir]));
		$this->default_resource_type = 'app';

		$this->error_reporting = E_ALL & ~E_NOTICE & ~E_WARNING;
	}

	/**
	 * Initialize the template manager.
	 * @param $request PKPRequest
	 */
	function initialize($request) {
		assert(is_a($request, 'PKPRequest'));
		$this->_request = $request;

		$locale = AppLocale::getLocale();
		$application = Application::get();
		$router = $request->getRouter();
		assert(is_a($router, 'PKPRouter'));

		AppLocale::requireComponents(LOCALE_COMPONENT_APP_COMMON, LOCALE_COMPONENT_PKP_COMMON);
		$currentContext = $request->getContext();

		$this->assign([
			'defaultCharset' => Config::getVar('i18n', 'client_charset'),
			'baseUrl' => $request->getBaseUrl(),
			'currentContext' => $currentContext,
			'currentLocale' => $locale,
			'currentLocaleLangDir' => AppLocale::getLocaleDirection($locale),
			'applicationName' => __($application->getNameKey()),
		]);

		// Assign date and time format
		if ($currentContext) {
			$this->assign(array(
				'dateFormatShort' => $currentContext->getLocalizedDateFormatShort(),
				'dateFormatLong' => $currentContext->getLocalizedDateFormatLong(),
				'datetimeFormatShort' => $currentContext->getLocalizedDateTimeFormatShort(),
				'datetimeFormatLong' => $currentContext->getLocalizedDateTimeFormatLong(),
				'timeFormat' => $currentContext->getLocalizedTimeFormat(),
				'displayPageHeaderTitle' => $currentContext->getLocalizedData('name'),
				'displayPageHeaderLogo' => $currentContext->getLocalizedData('pageHeaderLogoImage'),
				'displayPageHeaderLogoAltText' => $currentContext->getLocalizedData('pageHeaderLogoImageAltText'),
			));
		} else {
			$this->assign(array(
				'dateFormatShort' => Config::getVar('general', 'date_format_short'),
				'dateFormatLong' => Config::getVar('general', 'date_format_long'),
				'datetimeFormatShort' => Config::getVar('general', 'datetime_format_short'),
				'datetimeFormatLong' => Config::getVar('general', 'datetime_format_long'),
				'timeFormat' => Config::getVar('general', 'time_format'),
			));
		}

		if (Config::getVar('general', 'installed') && !$currentContext) {
			$site = $request->getSite();
			$this->assign(array(
				'displayPageHeaderTitle' => $site->getLocalizedTitle(),
				'displayPageHeaderLogo' => $site->getLocalizedData('pageHeaderTitleImage'),
			));
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

		if (Config::getVar('general', 'installed')) {
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

		if (is_a($router, 'PKPPageRouter')) {
			$this->assign([
				'requestedPage' => $router->getRequestedPage($request),
				'requestedOp' => $router->getRequestedOp($request),
			]);

			// A user-uploaded stylesheet
			if ($currentContext) {
				$contextStyleSheet = $currentContext->getData('styleSheet');
				if ($contextStyleSheet) {
					import('classes.file.PublicFileManager');
					$publicFileManager = new PublicFileManager();
					$this->addStyleSheet(
						'contextStylesheet',
						$request->getBaseUrl() . '/' . $publicFileManager->getContextFilesPath($currentContext->getId()) . '/' . $contextStyleSheet['uploadName'] . '?d=' . urlencode($contextStyleSheet['dateUploaded']),
						['priority' => STYLE_SEQUENCE_LATE]
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

				if (Config::getVar('captcha', 'captcha_on_password_reset')) {
					array_push($contexts, 'frontend-login-lostPassword');
				}

				$this->addJavaScript(
					'recaptcha',
					'https://www.recaptcha.net/recaptcha/api.js?hl=' . substr(AppLocale::getLocale(),0,2),
					[
						'contexts' => $contexts,
					]
				);
			}

			// Register meta tags
			if (Config::getVar('general', 'installed')) {
				if (($request->getRequestedPage()=='' || $request->getRequestedPage() == 'index') && $currentContext && $currentContext->getLocalizedData('searchDescription')) {
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
			import('classes.core.Services');
			$nmService = Services::get('navigationMenu');

			if (Config::getVar('general', 'installed')) {
				\HookRegistry::register('LoadHandler', [$nmService, '_callbackHandleCustomNavigationMenuItems']);
			}
		}

		// Register custom functions
		$this->registerPlugin('modifier', 'trim', 'trim');
		$this->registerPlugin('modifier', 'intval', 'intval');
		$this->registerPlugin('modifier', 'json_encode', 'json_encode');
		$this->registerPlugin('modifier', 'uniqid', 'uniqid');
		$this->registerPlugin('modifier', 'substr', 'substr');
		$this->registerPlugin('modifier', 'strstr', 'strstr');
		$this->registerPlugin('modifier', 'substr_replace', 'substr_replace');
		$this->registerPlugin('modifier', 'array_key_first', 'array_key_first');
		$this->registerPlugin('modifier', 'array_values', 'array_values');
		$this->registerPlugin('modifier', 'fatalError', 'fatalError');
		$this->registerPlugin('modifier', 'translate', 'AppLocale::translate');
		$this->registerPlugin('modifier', 'parse_url', 'parse_url');
		$this->registerPlugin('modifier', 'parse_str', 'parse_str');
		$this->registerPlugin('modifier', 'strtok', 'strtok');
		$this->registerPlugin('modifier', 'array_pop', 'array_pop');
		$this->registerPlugin('modifier', 'array_keys', 'array_keys');
		$this->registerPlugin('modifier','strip_unsafe_html', 'PKPString::stripUnsafeHtml');
		$this->registerPlugin('modifier','String_substr', 'PKPString::substr');
		$this->registerPlugin('modifier','dateformatPHP2JQueryDatepicker', 'PKPString::dateformatPHP2JQueryDatepicker');
		$this->registerPlugin('modifier','to_array', [$this, 'smartyToArray']);
		$this->registerPlugin('modifier','compare', [$this, 'smartyCompare']);
		$this->registerPlugin('modifier','concat', [$this, 'smartyConcat']);
		$this->registerPlugin('modifier','strtotime', [$this, 'smartyStrtotime']);
		$this->registerPlugin('modifier','explode', [$this, 'smartyExplode']);
		$this->registerPlugin('modifier','escape', [$this, 'smartyEscape']);
		$this->registerPlugin('function','csrf', [$this, 'smartyCSRF']);
		$this->registerPlugin('function', 'translate', [$this, 'smartyTranslate']);
		$this->registerPlugin('function','null_link_action', [$this, 'smartyNullLinkAction']);
		$this->registerPlugin('function','help', [$this, 'smartyHelp']);
		$this->registerPlugin('function','flush', [$this, 'smartyFlush']);
		$this->registerPlugin('function','call_hook', [$this, 'smartyCallHook']);
		$this->registerPlugin('function','html_options_translate', [$this, 'smartyHtmlOptionsTranslate']);
		$this->registerPlugin('block','iterate', [$this, 'smartyIterate']);
		$this->registerPlugin('function','page_links', [$this, 'smartyPageLinks']);
		$this->registerPlugin('function','page_info', [$this, 'smartyPageInfo']);
		$this->registerPlugin('function','pluck_files', [$this, 'smartyPluckFiles']);
		$this->registerPlugin('function','locale_direction', [$this, 'smartyLocaleDirection']);
		$this->registerPlugin('function','html_select_date_a11y', [$this, 'smartyHtmlSelectDateA11y']);

		$this->registerPlugin('function','title', [$this, 'smartyTitle']);
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
		import('lib.pkp.classes.controllers.listbuilder.ListbuilderHandler');
		$this->setConstants([
			'LISTBUILDER_SOURCE_TYPE_TEXT',
			'LISTBUILDER_SOURCE_TYPE_SELECT',
			'LISTBUILDER_OPTGROUP_LABEL',
		]);

		/**
		 * Kludge to make sure no code that tries to connect to the
		 * database is executed (e.g., when loading installer pages).
		 */
		if (!defined('SESSION_DISABLE_INIT')) {
			$this->assign([
				'isUserLoggedIn' => Validation::isLoggedIn(),
				'isUserLoggedInAs' => Validation::isLoggedInAs(),
				'itemsPerPage' => Config::getVar('interface', 'items_per_page'),
				'numPageLinks' => Config::getVar('interface', 'page_links'),
				'siteTitle' => $request->getSite()->getLocalizedData('title'),
			]);

			$user = $request->getUser();
			if ($user) {
				$notificationDao = DAORegistry::getDAO('NotificationDAO');
				$this->assign([
					'currentUser' => $user,
					// Assign the user name to be used in the sitenav
					'loggedInUsername' => $user->getUserName(),
					// Assign a count of unread tasks
					'unreadNotificationCount' => $notificationDao->getNotificationCount(false, $user->getId(), null, NOTIFICATION_LEVEL_TASK),
				]);
			}
		}

		if (Config::getVar('general', 'installed')) {
			// Respond to the sidebar hook
			if ($currentContext) {
				$this->assign('hasSidebar', !empty($currentContext->getData('sidebar')));
			} else {
				$this->assign('hasSidebar', !empty($request->getSite()->getData('sidebar')));
			}
			HookRegistry::register('Templates::Common::Sidebar', [$this, 'displaySidebar']);

			// Clear the cache whenever the active theme is changed
			HookRegistry::register('Context::edit', [$this, 'clearThemeTemplateCache']);
			HookRegistry::register('Site::edit', [$this, 'clearThemeTemplateCache']);
		}
	}


	/**
	 * Flag the page as cacheable (or not).
	 * @param $cacheability boolean optional
	 */
	function setCacheability($cacheability = CACHEABILITY_PUBLIC) {
		$this->_cacheability = $cacheability;
	}

	/**
	 * Compile a LESS stylesheet
	 *
	 * @param $name string Unique name for this LESS stylesheet
	 * @param $lessFile string Path to the LESS file to compile
	 * @param $args array Optional arguments. Supports:
	 *   'baseUrl': Base URL to use when rewriting URLs in the LESS file.
	 *   'addLess': Array of additional LESS files to parse before compiling
	 * @return string Compiled CSS styles
	 */
	public function compileLess($name, $lessFile, $args = []) {
		$less = new Less_Parser([
			'relativeUrls' => false,
			'compress' => true,
		]);

		$request = $this->_request;

		// Allow plugins to intervene
		HookRegistry::call('PageHandler::compileLess', [&$less, &$lessFile, &$args, $name, $request]);

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
		$less->parse("@baseUrl: '$baseUrl';");

		return $less->getCSS();
	}

	/**
	 * Save LESS styles to a cached file
	 *
	 * @param $path string File path to save the compiled styles
	 * @param styles string CSS styles compiled from the LESS
	 * @return bool success/failure
	 */
	public function cacheLess($path, $styles) {
		if (file_put_contents($path, $styles) === false) {
			error_log("Unable to write \"$path\".");
			return false;
		}

		return true;
	}

	/**
	 * Retrieve the file path for a cached LESS file
	 *
	 * @param $name string Unique name for the LESS file
	 * @return $path string Path to the less file or false if not found
	 */
	public function getCachedLessFilePath($name) {
		$cacheDirectory = CacheManager::getFileCachePath();
		$context = $this->_request->getContext();
		$contextId = is_a($context, 'Context') ? $context->getId() : 0;
		return $cacheDirectory . DIRECTORY_SEPARATOR . $contextId . '-' . $name . '-' . crc32(Application::get()->getRequest()->getBaseUrl()) . '.css';
	}

	/**
	 * Register a stylesheet with the style handler
	 *
	 * @param $name string Unique name for the stylesheet
	 * @param $style string The stylesheet to be included. Should be a URL
	 *   or, if the `inline` argument is included, stylesheet data to be output.
	 * @param $args array Key/value array defining display details
	 *   `priority` int The order in which to print this stylesheet.
	 *      Default: STYLE_SEQUENCE_NORMAL
	 *   `contexts` string|array Where the stylesheet should be loaded.
	 *      Default: array('frontend')
	 *   `inline` bool Whether the $stylesheet value should be output directly as
	 *      stylesheet data. Used to pass backend data to the scripts.
	 */
	function addStyleSheet($name, $style, $args = []) {

		$args = array_merge(
			[
				'priority' => STYLE_SEQUENCE_NORMAL,
				'contexts' => ['frontend'],
				'inline'   => false,
			],
			$args
		);

		$args['contexts'] = (array) $args['contexts'];
		foreach($args['contexts'] as $context) {
			$this->_styleSheets[$context][$args['priority']][$name] = [
				'style' => $style,
				'inline' => $args['inline'],
			];
		}
	}

	/**
	 * Register a script with the script handler
	 *
	 * @param $name string Unique name for the script
	 * @param $script string The script to be included. Should be a URL or, if
	 *   the `inline` argument is included, script data to be output.
	 * @param $args array Key/value array defining display details
	 *   `priority` int The order in which to print this script.
	 *      Default: STYLE_SEQUENCE_NORMAL
	 *   `contexts` string|array Where the script should be loaded.
	 *      Default: array('frontend')
	 *   `inline` bool Whether the $script value should be output directly as
	 *      script data. Used to pass backend data to the scripts.
	 */
	function addJavaScript($name, $script, $args = []) {

		$args = array_merge(
			[
				'priority' => STYLE_SEQUENCE_NORMAL,
				'contexts' => ['frontend'],
				'inline'   => false,
				'type' => 'text/javascript',
			],
			$args
		);

		$args['contexts'] = (array) $args['contexts'];
		foreach($args['contexts'] as $context) {
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
	 * @param $name string Unique name for the header
	 * @param $header string The header to be included.
	 * @param $args array Key/value array defining display details
	 *   `priority` int The order in which to print this header.
	 *      Default: STYLE_SEQUENCE_NORMAL
	 *   `contexts` string|array Where the header should be loaded.
	 *      Default: array('frontend')
	 */
	function addHeader($name, $header, $args = []) {

		$args = array_merge(
			[
				'priority' => STYLE_SEQUENCE_NORMAL,
				'contexts' => ['frontend'],
			],
			$args
		);

		$args['contexts'] = (array) $args['contexts'];
		foreach($args['contexts'] as $context) {
			$this->_htmlHeaders[$context][$args['priority']][$name] = [
				'header' => $header,
			];
		}
	}

	/**
	 * Set constants to be exposed in JavaScript at pkp.const.<constant>
	 *
	 * @param array $names Array of constant names
	 */
	function setConstants($names) {
		foreach ($names as $name) {
			$this->_constants[$name] = constant($name);
		}
	}

	/**
	 * Set locale keys to be exposed in JavaScript at pkp.localeKeys.<key>
	 *
	 * @param array $keys Array of locale keys
	 */
	function setLocaleKeys($keys) {
		foreach ($keys as $key) {
			if (!array_key_exists($key, $this->_localeKeys)) {
				$this->_localeKeys[$key] = __($key);
			}
		}
	}

	/**
	 * Get a piece of the state data
	 *
	 * @param string $key
	 * @return mixed
	 */
	function getState($key) {
		return array_key_exists($key, $this->_state)
			? $this->_state[$key]
			: null;
	}

	/**
	 * Set initial state data to be managed by the Vue.js component on this page
	 *
	 * @param array $data
	 */
	function setState($data) {
		$this->_state = array_merge($this->_state, $data);
	}

	/**
	 * Register all files required by the core JavaScript library
	 */
	function registerJSLibrary() {
		$baseUrl = $this->_request->getBaseUrl();
		$localeChecks = [AppLocale::getLocale(), strtolower(substr(AppLocale::getLocale(), 0, 2))];

		// Common $args array used for all our core JS files
		$args = [
			'priority' => STYLE_SEQUENCE_CORE,
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
			if (file_exists($jqvLocalePath . $localeCheck .'.js')) {
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
				'priority' => STYLE_SEQUENCE_LATE,
				'contexts' => ['backend']
			]
		);

		// Load minified file if it exists
		if (Config::getVar('general', 'enable_minified')) {
			$this->addJavaScript(
				'pkpLib',
				$baseUrl . '/js/pkp.min.js',
				[
					'priority' => STYLE_SEQUENCE_CORE,
					'contexts' => ['backend']
				]
			);
			return;
		}

		// Otherwise retrieve and register all script files
		$minifiedScripts = array_filter(array_map('trim', file('registry/minifiedScripts.txt')), function($s) {
			return strlen($s) && $s[0] != '#'; // Exclude empty and commented (#) lines
		});
		foreach ($minifiedScripts as $key => $script) {
			$this->addJavaScript( 'pkpLib' . $key, "$baseUrl/$script", $args);
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
	function registerJSLibraryData() {

		$context = $this->_request->getContext();

		// Instantiate the namespace
		$output = '$.pkp = $.pkp || {};';

		// Load data intended for general use by the app
		import('lib.pkp.classes.security.Role');

		$app_data = [
			'currentLocale' => AppLocale::getLocale(),
			'primaryLocale' => AppLocale::getPrimaryLocale(),
			'baseUrl' => $this->_request->getBaseUrl(),
			'contextPath' => isset($context) ? $context->getPath() : '',
			'apiBasePath' => '/api/v1',
			'pathInfoEnabled' => Config::getVar('general', 'disable_path_info') ? false : true,
			'restfulUrlsEnabled' => Config::getVar('general', 'restful_urls') ? true : false,
			'tinyMceContentCSS' => $this->_request->getBaseUrl() . '/plugins/generic/tinymce/styles/content.css',
		];

		// Add an array of rtl languages (right-to-left)
		if (Config::getVar('general', 'installed') && !defined('SESSION_DISABLE_INIT')) {
			$allLocales = [];
			if ($context) {
				$allLocales = array_merge(
					$context->getSupportedLocales(),
					$context->getSupportedFormLocales(),
					$context->getSupportedSubmissionLocales()
				);
			} else {
				$allLocales = $this->_request->getSite()->getSupportedLocales();
			}
			$allLocales = array_unique($allLocales);
			$rtlLocales = array_filter($allLocales, function($locale) {
				return AppLocale::getLocaleDirection($locale) === 'rtl';
			});
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
				'priority' => STYLE_SEQUENCE_CORE,
				'contexts' => 'backend',
				'inline'   => true,
			]
		);
	}

	/**
	 * Set up the template requirements for editorial backend pages
	 */
	function setupBackendPage() {
		$this->isBackendPage = true;

		$request = Application::get()->getRequest();
		$dispatcher = $request->getDispatcher();
		$router = $request->getRouter();

		if (empty($this->get_template_vars('pageComponent'))) {
			$this->assign('pageComponent', 'Page');
		}

		$this->setConstants([
			'REALLY_BIG_NUMBER',
			'UPLOAD_MAX_FILESIZE',
			'WORKFLOW_STAGE_ID_PUBLISHED',
			'WORKFLOW_STAGE_ID_SUBMISSION',
			'WORKFLOW_STAGE_ID_INTERNAL_REVIEW',
			'WORKFLOW_STAGE_ID_EXTERNAL_REVIEW',
			'WORKFLOW_STAGE_ID_EDITING',
			'WORKFLOW_STAGE_ID_PRODUCTION',
			'INSERT_TAG_VARIABLE_TYPE_PLAIN_TEXT',
			'INSERT_TAG_VARIABLE_TYPE_SAFE_HTML',
			'ROLE_ID_MANAGER',
			'ROLE_ID_SITE_ADMIN',
			'ROLE_ID_AUTHOR',
			'ROLE_ID_REVIEWER',
			'ROLE_ID_ASSISTANT',
			'ROLE_ID_READER',
			'ROLE_ID_SUB_EDITOR',
			'ROLE_ID_SUBSCRIPTION_MANAGER',
		]);

		// Common locale keys available in the browser for every page
		$this->setLocaleKeys([
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
			'common.loading',
			'common.no',
			'common.noItemsFound',
			'common.none',
			'common.ok',
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

		// Register the jQuery script
		$min = Config::getVar('general', 'enable_minified') ? '.min' : '';
		$this->addJavaScript(
			'jquery',
			$request->getBaseUrl() . '/lib/pkp/lib/vendor/components/jquery/jquery' . $min . '.js',
			[
				'priority' => STYLE_SEQUENCE_CORE,
				'contexts' => 'backend',
			]
		);
		$this->addJavaScript(
			'jqueryUI',
			$request->getBaseUrl() . '/lib/pkp/lib/vendor/components/jqueryui/jquery-ui' . $min . '.js',
			[
				'priority' => STYLE_SEQUENCE_CORE,
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
				'priority' => STYLE_SEQUENCE_CORE,
				'contexts' => 'backend',
			]
		);

		// Stylesheet compiled from Vue.js single-file components
		$this->addStyleSheet(
			'build',
			$request->getBaseUrl() . '/styles/build.css',
			[
				'priority' => STYLE_SEQUENCE_CORE,
				'contexts' => 'backend',
			]
		);

		// The legacy stylesheet for the backend
		$this->addStyleSheet(
			'pkpLib',
			$dispatcher->url($request, ROUTE_COMPONENT, null, 'page.PageHandler', 'css'),
			[
				'priority' => STYLE_SEQUENCE_CORE,
				'contexts' => 'backend',
			]
		);

		// If there's a locale-specific stylesheet, add it.
		if (($localeStyleSheet = AppLocale::getLocaleStyleSheet(AppLocale::getLocale())) != null) {
			$this->addStyleSheet(
				'pkpLibLocale',
				$request->getBaseUrl() . '/' . $localeStyleSheet,
				[
					'contexts' => ['backend'],
				]
			);
		}

		// Set up required state properties
		$this->setState([
			'menu' => [],
		]);

		/**
		 * Kludge to make sure no code that tries to connect to the
		 * database is executed (e.g., when loading installer pages).
		 */
		if (Config::getVar('general', 'installed') && !defined('SESSION_DISABLE_INIT')) {

			if ($request->getUser()) {

				// Get a count of unread tasks
				$notificationDao = DAORegistry::getDAO('NotificationDAO'); /* @var $notificationDao NotificationDAO */
				import('lib.pkp.controllers.grid.notifications.TaskNotificationsGridHandler');
				$unreadTasksCount = (int) $notificationDao->getNotificationCount(false, $request->getUser()->getId(), null, NOTIFICATION_LEVEL_TASK);

				// Get a URL to load the tasks grid
				$tasksUrl = $request->getDispatcher()->url($request, ROUTE_COMPONENT, null, 'page.PageHandler', 'tasks');

				// Load system notifications in SiteHandler.js
				$notificationDao = DAORegistry::getDAO('NotificationDAO'); /* @var $notificationDao NotificationDAO */
				$notificationsCount = count($notificationDao->getByUserId($request->getUser()->getId(), NOTIFICATION_LEVEL_TRIVIAL)->toArray());

				// Load context switcher
				$isAdmin = in_array(ROLE_ID_SITE_ADMIN, $this->get_template_vars('userRoles'));
				if ($isAdmin) {
					$args = [];
				} else {
					$args = ['userId' => $request->getUser()->getId()];
				}
				$availableContexts = Services::get('context')->getManySummary($args);
				if ($request->getContext()) {
					$availableContexts = array_filter($availableContexts, function($context) use ($request) {
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
						$availableContext->url = $dispatcher->url($request, ROUTE_PAGE, $availableContext->urlPath, $request->getRequestedPage(), $requestedOp, $request->getRequestedArgs($request));
					} else {
						$availableContext->url = $dispatcher->url($request, ROUTE_PAGE, $availableContext->urlPath, 'submissions');
					}
				}

				// Create main navigation menu
				$userRoles = (array) $router->getHandler()->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES);

				$menu = [];

				if ($request->getContext()) {
					if (count(array_intersect([ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT, ROLE_ID_REVIEWER, ROLE_ID_AUTHOR], $userRoles))) {
						$menu['submissions'] = [
							'name' => __('navigation.submissions'),
							'url' => $router->url($request, null, 'submissions'),
							'isCurrent' => $router->getRequestedPage($request) === 'submissions',
						];
					} elseif (count($userRoles) === 1 && in_array(ROLE_ID_READER, $userRoles)) {
						AppLocale::requireComponents(LOCALE_COMPONENT_APP_AUTHOR);
						$menu['submit'] = [
							'name' => __('author.submit'),
							'url' => $router->url($request, null, 'submission', 'wizard'),
							'isCurrent' => $router->getRequestedPage($request) === 'submission',
						];
					}

					if (in_array(ROLE_ID_MANAGER, $userRoles)) {
						if ($request->getContext()->getData('enableAnnouncements')) {
							$menu['announcements'] = [
								'name' => __('announcement.announcements'),
								'url' => $router->url($request, null, 'management', 'settings', 'announcements'),
								'isCurrent' => $router->getRequestedPage($request) === 'management' && in_array('announcements', (array) $router->getRequestedArgs($request)),
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

					if (count(array_intersect([ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR], $userRoles))) {
						AppLocale::requireComponents([LOCALE_COMPONENT_PKP_MANAGER]); // pkp/pkp-lib#9721
						$menu['statistics'] = [
							'name' => __('navigation.tools.statistics'),
							'submenu' => [
								'publications' => [
									'name' => __('common.publications'),
									'url' => $router->url($request, null, 'stats', 'publications', 'publications'),
									'isCurrent' => $router->getRequestedPage($request) === 'stats' && $router->getRequestedOp($request) === 'publications',
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
								]
							]
						];
						if (in_array(ROLE_ID_MANAGER, $userRoles)) {
							$menu['statistics']['submenu'] += [
								'reports' => [
									'name' => __('manager.statistics.reports'),
									'url' => $router->url($request, null, 'stats', 'reports'),
									'isCurrent' => $router->getRequestedPage($request) === 'stats' && $router->getRequestedOp($request) === 'reports',
								]
							];
						}
					}

					if (in_array(ROLE_ID_MANAGER, $userRoles)) {
						$menu['tools'] = [
							'name' => __('navigation.tools'),
							'url' => $router->url($request, null, 'management', 'tools'),
							'isCurrent' => $router->getRequestedPage($request) === 'management' && $router->getRequestedOp($request) === 'tools',
						];
					}

					if (in_array(ROLE_ID_SITE_ADMIN, $userRoles)) {
						$menu['admin'] = [
							'name' => __('navigation.admin'),
							'url' => $router->url($request, 'index', 'admin'),
							'isCurrent' => $router->getRequestedPage($request) === 'admin',
						];
					}
				}

				// Load the manager.people.signedInAs locale key
				if (Validation::isLoggedInAs()) {
					AppLocale::requireComponents([LOCALE_COMPONENT_PKP_MANAGER, LOCALE_COMPONENT_APP_MANAGER]);
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

		HookRegistry::call('TemplateManager::setupBackendPage');
	}

	/**
	 * @copydoc Smarty::fetch()
	 */
	function fetch($template = null, $cache_id = null, $compile_id = null, $parent = null) {

		// If no compile ID was assigned, get one.
		if (!$compile_id) $compile_id = $this->getCompileId($template);

		// Give hooks an opportunity to override
		$result = null;
		if (HookRegistry::call('TemplateManager::fetch', [$this, $template, $cache_id, $compile_id, &$result])) return $result;

		return parent::fetch($template, $cache_id, $compile_id, $parent);
	}

	/**
	 * Fetch content via AJAX and add it to the DOM, wrapped in a container element.
	 * @param $id string ID to use for the generated container element.
	 * @param $url string URL to fetch the contents from.
	 * @param $element string Element to use for container.
	 * @return JSONMessage The JSON-encoded result.
	 */
	function fetchAjax($id, $url, $element = 'div') {
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
	 * @param $resourceName string Resource name.
	 * @return string
	 */
	function getCompileId($resourceName) {

		if ( Config::getVar('general', 'installed' ) ) {
			$context = $this->_request->getContext();
			if (is_a($context, 'Context')) {
				$resourceName .= $context->getData('themePluginPath');
			}
		}

		return sha1($resourceName);
	}

	/**
	 * Returns the template results as a JSON message.
	 * @param $template string Template filename (or Smarty resource name)
	 * @param $status boolean
	 * @return JSONMessage JSON object
	 */
	function fetchJson($template, $status = true) {
		import('lib.pkp.classes.core.JSONMessage');
		return new JSONMessage($status, $this->fetch($template));
	}

	/**
	 * @copydoc Smarty::display()
	 */
	function display($template = null, $cache_id = null, $compile_id = null, $parent = null) {

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
				$result = PKPString::stripUnsafeHtml($input, $configKey);
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
		if (Config::getVar('general', 'installed')) {
			$user = $this->_request->getUser();
			if ($user) {
				$userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /* @var $userGroupDao UserGroupDAO */
				$userGroupsResult = $userGroupDao->getByUserId($user->getId());
				$userRoles = [];
				while ($userGroup = $userGroupsResult->next()) {
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
				'priority' => STYLE_SEQUENCE_LATE,
				'contexts' => ['backend'],
				'inline' => true,
			]
		);

		// Give any hooks registered against the TemplateManager
		// the opportunity to modify behavior; otherwise, display
		// the template as usual.
		$output = null;
		if (HookRegistry::call('TemplateManager::display', [$this, &$template, &$output])) {
			echo $output;
			return;
		}

		// Pass the initial state data for this page
		$this->assign('state', $this->_state);

		// Explicitly set the character encoding. Required in
		// case server is using Apache's AddDefaultCharset
		// directive (which can prevent browser auto-detection
		// of the proper character set).
		header('Content-Type: text/html; charset=' . Config::getVar('i18n', 'client_charset'));
		header('Cache-Control: ' . $this->_cacheability);

		foreach ($this->_headers as $header) {
			header($header);
		}

		// If no compile ID was assigned, get one.
		if (!$compile_id) $compile_id = $this->getCompileId($template);

		// Actually display the template.
		parent::display($template, $cache_id, $compile_id, $parent);
	}

	/**
	 * Clear template compile and cache directories.
	 */
	function clearTemplateCache() {
		$this->clearCompiledTemplate();
		$this->clearAllCache();
	}

	/**
	 * Clear all compiled CSS files
	 */
	public function clearCssCache() {
		$cacheDirectory = CacheManager::getFileCachePath();
		$files = scandir($cacheDirectory);
		array_map('unlink', glob(CacheManager::getFileCachePath() . DIRECTORY_SEPARATOR . '*.' . CSS_FILENAME_SUFFIX));
	}

	/**
	 * Clear the cache when a context or site has changed it's active theme
	 *
	 * @param $hookName string
	 * @param $args array [
	 * 	@option Context|Site The new values
	 * 	@option Context|Site The old values
	 * 	@option array Key/value of params that were modified
	 * 	@option Request
	 * ]
	 */
	public function clearThemeTemplateCache($hookName, $args) {
		$newContextOrSite = $args[0];
		$contextOrSite = $args[1];
		if ($newContextOrSite->getData('themePluginPath') !== $contextOrSite->getData('themePluginPath')) {
			$this->clearTemplateCache();
			$this->clearCssCache();
		}
	}

	/**
	 * Return an instance of the template manager.
	 * @param $request PKPRequest
	 * @return TemplateManager the template manager object
	 */
	static function &getManager($request = null) {
		if (!isset($request)) {
			$request = Registry::get('request');
			if (Config::getVar('debug', 'deprecation_warnings')) trigger_error('Deprecated call without request object.');
		}
		assert(is_a($request, 'PKPRequest'));

		$instance =& Registry::get('templateManager', true, null); // Reference required

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
	 * @return TemplateManager the template manager object
	 */
	function getFBV() {
		if(!$this->_fbv) {
			import('lib.pkp.classes.form.FormBuilderVocabulary');
			$this->_fbv = new FormBuilderVocabulary();
		}
		return $this->_fbv;
	}

	/**
	 * Display the sidebar
	 *
	 * @param $hookName string
	 * @param $args array [
	 *		@option array Params passed to the hook
	 *		@option Smarty
	 *		@option string The output
	 * ]
	 */
	public function displaySidebar($hookName, $args) {
		$params =& $args[0];
		$smarty =& $args[1];
		$output =& $args[2];

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


	//
	// Custom template functions, modifiers, etc.
	//

	/**
	 * Smarty usage: {translate key="localization.key.name" [paramName="paramValue" ...]}
	 *
	 * Custom Smarty function for translating localization keys.
	 * Substitution works by replacing tokens like "{$foo}" with the value of the parameter named "foo" (if supplied).
	 * @param $params array associative array, must contain "key" parameter for string to translate plus zero or more named parameters for substitution.
	 * 	Translation variables can be specified also as an optional
	 * 	associative array named "params".
	 * @param $smarty Smarty
	 * @return string the localized string, including any parameter substitutions
	 */
	function smartyTranslate($params, $smarty) {
		if (isset($params) && !empty($params)) {
			if (!isset($params['key'])) return __('');

			$key = $params['key'];
			unset($params['key']);
			if (isset($params['params']) && is_array($params['params'])) {
				$paramsArray = $params['params'];
				unset($params['params']);
				$params = array_merge($params, $paramsArray);
			}
			return __($key, $params);
		}
	}

	/**
	 * Smarty usage: {null_link_action id="linkId" key="localization.key.name" image="imageClassName"}
	 *
	 * Custom Smarty function for displaying a null link action; these will
	 * typically be attached and handled in Javascript.
	 * @param $smarty Smarty
	 * @return string the HTML for the generated link action
	 */
	function smartyNullLinkAction($params, $smarty) {
		assert(isset($params['id']));

		$id = $params['id'];
		$key = isset($params['key'])?$params['key']:null;
		$hoverTitle = isset($params['hoverTitle'])?true:false;
		$image = isset($params['image'])?$params['image']:null;
		$translate = isset($params['translate'])?false:true;

		import('lib.pkp.classes.linkAction.request.NullAction');
		import('lib.pkp.classes.linkAction.LinkAction');
		$key = $translate ? __($key) : $key;
		$this->assign('action', new LinkAction(
			$id, new NullAction(), $key, $image
		));

		$this->assign('hoverTitle', $hoverTitle);
		return $this->fetch('linkAction/linkAction.tpl');
	}

	/**
	 * Smarty usage: {help file="someFile" section="someSection" textKey="some.text.key"}
	 *
	 * Custom Smarty function for displaying a context-sensitive help link.
	 * @param $smarty Smarty
	 * @return string the HTML for the generated link action
	 */
	function smartyHelp($params, $smarty) {
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
	 * @param $params array
	 * @param $smarty Smarty
	 */
	function smartyHtmlOptionsTranslate($params, $smarty) {
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
				$params['options'] = array_map('AppLocale::translate', $params['options']);
			}
		}

		if (isset($params['output'])) {
			$params['output'] = array_map('AppLocale::translate', $params['output']);
		}

		if (isset($params['values']) && isset($params['translateValues'])) {
			$params['values'] = array_map('AppLocale::translate', $params['values']);
		}

		require_once('lib/pkp/lib/vendor/smarty/smarty/libs/plugins/function.html_options.php');
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
	function smartyIterate($params, $content, $smarty, &$repeat) {
		$iterator = $smarty->getTemplateVars($params['from']);

		if (isset($params['key'])) {
			if (empty($content)) $smarty->assign($params['key'], 1);
			else $smarty->assign($params['key'], $smarty->getTemplateVars($params['key'])+1);
		}

		// If the iterator is empty, we're finished.
		if (!$iterator || $iterator->eof()) {
			if (!$repeat) return $content;
			$repeat = false;
			return '';
		}

		$repeat = true;

		if (isset($params['key'])) {
			list($key, $value) = $iterator->nextWithKey();
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
	function smartyPageInfo($params, $smarty) {
		$iterator = $params['iterator'];

		if (isset($params['itemsPerPage'])) {
			$itemsPerPage = $params['itemsPerPage'];
		} else {
			$itemsPerPage = $smarty->getTemplateVars('itemsPerPage');
			if (!is_numeric($itemsPerPage)) $itemsPerPage=25;
		}

		$page = $iterator->getPage();
		$pageCount = $iterator->getPageCount();
		$itemTotal = $iterator->getCount();

		if ($pageCount<1) return '';

		$from = (($page - 1) * $itemsPerPage) + 1;
		$to = min($itemTotal, $page * $itemsPerPage);

		return __('navigation.items', [
			'from' => ($to===0?0:$from),
			'to' => $to,
			'total' => $itemTotal
		]);
	}

	/**
	 * Flush the output buffer. This is useful in cases where Smarty templates
	 * are calling functions that take a while to execute so that they can display
	 * a progress indicator or a message stating that the operation may take a while.
	 */
	function smartyFlush($params, $smarty) {
		$smarty->flush();
	}

	function flush() {
		while (ob_get_level()) {
			ob_end_flush();
		}
		flush();
	}

	/**
	 * Call hooks from a template.
	 */
	function smartyCallHook($params, $smarty) {
		$output = null;
		HookRegistry::call($params['name'], [&$params, $smarty, &$output]);
		return $output;
	}

	/**
	 * Generate a URL into a PKPApp.
	 * @param $params array
	 * @param $smarty object
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
	function smartyUrl($parameters, $smarty) {
		if ( !isset($parameters['context']) ) {
			// Extract the variables named in $paramList, and remove them
			// from the parameters array. Variables remaining in params will be
			// passed along to Request::url as extra parameters.
			$context = [];
			$application = Application::get();
			$contextList = $application->getContextList();
			foreach ($contextList as $contextName) {
				if (isset($parameters[$contextName])) {
					$context[$contextName] = $parameters[$contextName];
					unset($parameters[$contextName]);
				} else {
					$context[$contextName] = null;
				}
			}
			$parameters['context'] = $context;
		}

		// Extract the reserved variables named in $paramList, and remove them
		// from the parameters array. Variables remaining in parameters will be passed
		// along to Request::url as extra parameters.
		$paramList = ['params', 'router', 'context', 'page', 'component', 'op', 'path', 'anchor', 'escape'];
		foreach ($paramList as $parameter) {
			if (isset($parameters[$parameter])) {
				$$parameter = $parameters[$parameter];
				unset($parameters[$parameter]);
			} else {
				$$parameter = null;
			}
		}

		// Merge parameters specified in the {url paramName=paramValue} format with
		// those optionally supplied in {url params=$someAssociativeArray} format
		$parameters = array_merge($parameters, (array) $params);

		// Set the default router
		if (is_null($router)) {
			if (is_a($this->_request->getRouter(), 'PKPComponentRouter')) {
				$router = ROUTE_COMPONENT;
			} else {
				$router = ROUTE_PAGE;
			}
		}

		// Check the router
		$dispatcher = Application::get()->getDispatcher();
		$routerShortcuts = array_keys($dispatcher->getRouterNames());
		assert(in_array($router, $routerShortcuts));

		// Identify the handler
		switch($router) {
			case ROUTE_PAGE:
				$handler = $page;
				break;

			case ROUTE_COMPONENT:
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
	 * @param $parameters array
	 * @param $smarty object
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
	function smartyTitle($parameters, $smarty) {
		$page = $parameters['value'] ?? '';
		if ($smarty->get_template_vars('currentContext')) {
			$siteTitle = $smarty->get_template_vars('currentContext')->getLocalizedData('name');
		} elseif ($smarty->get_template_vars('siteTitle')) {
			$siteTitle = $smarty->get_template_vars('siteTitle');
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
	function smartyPageLinks($params, $smarty) {
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
		if (!is_numeric($numPageLinks)) $numPageLinks=10;

		$page = $iterator->getPage();
		$pageCount = $iterator->getPageCount();

		$pageBase = max($page - floor($numPageLinks / 2), 1);
		$paramName = $name . 'Page';

		if ($pageCount<=1) return '';

		$value = '';

		$router = $this->_request->getRouter();
		$requestedArgs = null;
		if (is_a($router, 'PageRouter')) {
			$requestedArgs = $router->getRequestedArgs($this->_request);
		}

		if ($page>1) {
			$params[$paramName] = 1;
			$value .= '<a href="' . $this->_request->url(null, null, null, $requestedArgs, $params, $anchor) . '"' . $allExtra . '>&lt;&lt;</a>&nbsp;';
			$params[$paramName] = $page - 1;
			$value .= '<a href="' . $this->_request->url(null, null, null, $requestedArgs, $params, $anchor) . '"' . $allExtra . '>&lt;</a>&nbsp;';
		}

		for ($i=$pageBase; $i<min($pageBase+$numPageLinks, $pageCount+1); $i++) {
			if ($i == $page) {
				$value .= "<strong>$i</strong>&nbsp;";
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
	function smartyToArray() {
		return func_get_args();
	}

	/**
	 * Concatenate the parameters and return the result.
	 */
	function smartyConcat() {
		$args = func_get_args();
		return implode('', $args);
	}

	/**
	 * Compare the parameters.
	 * @param $a mixed Parameter A
	 * @param $a mixed Parameter B
	 * @param $strict boolean True iff a strict (===) compare should be used
	 * @param $invert booelan True iff the output should be inverted
	 */
	function smartyCompare($a, $b, $strict = false, $invert = false) {
		$result = $strict?$a===$b:$a==$b;
		return $invert?!$result:$result;
	}

	/**
	 * Convert a string to a numeric time.
	 */
	function smartyStrtotime($string) {
		return strtotime($string);
	}

	/**
	 * Split the supplied string by the supplied separator.
	 */
	function smartyExplode($string, $separator) {
		return explode($separator, $string);
	}

	/**
	 * Override the built-in smarty escape modifier to
	 * add the jqselector escaping method.
	 */
	function smartyEscape($string, $esc_type = 'html', $char_set = 'ISO-8859-1') {
		$pattern = "/(:|\.|\[|\]|,|=|@)/";
		$replacement = "\\\\\\\\$1";
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
	 * @param $params array associative array
	 * @param $smarty Smarty
	 * @return string of HTML/Javascript
	 */
	function smartyLoadUrlInEl($params, $smarty) {
		// Required Params
		if (!isset($params['el'])) {
			throw new Exception("el parameter is missing from load_url_in_el");
		}
		if (!isset($params['url'])) {
			throw new Exception("url parameter is missing from load_url_in_el");
		}
		if (!isset($params['id'])) {
			throw new Exception("id parameter is missing from load_url_in_el");
		}

		$this->assign([
			'inEl' => $params['el'],
			'inElUrl' => $params['url'],
			'inElElId' => $params['id'],
			'inElClass' => isset($params['class'])?$params['class']:null,
			'refreshOn' => isset($params['refreshOn'])?$params['refreshOn']:null,
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
	 * @param $params array associative array
	 * @param $smarty Smarty
	 * @return string of HTML/Javascript
	 */
	function smartyLoadUrlInDiv($params, $smarty) {
		$params['el'] = 'div';
		return $this->smartyLoadUrlInEl( $params, $smarty );
	}

	/**
	 * Smarty usage: {csrf}
	 *
	 * Custom Smarty function for inserting a CSRF token.
	 * @param $params array associative array
	 * @param $smarty Smarty
	 * @return string of HTML
	 */
	function smartyCSRF($params, $smarty) {
		$csrfToken = $this->_request->getSession()->getCSRFToken();
		switch (isset($params['type'])?$params['type']:null) {
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
	 * @param $params array associative array
	 * @param $smarty Smarty
	 * @return string of HTML/Javascript
	 */
	function smartyLoadStylesheet($params, $smarty) {

		if (empty($params['context'])) {
			$params['context'] = 'frontend';
		}

		if (!defined('SESSION_DISABLE_INIT')) {
			$versionDao = DAORegistry::getDAO('VersionDAO'); /* @var $versionDao VersionDAO */
			$appVersion = $versionDao->getCurrentVersion()->getVersionString();
		} else $appVersion = null;

		$stylesheets = $this->getResourcesByContext($this->_styleSheets, $params['context']);

		ksort($stylesheets);

		$output = '';
		foreach($stylesheets as $priorityList) {
			foreach($priorityList as $style) {
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
	 * @param $htmlContent string The HTML file content
	 * @param $embeddedFiles array Additional files embedded in this galley
	 */
	function loadHtmlGalleyStyles($htmlContent, $embeddedFiles) {

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
					$links .= '<link rel="stylesheet" href="' . $htmlStyle['style'] . '" type="text/css" />' . "\n";
				}
			}
		}

		return str_ireplace('<head>', '<head>' . "\n" . $links, $htmlContent);
	}

	/**
	 * Smarty usage: {load_script context="backend" scripts=$scripts}
	 *
	 * Custom Smarty function for printing scripts attached to a context.
	 * @param $params array associative array
	 * @param $smarty Smarty
	 * @return string of HTML/Javascript
	 */
	function smartyLoadScript($params, $smarty) {

		if (empty($params['context'])) {
			$params['context'] = 'frontend';
		}

		if (!defined('SESSION_DISABLE_INIT')) {
			$versionDao = DAORegistry::getDAO('VersionDAO'); /* @var $versionDao VersionDAO */
			$appVersion = defined('SESSION_DISABLE_INIT') ? null : $versionDao->getCurrentVersion()->getVersionString();
		} else $appVersion = null;

		$scripts = $this->getResourcesByContext($this->_javaScripts, $params['context']);

		ksort($scripts);

		$output = '';
		foreach($scripts as $priorityList) {
			foreach($priorityList as $name => $data) {
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
	 * @param $params array associative array
	 * @param $smarty Smarty
	 * @return string of HTML/Javascript
	 */
	function smartyLoadHeader($params, $smarty) {

		if (empty($params['context'])) {
			$params['context'] = 'frontend';
		}

		$headers = $this->getResourcesByContext($this->_htmlHeaders, $params['context']);

		ksort($headers);

		$output = '';
		foreach($headers as $priorityList) {
			foreach($priorityList as $name => $data) {
				$output .= "\n" . $data['header'];
			}
		}

		return $output;
	}

	/**
	 * Smarty usage: {load_menu name=$areaName path=$declaredMenuTemplatePath id=$id ulClass=$ulClass liClass=$liClass}
	 *
	 * Custom Smarty function for printing navigation menu areas attached to a context.
	 * @param $params array associative array
	 * @param $smarty Smarty
	 * @return string of HTML/Javascript
	 */
	function smartyLoadNavigationMenuArea($params, $smarty) {
		$areaName = $params['name'];
		$declaredMenuTemplatePath = $params['path'] ?? null;
		$currentContext = $this->_request->getContext();
		$contextId = CONTEXT_ID_NONE;
		if ($currentContext) {
			$contextId = $currentContext->getId();
		}

		// Don't load menus for an area that's not registered by the active theme
		$themePlugins = PluginRegistry::getPlugins('themes');
		if (empty($themePlugins)) {
			$themePlugins = PluginRegistry::loadCategory('themes', true);
		}
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

		$navigationMenuDao = DAORegistry::getDAO('NavigationMenuDAO'); /* @var $navigationMenuDao NavigationMenuDAO */

		$output = '';
		$navigationMenus = $navigationMenuDao->getByArea($contextId, $areaName)->toArray();
		if (isset($navigationMenus[0])) {
			$navigationMenu = $navigationMenus[0];
			import('classes.core.Services');
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
	 * @param $resources array Requested resources
	 * @param $context string Requested context
	 * @return array Resources assigned to these contexts
	 */
	function getResourcesByContext($resources, $context) {
		$matches = [];

		if (array_key_exists($context, $resources)) {
			$matches = $resources[$context];
		}

		$page = $this->getTemplateVars('requestedPage');
		$page = empty( $page ) ? 'index' : $page;
		$op = $this->getTemplateVars('requestedOp');
		$op = empty( $op ) ? 'index' : $op;

		$contexts = [
			join('-', [$context, $page]),
			join('-', [$context, $page, $op]),
		];

		foreach($contexts as $context) {
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
	 * @param $params array associative array
	 * @param $smarty Smarty
	 * @return array of SubmissionFile objects
	 */
	function smartyPluckFiles($params, $smarty) {

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
		if (!in_array($params['by'], array('chapter','publicationFormat','fileExtension','genre'))) {
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

		$genreDao = DAORegistry::getDAO('GenreDAO'); /* @var $genreDao GenreDAO */
		foreach ($params['files'] as $file) {
			switch ($params['by']) {

				case 'chapter':
					$genre = $genreDao->getById($file->getGenreId());
					if (!$genre->getDependent() && method_exists($file, 'getChapterId')) {
						if ($params['value'] === 'any' && $file->getChapterId()) {
							$matching_files[] = $file;
						} elseif($file->getChapterId() == $params['value']) {
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
	 * @return void
	 */
	public function smartyLocaleDirection($params, $smarty) {
		$locale = !empty($params['locale'])
			? $params['locale']
			: AppLocale::getLocale();
		return AppLocale::getLocaleDirection($locale);
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
	 * @return string
	 */
	public function smartyHtmlSelectDateA11y($params, $smarty) {
		if (!isset($params['prefix'], $params['legend'], $params['start_year'], $params['end_year'])) {
			throw new Exception('You must provide a prefix, legend, start_year and end_year when using html_select_date_a11y.');
		}
		$prefix = $params['prefix'];
		$legend = $params['legend'];
		$time = isset($params['time']) ? $params['time'] : '';
		$startYear = $params['start_year'];
		$endYear = $params['end_year'];
		$yearEmpty = isset($params['year_empty']) ? $params['year_empty'] : '';
		$monthEmpty = isset($params['month_empty']) ? $params['month_empty'] : '';
		$dayEmpty = isset($params['day_empty']) ? $params['day_empty'] : '';
		$yearLabel = isset($params['year_label']) ? $params['year_label'] : __('common.year');
		$monthLabel = isset($params['month_label']) ? $params['month_label'] : __('common.month');
		$dayLabel = isset($params['day_label']) ? $params['day_label'] : __('common.day');

		$years = [];
		$i = $startYear;
		while ($i <= $endYear) {
			$years[$i] = $i;
			$i++;
		}

		$months = [];
		for ($i = 1; $i <= 12; $i++) {
			$months[$i] = strftime('%B', strtotime('2020-' . $i . '-01'));
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
			$output .= '<option value="'. $value . '"' . $selected . '>' . $label . '</option>';
		}
		$output .= '</select>';
		$output .= '<label for="' . $prefix . 'Month">' . $monthLabel . '</label>';
		$output .= '<select id="' . $prefix . 'Month" name="' . $prefix . 'Month">';
		$output .= '<option>' . $monthEmpty . '</option>';
		foreach ($months as $value => $label) {
			$selected = $currentMonth === $value ? ' selected' : '';
			$output .= '<option value="'. $value . '"' . $selected . '>' . $label . '</option>';
		}
		$output .= '</select>';
		$output .= '<label for="' . $prefix . 'Day">' . $dayLabel . '</label>';
		$output .= '<select id="' . $prefix . 'Day" name="' . $prefix . 'Day">';
		$output .= '<option>' . $dayEmpty . '</option>';
		foreach ($days as $value => $label) {
			$selected = $currentDay === $value ? ' selected' : '';
			$output .= '<option value="'. $value . '"' . $selected . '>' . $label . '</option>';
		}
		$output .= '</select>';
		$output .= '</fieldset>';

		return $output;
	}

	/**
	 * DEPRECATED wrapper for Smarty2 backwards compatibility
	 * @param $varname
	 */
	public function get_template_vars($varname = null) {
		if (Config::getVar('debug', 'deprecation_warnings')) trigger_error('Deprecated call to Smarty2 function ' .  __FUNCTION__);
		return $this->getTemplateVars($varname);
	}

	/**
	 * DEPRECATED wrapper for Smarty2 backwards compatibility
	 * @param $name
	 * @param $impl
	 * @param $cacheable
	 * @param $cache_attrs
	 */
	public function register_function($name, $impl, $cacheable = true, $cache_attrs = null) {
		if (Config::getVar('debug', 'deprecation_warnings')) trigger_error('Deprecated call to Smarty2 function ' .  __FUNCTION__);
		$this->registerPlugin('function', $name, $impl, $cacheable, $cache_attrs);
	}

	/**
	 * Defines the HTTP headers which will be appended to the output once the display() method gets called
	 * @param string[] List of formatted headers (['header: content', ...])
	 */
	public function setHeaders(array $headers): self
	{
		$this->_headers = $headers;
		return $this;
	}

	/**
	 * Retrieves the headers
	 *
	 * @return string[]
	 */
	public function getHeaders(): array
	{
		return $this->_headers;
	}
}
