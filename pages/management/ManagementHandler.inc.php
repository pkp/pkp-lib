<?php

/**
 * @file pages/management/ManagementHandler.inc.php
 *
 * Copyright (c) 2013-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
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
		$dispatcher = $request->getDispatcher();

		$apiUrl = $dispatcher->url($request, ROUTE_API, $context->getPath(), 'contexts/' . $context->getId());

		$supportedFormLocales = $context->getSupportedFormLocales();
		$localeNames = AppLocale::getAllLocales();
		$locales = array_map(function($localeKey) use ($localeNames) {
			return ['key' => $localeKey, 'label' => $localeNames[$localeKey]];
		}, $supportedFormLocales);

		$contactForm = new PKP\components\forms\context\PKPContactForm($apiUrl, $locales, $context);
		$mastheadForm = new APP\components\forms\context\MastheadForm($apiUrl, $locales, $context);

		$settingsData = [
			'components' => [
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

		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_SUBMISSION);
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
		$dispatcher = $request->getDispatcher();
		$router = $request->getRouter();

		$contextApiUrl = $dispatcher->url($request, ROUTE_API, $context->getPath(), 'contexts/' . $context->getId());
		$themeApiUrl = $dispatcher->url($request, ROUTE_API, $context->getPath(), 'contexts/' . $context->getId() . '/theme');
		$temporaryFileApiUrl = $dispatcher->url($request, ROUTE_API, $context->getPath(), 'temporaryFiles');
		$contextUrl = $router->url($request, $context->getPath());

		import('classes.file.PublicFileManager');
		$publicFileManager = new PublicFileManager();
		$baseUrl = $request->getBaseUrl() . '/' . $publicFileManager->getContextFilesPath($context->getId());

		$supportedFormLocales = $context->getSupportedFormLocales();
		$localeNames = AppLocale::getAllLocales();
		$locales = array_map(function($localeKey) use ($localeNames) {
			return ['key' => $localeKey, 'label' => $localeNames[$localeKey]];
		}, $supportedFormLocales);

		$announcementSettingsForm = new \PKP\components\forms\context\PKPAnnouncementSettingsForm($contextApiUrl, $locales, $context);
		$appearanceAdvancedForm = new \APP\components\forms\context\AppearanceAdvancedForm($contextApiUrl, $locales, $context, $baseUrl, $temporaryFileApiUrl);
		$appearanceSetupForm = new \APP\components\forms\context\AppearanceSetupForm($contextApiUrl, $locales, $context, $baseUrl, $temporaryFileApiUrl);
		$informationForm = new \PKP\components\forms\context\PKPInformationForm($contextApiUrl, $locales, $context);
		$listsForm = new \PKP\components\forms\context\PKPListsForm($contextApiUrl, $locales, $context);
		$privacyForm = new \PKP\components\forms\context\PKPPrivacyForm($contextApiUrl, $locales, $context);
		$themeForm = new \PKP\components\forms\context\PKPThemeForm($themeApiUrl, $locales, $contextUrl, $context);

		$settingsData = [
			'components' => [
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
		$dispatcher = $request->getDispatcher();

		$contextApiUrl = $dispatcher->url($request, ROUTE_API, $context->getPath(), 'contexts/' . $context->getId());
		$emailTemplatesApiUrl = $dispatcher->url($request, ROUTE_API, $context->getPath(), 'emailTemplates');

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

		$authorGuidelinesForm = new \PKP\components\forms\context\PKPAuthorGuidelinesForm($contextApiUrl, $locales, $context);
		$metadataSettingsForm = new \PKP\components\forms\context\PKPMetadataSettingsForm($contextApiUrl, $context);
		$emailSetupForm = new \PKP\components\forms\context\PKPEmailSetupForm($contextApiUrl, $locales, $context);
		$reviewGuidanceForm = new \APP\components\forms\context\ReviewGuidanceForm($contextApiUrl, $locales, $context);
		$reviewSetupForm = new \PKP\components\forms\context\PKPReviewSetupForm($contextApiUrl, $locales, $context);

		$emailTemplatesListPanel = new \APP\components\listPanels\EmailTemplatesListPanel(
			'emailTemplates',
			__('manager.emails.emailTemplates'),
			[
				'apiUrl' => $emailTemplatesApiUrl,
				'count' => 100,
				'items' => [],
				'itemsMax' => 0,
				'lazyLoad' => true,
			]
		);

		$settingsData = [
			'components' => [
				FORM_AUTHOR_GUIDELINES => $authorGuidelinesForm->getConfig(),
				FORM_METADATA_SETTINGS => $metadataSettingsForm->getConfig(),
				FORM_EMAIL_SETUP => $emailSetupForm->getConfig(),
				FORM_REVIEW_GUIDANCE => $reviewGuidanceForm->getConfig(),
				FORM_REVIEW_SETUP => $reviewSetupForm->getConfig(),
				'emailTemplates' => $emailTemplatesListPanel->getConfig(),
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

		$apiUrl = $dispatcher->url($request, ROUTE_API, $context->getPath(), 'contexts/' . $context->getId());
		$sitemapUrl = $router->url($request, $context->getPath(), 'sitemap');
		$paymentsUrl = $dispatcher->url($request, ROUTE_API, $context->getPath(), '_payments');

		$supportedFormLocales = $context->getSupportedFormLocales();
		$localeNames = AppLocale::getAllLocales();
		$locales = array_map(function($localeKey) use ($localeNames) {
			return ['key' => $localeKey, 'label' => $localeNames[$localeKey]];
		}, $supportedFormLocales);

		$licenseForm = new \APP\components\forms\context\LicenseForm($apiUrl, $locales, $context);
		$searchIndexingForm = new \PKP\components\forms\context\PKPSearchIndexingForm($apiUrl, $locales, $context, $sitemapUrl);
		$paymentSettingsForm = new \PKP\components\forms\context\PKPPaymentSettingsForm($paymentsUrl, $locales, $context);

		$settingsData = [
			'components' => [
				FORM_LICENSE => $licenseForm->getConfig(),
				FORM_SEARCH_INDEXING => $searchIndexingForm->getConfig(),
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
		$dispatcher = $request->getDispatcher();

		$apiUrl = $dispatcher->url($request, ROUTE_API, $context->getPath(), 'contexts/' . $context->getId());

		$userAccessForm = new \APP\components\forms\context\UserAccessForm($apiUrl, $context);

		$settingsData = [
			'components' => [
				FORM_USER_ACCESS => $userAccessForm->getConfig(),
			],
		];
		$templateMgr->assign('settingsData', $settingsData);

		$templateMgr->display('management/access.tpl');
	}
}
