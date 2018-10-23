<?php

/**
 * @file pages/management/ManagementHandler.inc.php
 *
 * Copyright (c) 2013-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ManagementHandler
 * @ingroup pages_management
 *
 * @brief Base class for all management page handlers.
 */

// Import the base Handler.
import('classes.handler.Handler');

class ManagementHandler extends Handler {

	//
	// Overridden methods from Handler
	//
	/**
	 * @see PKPHandler::initialize()
	 */
	function initialize($request) {
		parent::initialize($request);

		// Load manager locale components.
		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_MANAGER, LOCALE_COMPONENT_APP_MANAGER, LOCALE_COMPONENT_PKP_GRID);
	}

	/**
	 * @see PKPHandler::authorize()
	 * @param $request PKPRequest
	 * @param $args array
	 * @param $roleAssignments array
	 */
	function authorize($request, &$args, $roleAssignments) {
		import('lib.pkp.classes.security.authorization.ContextAccessPolicy');
		$this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));
		return parent::authorize($request, $args, $roleAssignments);
	}

	/**
	 * Route requests to the appropriate operation.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function settings($args, $request) {
		$path = array_shift($args);
		switch($path) {
			case 'index':
			case '':
			case 'context':
				$this->context($args, $request);
				break;
			case 'website':
				$this->website($args, $request);
				break;
			case 'workflow':
				$this->workflow($args, $request);
				break;
			case 'distribution':
				$this->distribution($args, $request);
				break;
			case 'access':
				$this->access($args, $request);
				break;
			case 'announcements':
				$this->announcements($args, $request);
				break;
			default:
				$request->getDispatcher()->handle404();
				assert(false);
		}
	}

	/**
	 * Display settings for a journal/press
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function context($args, $request) {
		$templateMgr = TemplateManager::getManager($request);
		$this->setupTemplate($request);
		$context = $request->getContext();
		$router = $request->getRouter();

		$apiUrl = $router->getApiUrl($request, $context->getPath(), 'v1', 'contexts', $context->getId());

		$supportedFormLocales = $context->getSupportedFormLocales();
		$localeNames = AppLocale::getAllLocales();
		$locales = array_map(function($localeKey) use ($localeNames) {
			return ['key' => $localeKey, 'label' => $localeNames[$localeKey]];
		}, $supportedFormLocales);

		import('lib.pkp.components.forms.context.PKPContactForm');
		$contactForm = new PKPContactForm($apiUrl, $locales, $context);
		import('components.forms.context.MastheadForm');
		$mastheadForm = new MastheadForm($apiUrl, $locales, $context);

		$settingsData = [
			'forms' => [
				FORM_CONTACT => $contactForm->getConfig(),
				FORM_MASTHEAD => $mastheadForm->getConfig(),
			],
		];

		$templateMgr->assign('settingsData', $settingsData);

		// Display a warning message if there is a new version of OJS available
		if (Config::getVar('general', 'show_upgrade_warning')) {
			import('lib.pkp.classes.site.VersionCheck');
			if ($latestVersion = VersionCheck::checkIfNewVersionExists()) {
				$templateMgr->assign('newVersionAvailable', true);
				$templateMgr->assign('latestVersion', $latestVersion);
				$currentVersion = VersionCheck::getCurrentDBVersion();
				$templateMgr->assign('currentVersion', $currentVersion->getVersionString());

				// Get contact information for site administrator
				$roleDao = DAORegistry::getDAO('RoleDAO');
				$siteAdmins = $roleDao->getUsersByRoleId(ROLE_ID_SITE_ADMIN);
				$templateMgr->assign('siteAdmin', $siteAdmins->next());
			}
		}

		$templateMgr->display('management/context.tpl');
	}

	/**
	 * Display website settings
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function website($args, $request) {
		$templateMgr = TemplateManager::getManager($request);
		$this->setupTemplate($request);
		$context = $request->getContext();
		$router = $request->getRouter();

		$contextApiUrl = $router->getApiUrl($request, $context->getPath(), 'v1', 'contexts', $context->getId());
		$themeApiUrl = $router->getApiUrl($request, $context->getPath(), 'v1', 'contexts', $context->getId() . '/theme');
		$temporaryFileApiUrl = $router->getApiUrl($request, $context->getPath(), 'v1', 'temporaryFiles');
		$contextUrl = $router->url($request, $context->getPath());

		import('classes.file.PublicFileManager');
		$publicFileManager = new PublicFileManager();
		$baseUrl = $request->getBaseUrl() . '/' . $publicFileManager->getContextFilesPath($context->getAssocType(), $context->getId());

		$supportedFormLocales = $context->getSupportedFormLocales();
		$localeNames = AppLocale::getAllLocales();
		$locales = array_map(function($localeKey) use ($localeNames) {
			return ['key' => $localeKey, 'label' => $localeNames[$localeKey]];
		}, $supportedFormLocales);

		import('lib.pkp.components.forms.context.PKPAnnouncementSettingsForm');
		$announcementSettingsForm = new PKPAnnouncementSettingsForm($contextApiUrl, $locales, $context);
		import('lib.pkp.components.forms.context.PKPAppearanceAdvancedForm');
		$appearanceAdvancedForm = new PKPAppearanceAdvancedForm($contextApiUrl, $locales, $context, $baseUrl, $temporaryFileApiUrl);
		import('components.forms.context.AppearanceSetupForm');
		$appearanceSetupForm = new AppearanceSetupForm($contextApiUrl, $locales, $context, $baseUrl, $temporaryFileApiUrl);
		import('lib.pkp.components.forms.context.PKPInformationForm');
		$informationForm = new PKPInformationForm($contextApiUrl, $locales, $context);
		import('lib.pkp.components.forms.context.PKPListsForm');
		$listsForm = new PKPListsForm($contextApiUrl, $locales, $context);
		import('lib.pkp.components.forms.context.PKPPrivacyForm');
		$privacyForm = new PKPPrivacyForm($contextApiUrl, $locales, $context);
		import('lib.pkp.components.forms.context.PKPThemeForm');
		$themeForm = new PKPThemeForm($themeApiUrl, $locales, $contextUrl, $context);

		$settingsData = [
			'forms' => [
				FORM_ANNOUNCEMENT_SETTINGS => $announcementSettingsForm->getConfig(),
				FORM_APPEARANCE_ADVANCED => $appearanceAdvancedForm->getConfig(),
				FORM_APPEARANCE_SETUP => $appearanceSetupForm->getConfig(),
				FORM_INFORMATION => $informationForm->getConfig(),
				FORM_LISTS => $listsForm->getConfig(),
				FORM_PRIVACY => $privacyForm->getConfig(),
				FORM_THEME => $themeForm->getConfig(),
			],
		];

		$templateMgr->assign('settingsData', $settingsData);

		$templateMgr->display('management/website.tpl');
	}

	/**
	 * Display workflow settings
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function workflow($args, $request) {
		$templateMgr = TemplateManager::getManager($request);
		$this->setupTemplate($request);
		$context = $request->getContext();
		$router = $request->getRouter();

		$apiUrl = $router->getApiUrl($request, $context->getPath(), 'v1', 'contexts', $context->getId());

		AppLocale::requireComponents(
			LOCALE_COMPONENT_PKP_SUBMISSION,
			LOCALE_COMPONENT_APP_SUBMISSION,
			LOCALE_COMPONENT_PKP_EDITOR,
			LOCALE_COMPONENT_APP_EDITOR,
			LOCALE_COMPONENT_PKP_MANAGER,
			LOCALE_COMPONENT_APP_MANAGER
		);

		$supportedFormLocales = $context->getSupportedFormLocales();
		$localeNames = AppLocale::getAllLocales();
		$locales = array_map(function($localeKey) use ($localeNames) {
			return ['key' => $localeKey, 'label' => $localeNames[$localeKey]];
		}, $supportedFormLocales);

		import('lib.pkp.components.forms.context.PKPAuthorGuidelinesForm');
		$authorGuidelinesForm = new PKPAuthorGuidelinesForm($apiUrl, $locales, $context);
		import('lib.pkp.components.forms.context.PKPMetadataSettingsForm');
		$metadataSettingsForm = new PKPMetadataSettingsForm($apiUrl, $context);
		import('lib.pkp.components.forms.context.PKPEmailSetupForm');
		$emailSetupForm = new PKPEmailSetupForm($apiUrl, $locales, $context);
		import('components.forms.context.ReviewGuidanceForm');
		$reviewGuidanceForm = new ReviewGuidanceForm($apiUrl, $locales, $context);
		import('lib.pkp.components.forms.context.PKPReviewSetupForm');
		$reviewSetupForm = new PKPReviewSetupForm($apiUrl, $locales, $context);

		$settingsData = [
			'forms' => [
				FORM_AUTHOR_GUIDELINES => $authorGuidelinesForm->getConfig(),
				FORM_METADATA_SETTINGS => $metadataSettingsForm->getConfig(),
				FORM_EMAIL_SETUP => $emailSetupForm->getConfig(),
				FORM_REVIEW_GUIDANCE => $reviewGuidanceForm->getConfig(),
				FORM_REVIEW_SETUP => $reviewSetupForm->getConfig(),
			],
		];
		$templateMgr->assign('settingsData', $settingsData);

		$templateMgr->display('management/workflow.tpl');
	}

	/**
	 * Display distribution settings
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function distribution($args, $request) {
		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_SUBMISSION, LOCALE_COMPONENT_APP_SUBMISSION);
		$templateMgr = TemplateManager::getManager($request);
		$this->setupTemplate($request);
		$context = $request->getContext();
		$router = $request->getRouter();
		$dispatcher = $request->getDispatcher();

		$apiUrl = $router->getApiUrl($request, $context->getPath(), 'v1', 'contexts', $context->getId());
		$lockssUrl = $router->url($request, $context->getPath(), 'gateway', 'lockss');
		$clockssUrl = $router->url($request, $context->getPath(), 'gateway', 'clockss');
		$sitemapUrl = $router->url($request, $context->getPath(), 'sitemap');
		$paymentsUrl = $router->getApiUrl($request, $context->getPath(), 'v1', '_payments');

		$supportedFormLocales = $context->getSupportedFormLocales();
		$localeNames = AppLocale::getAllLocales();
		$locales = array_map(function($localeKey) use ($localeNames) {
			return ['key' => $localeKey, 'label' => $localeNames[$localeKey]];
		}, $supportedFormLocales);

		import('components.forms.context.ArchivingLockssForm');
		$archivingLockssForm = new ArchivingLockssForm($apiUrl, $locales, $context, $lockssUrl, $clockssUrl);
		import('lib.pkp.components.forms.context.PKPLicenseForm');
		$licenseForm = new PKPLicenseForm($apiUrl, $locales, $context);
		import('lib.pkp.components.forms.context.PKPSearchIndexingForm');
		$searchIndexingForm = new PKPSearchIndexingForm($apiUrl, $locales, $context, $sitemapUrl);
		import('components.forms.context.AccessForm');
		$accessForm = new AccessForm($apiUrl, $locales, $context);
		import('components.forms.context.PaymentSettingsForm');
		$paymentSettingsForm = new PaymentSettingsForm($apiUrl, $locales, $context);

		// Create a dummy "form" for the PKP Preservation Network settings. This
		// form loads a single field which enables/disables the plugin, and does
		// not need to be submitted. It's a dirty hack, but we can change this once
		// an API is in place for plugins and plugin settings.
		$versionDao = DAORegistry::getDAO('VersionDAO');
		$isPlnInstalled = $versionDao->getCurrentVersion('plugins.generic', 'pln', true);
		$archivePnForm = new FormComponent('archivePn', 'PUT', 'dummy', 'dummy');
		$archivePnForm->addPage([
				'id' => 'default',
				'submitButton' => null,
			])
			->addGroup([
				'id' => 'default',
				'pageId' => 'default',
			]);

		if (!$isPlnInstalled) {
			$archivePnForm->addField(new FieldHTML('pn', [
				'label' => __('manager.setup.plnPluginArchiving'),
				'value' => __('manager.setup.plnPluginNotInstalled'),
				'groupId' => 'default',
			]));
		} else {
			$plnPlugin = PluginRegistry::getPlugin('generic', 'plnplugin');
			$pnEnablePluginUrl = $dispatcher->url($request, ROUTE_COMPONENT, null, 'grid.settings.plugins.SettingsPluginGridHandler', 'enable', null, array('plugin' => 'plnplugin', 'category' => 'generic'));
			$pnDisablePluginUrl = $dispatcher->url($request, ROUTE_COMPONENT, null, 'grid.settings.plugins.SettingsPluginGridHandler', 'disable', null, array('plugin' => 'plnplugin', 'category' => 'generic'));
			$pnSettingsUrl = $dispatcher->url($request, ROUTE_COMPONENT, null, 'grid.settings.plugins.SettingsPluginGridHandler', 'manage', null, array('verb' => 'settings', 'plugin' => 'plnplugin', 'category' => 'generic'));

			$archivePnForm->addField(new FieldArchivingPn('pn', [
				'label' => __('manager.setup.plnPluginArchiving'),
				'description' => __('manager.setup.plnDescription'),
				'terms' => __('manager.setup.plnSettingsDescription'),
				'options' => [
					[
						'value' => true,
						'label' => __('manager.setup.plnPluginEnable'),
					],
				],
				'value' => (bool) $plnPlugin,
				'enablePluginUrl' => $pnEnablePluginUrl,
				'disablePluginUrl' => $pnDisablePluginUrl,
				'settingsUrl' => $pnSettingsUrl,
				'csrfToken' => $request->getSession()->getCSRFToken(),
				'groupId' => 'default',
				'i18n' => [
					'enablePluginError' => __('api.submissions.unknownError'),
					'enablePluginSuccess' => __('common.pluginEnabled', ['pluginName' => __('manager.setup.plnPluginArchiving')]),
					'disablePluginSuccess' => __('common.pluginDisabled', ['pluginName' => __('manager.setup.plnPluginArchiving')]),
				],
			]));
		}

		$settingsData = [
			'forms' => [
				FORM_ARCHIVING_LOCKSS => $archivingLockssForm->getConfig(),
				$archivePnForm->id => $archivePnForm->getConfig(),
				FORM_LICENSE => $licenseForm->getConfig(),
				FORM_SEARCH_INDEXING => $searchIndexingForm->getConfig(),
				FORM_ACCESS => $accessForm->getConfig(),
				FORM_PAYMENT_SETTINGS => $paymentSettingsForm->getConfig(),
			],
		];
		$templateMgr->assign('settingsData', $settingsData);
	}

	/**
	 * Display list of announcements and announcement types
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function announcements($args, $request) {
		$templateMgr = TemplateManager::getManager($request);
		$this->setupTemplate($request);

		$templateMgr->display('management/announcements.tpl');
	}

	/**
	 * Display Access and Security page.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function access($args, $request) {
		$templateMgr = TemplateManager::getManager($request);
		$this->setupTemplate($request);
		$context = $request->getContext();
		$router = $request->getRouter();

		$apiUrl = $router->getApiUrl($request, $context->getPath(), 'v1', 'contexts', $context->getId());

		import('components.forms.context.UserAccessForm');
		$userAccessForm = new UserAccessForm($apiUrl, $context);

		$settingsData = [
			'forms' => [
				FORM_USER_ACCESS => $userAccessForm->getConfig(),
			],
		];
		$templateMgr->assign('settingsData', $settingsData);

		$templateMgr->display('management/access.tpl');
	}
}
