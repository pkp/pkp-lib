<?php

/**
 * @file pages/admin/AdminHandler.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class AdminHandler
 * @ingroup pages_admin
 *
 * @brief Handle requests for site administration functions.
 */

import('classes.handler.Handler');

class AdminHandler extends Handler {
	/**
	 * Constructor
	 */
	function __construct() {
		parent::__construct();

		$this->addRoleAssignment(
			array(ROLE_ID_SITE_ADMIN),
			array('index', 'contexts', 'settings', 'wizard')
		);
	}

	/**
	 * @copydoc PKPHandler::authorize()
	 */
	function authorize($request, &$args, $roleAssignments) {
		import('lib.pkp.classes.security.authorization.PKPSiteAccessPolicy');
		$this->addPolicy(new PKPSiteAccessPolicy($request, null, $roleAssignments));
		$returner = parent::authorize($request, $args, $roleAssignments);

		// Make sure user is in a context. Otherwise, redirect.
		$context = $request->getContext();
		$router = $request->getRouter();
		$requestedOp = $router->getRequestedOp($request);

		if ($requestedOp == 'settings') {
			$contextDao = Application::getContextDAO();
			$contextFactory = $contextDao->getAll();
			if ($contextFactory->getCount() == 1) {
				// Don't let users access site settings in a single context installation.
				// In that case, those settings are available under management or are not
				// relevant (like site appearance).
				return false;
			}
		}

		return $returner;
	}

	/**
	 * Display site admin index page.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function index($args, $request) {
		$this->setupTemplate($request);
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->display('admin/index.tpl');
	}

	/**
	 * Display a list of the contexts hosted on the site.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function contexts($args, $request) {
		$this->setupTemplate($request);
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->display('admin/contexts.tpl');
	}

	/**
	 * Display the administration settings page.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function settings($args, $request) {
		$this->setupTemplate($request);
		$templateMgr = TemplateManager::getManager($request);
		$site = $request->getSite();
		$router = $request->getRouter();

		$apiUrl = $router->getApiUrl($request, '*', 'v1', 'site');
		$themeApiUrl = $router->getApiUrl($request, '*', 'v1', 'site', 'theme');
		$temporaryFileApiUrl = $router->getApiUrl($request, '*', 'v1', 'temporaryFiles');
		$siteUrl = $request->getBaseUrl();

		import('classes.file.PublicFileManager');
		$publicFileManager = new PublicFileManager();
		$baseUrl = $request->getBaseUrl() . '/' . $publicFileManager->getSiteFilesPath();

		$supportedLocales = $site->getSupportedLocales();
		$localeNames = AppLocale::getAllLocales();
		$locales = array_map(function($localeKey) use ($localeNames) {
			return ['key' => $localeKey, 'label' => $localeNames[$localeKey]];
		}, $supportedLocales);

		import('components.forms.site.SiteAppearanceForm');
		$siteAppearanceForm = new SiteAppearanceForm($apiUrl, $locales, $site, $baseUrl, $temporaryFileApiUrl);
		import('components.forms.site.SiteConfigForm');
		$siteConfigForm = new SiteConfigForm($apiUrl, $locales, $site);
		import('components.forms.site.SiteInformationForm');
		$siteInformationForm = new SiteInformationForm($apiUrl, $locales, $site);
		import('lib.pkp.components.forms.context.PKPThemeForm');
		$themeForm = new PKPThemeForm($themeApiUrl, $locales, $siteUrl);

		$settingsData = [
			'forms' => [
				FORM_SITE_APPEARANCE => $siteAppearanceForm->getConfig(),
				FORM_SITE_CONFIG => $siteConfigForm->getConfig(),
				FORM_SITE_INFO => $siteInformationForm->getConfig(),
				FORM_THEME => $themeForm->getConfig(),
			],
		];

		$templateMgr->assign('settingsData', $settingsData);

		$templateMgr->display('admin/settings.tpl');
	}

	/**
	 * Display a settings wizard for a journal
	 *
	 * @param $args array
	 * @param $request PKPRequest
	 */
	public function wizard($args, $request) {
		$this->setupTemplate($request);
		$router = $request->getRouter();

		if (!isset($args[0]) || !ctype_digit($args[0])) {
			$request->getDispatcher()->handle404();
		}

		import('classes.core.ServicesContainer');
		$contextService = ServicesContainer::instance()->get('context');
		$context = $contextService->getContext((int) $args[0]);

		if (empty($context)) {
			$request->getDispatcher()->handle404();
		}

		$apiUrl = $router->getApiUrl($request, $context->getPath(), 'v1', 'contexts', $context->getId());
		$themeApiUrl = $router->getApiUrl($request, $context->getPath(), 'v1', 'contexts', $context->getId() . '/theme');
		$contextUrl = $router->url($request, $context->getPath());
		$sitemapUrl = $router->url($request, $context->getPath(), 'sitemap');

		$supportedFormLocales = $context->getSupportedFormLocales();
		$localeNames = AppLocale::getAllLocales();
		$locales = array_map(function($localeKey) use ($localeNames) {
			return ['key' => $localeKey, 'label' => $localeNames[$localeKey]];
		}, $supportedFormLocales);

		import('components.forms.context.ContextForm');
		$contextForm = new ContextForm($apiUrl, __('admin.contexts.form.edit.success'), $locales, $request->getBaseUrl(), $context);
		import('lib.pkp.components.forms.context.PKPThemeForm');
		$themeForm = new PKPThemeForm($themeApiUrl, $locales, $contextUrl, $context);
		import('lib.pkp.components.forms.context.PKPSearchIndexingForm');
		$indexingForm = new PKPSearchIndexingForm($apiUrl, $locales, $context, $sitemapUrl);

		$settingsData = [
			'forms' => [
				FORM_CONTEXT => $contextForm->getConfig(),
				FORM_SEARCH_INDEXING => $indexingForm->getConfig(),
				FORM_THEME => $themeForm->getConfig(),
			],
		];

		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign([
			'settingsData' => $settingsData,
			'editContext' => $context,
		]);

		$templateMgr->display('admin/contextSettings.tpl');
	}

	/**
	 * Initialize the handler.
	 * @param $request PKPRequest
	 */
	function initialize($request) {
		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_ADMIN, LOCALE_COMPONENT_APP_MANAGER, LOCALE_COMPONENT_APP_ADMIN, LOCALE_COMPONENT_APP_COMMON, LOCALE_COMPONENT_PKP_MANAGER);
		return parent::initialize($request);
	}
}
