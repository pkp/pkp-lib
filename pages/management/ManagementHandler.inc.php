<?php

/**
 * @file pages/management/ManagementHandler.inc.php
 *
 * Copyright (c) 2013-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ManagementHandler
 * @ingroup pages_management
 *
 * @brief Base class for all management page handlers.
 */

// Import the base Handler.
import('classes.handler.Handler');

class ManagementHandler extends Handler {

	/** @copydoc PKPHandler::_isBackendPage */
	var $_isBackendPage = true;


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

		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('pageComponent', 'SettingsPage');
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
				assert(false);
				$request->getDispatcher()->handle404();
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
		$publicFileApiUrl = $dispatcher->url($request, ROUTE_API, $context->getPath(), '_uploadPublicFile');

		$supportedFormLocales = $context->getSupportedFormLocales();
		$localeNames = AppLocale::getAllLocales();
		$locales = array_map(function($localeKey) use ($localeNames) {
			return ['key' => $localeKey, 'label' => $localeNames[$localeKey]];
		}, $supportedFormLocales);

		$contactForm = new PKP\components\forms\context\PKPContactForm($apiUrl, $locales, $context);
		$mastheadForm = new APP\components\forms\context\MastheadForm($apiUrl, $locales, $context, $publicFileApiUrl);

		$templateMgr->setState([
			'components' => [
				FORM_CONTACT => $contactForm->getConfig(),
				FORM_MASTHEAD => $mastheadForm->getConfig(),
			],
		]);

		// Interact with the beacon (if enabled) and determine if a new version exists
		import('lib.pkp.classes.site.VersionCheck');
		$latestVersion = VersionCheck::checkIfNewVersionExists();

		// Display a warning message if there is a new version of OJS available
		if (Config::getVar('general', 'show_upgrade_warning') && $latestVersion) {
			$currentVersion = VersionCheck::getCurrentDBVersion();
			$templateMgr->assign([
				'newVersionAvailable' =>  true,
				'currentVersion' => $currentVersion->getVersionString(),
				'latestVersion' =>  $latestVersion,
			]);

			// Get contact information for site administrator
			$roleDao = DAORegistry::getDAO('RoleDAO'); /* @var $roleDao RoleDAO */
			$siteAdmins = $roleDao->getUsersByRoleId(ROLE_ID_SITE_ADMIN);
			$templateMgr->assign('siteAdmin', $siteAdmins->next());
		}

		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_SUBMISSION);
		$templateMgr->assign('pageTitle', __('manager.setup'));
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
		$publicFileApiUrl = $dispatcher->url($request, ROUTE_API, $context->getPath(), '_uploadPublicFile');

		import('classes.file.PublicFileManager');
		$publicFileManager = new PublicFileManager();
		$baseUrl = $request->getBaseUrl() . '/' . $publicFileManager->getContextFilesPath($context->getId());

		$supportedFormLocales = $context->getSupportedFormLocales();
		$localeNames = AppLocale::getAllLocales();
		$locales = array_map(function($localeKey) use ($localeNames) {
			return ['key' => $localeKey, 'label' => $localeNames[$localeKey]];
		}, $supportedFormLocales);

		$announcementSettingsForm = new \PKP\components\forms\context\PKPAnnouncementSettingsForm($contextApiUrl, $locales, $context);
		$appearanceAdvancedForm = new \APP\components\forms\context\AppearanceAdvancedForm($contextApiUrl, $locales, $context, $baseUrl, $temporaryFileApiUrl, $publicFileApiUrl);
		$appearanceSetupForm = new \APP\components\forms\context\AppearanceSetupForm($contextApiUrl, $locales, $context, $baseUrl, $temporaryFileApiUrl, $publicFileApiUrl);
		$informationForm = new \PKP\components\forms\context\PKPInformationForm($contextApiUrl, $locales, $context, $publicFileApiUrl);
		$listsForm = new \PKP\components\forms\context\PKPListsForm($contextApiUrl, $locales, $context);
		$privacyForm = new \PKP\components\forms\context\PKPPrivacyForm($contextApiUrl, $locales, $context, $publicFileApiUrl);
		$themeForm = new \PKP\components\forms\context\PKPThemeForm($themeApiUrl, $locales, $context);
		$dateTimeForm = new \PKP\components\forms\context\PKPDateTimeForm($contextApiUrl, $locales, $context);

		$templateMgr->setConstants([
			'FORM_ANNOUNCEMENT_SETTINGS',
		]);

		$templateMgr->setState([
			'components' => [
				FORM_ANNOUNCEMENT_SETTINGS => $announcementSettingsForm->getConfig(),
				FORM_APPEARANCE_ADVANCED => $appearanceAdvancedForm->getConfig(),
				FORM_APPEARANCE_SETUP => $appearanceSetupForm->getConfig(),
				FORM_INFORMATION => $informationForm->getConfig(),
				FORM_LISTS => $listsForm->getConfig(),
				FORM_PRIVACY => $privacyForm->getConfig(),
				FORM_THEME => $themeForm->getConfig(),
				FORM_DATE_TIME => $dateTimeForm->getConfig(),
			],
			'announcementsNavLink' => [
				'name' => __('announcement.announcements'),
				'url' => $router->url($request, null, 'management', 'settings', 'announcements'),
				'isCurrent' => false,
			],
		]);

		$templateMgr->assign('pageTitle', __('manager.website.title'));
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
		$metadataSettingsForm = new \APP\components\forms\context\MetadataSettingsForm($contextApiUrl, $context);
		$disableSubmissionsForm = new \PKP\components\forms\context\PKPDisableSubmissionsForm($contextApiUrl, $locales, $context);
		$emailSetupForm = new \PKP\components\forms\context\PKPEmailSetupForm($contextApiUrl, $locales, $context);
		$reviewGuidanceForm = new \APP\components\forms\context\ReviewGuidanceForm($contextApiUrl, $locales, $context);
		$reviewSetupForm = new \PKP\components\forms\context\PKPReviewSetupForm($contextApiUrl, $locales, $context);

		$emailTemplatesListPanel = new \APP\components\listPanels\EmailTemplatesListPanel(
			'emailTemplates',
			__('manager.emails.emailTemplates'),
			$locales,
			[
				'apiUrl' => $emailTemplatesApiUrl,
				'count' => 200,
				'items' => [],
				'itemsMax' => 0,
				'lazyLoad' => true,
			]
		);

		$templateMgr->setState([
			'components' => [
				FORM_AUTHOR_GUIDELINES => $authorGuidelinesForm->getConfig(),
				FORM_METADATA_SETTINGS => $metadataSettingsForm->getConfig(),
				FORM_DISABLE_SUBMISSIONS => $disableSubmissionsForm->getConfig(),
				FORM_EMAIL_SETUP => $emailSetupForm->getConfig(),
				FORM_REVIEW_GUIDANCE => $reviewGuidanceForm->getConfig(),
				FORM_REVIEW_SETUP => $reviewSetupForm->getConfig(),
				'emailTemplates' => $emailTemplatesListPanel->getConfig(),
			],
		]);
		$templateMgr->assign('pageTitle', __('manager.workflow.title'));
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
		$templateMgr->setConstants([
			'FORM_PAYMENT_SETTINGS',
		]);

		$templateMgr->setState([
			'components' => [
				FORM_LICENSE => $licenseForm->getConfig(),
				FORM_SEARCH_INDEXING => $searchIndexingForm->getConfig(),
				FORM_PAYMENT_SETTINGS => $paymentSettingsForm->getConfig(),
			],
		]);
		$templateMgr->assign('pageTitle', __('manager.distribution.title'));
	}

	/**
	 * Display list of announcements and announcement types
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function announcements($args, $request) {
		$templateMgr = TemplateManager::getManager($request);
		$this->setupTemplate($request);

		$apiUrl = $request->getDispatcher()->url($request, ROUTE_API, $request->getContext()->getPath(), 'announcements');

		$supportedFormLocales = $request->getContext()->getSupportedFormLocales();
		$localeNames = AppLocale::getAllLocales();
		$locales = array_map(function($localeKey) use ($localeNames) {
			return ['key' => $localeKey, 'label' => $localeNames[$localeKey]];
		}, $supportedFormLocales);

		$announcementForm = new \PKP\components\forms\announcement\PKPAnnouncementForm($apiUrl, $locales, $request->getContext());

		$getParams = [
			'contextIds' => $request->getContext()->getId(),
			'count' => 30,
		];
		$announcementsIterator = Services::get('announcement')->getMany($getParams);
		$itemsMax = Services::get('announcement')->getMax($getParams);
		$items = [];
		foreach ($announcementsIterator as $announcement) {
			$items[] = Services::get('announcement')->getSummaryProperties($announcement, [
				'request' => $request,
				'announcementContext' => $request->getContext(),
			]);
		}

		$announcementsListPanel = new \PKP\components\listPanels\PKPAnnouncementsListPanel(
			'announcements',
			__('manager.setup.announcements'),
			[
				'apiUrl' => $apiUrl,
				'form' => $announcementForm,
				'getParams' => $getParams,
				'items' => $items,
				'itemsMax' => $itemsMax,
			]
		);

		$templateMgr->setState([
			'components' => [
				$announcementsListPanel->id => $announcementsListPanel->getConfig(),
			],
		]);

		$templateMgr->assign([
			'pageTitle' => __('manager.setup.announcements'),
		]);

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
		$notifyUrl = $dispatcher->url($request, ROUTE_API, $context->getPath(), '_email');
		$progressUrl = $dispatcher->url($request, ROUTE_API, $context->getPath(), '_email/{queueId}');
		$userGroups = DAORegistry::getDAO('UserGroupDAO')->getByContextId($context->getId());

		$userAccessForm = new \APP\components\forms\context\UserAccessForm($apiUrl, $context);
		$notifyUsersForm = new \PKP\components\forms\context\PKPNotifyUsersForm($notifyUrl, $context, $userGroups);

		$templateMgr->assign([
			'pageComponent' => 'AccessPage',
			'pageTitle' => __('navigation.access'),
			'enableBulkEmails' => in_array($context->getId(), (array) $request->getSite()->getData('enableBulkEmails')),
		]);

		$templateMgr->setConstants([
			'FORM_NOTIFY_USERS',
		]);

		$templateMgr->setState([
			'components' => [
				FORM_USER_ACCESS => $userAccessForm->getConfig(),
				FORM_NOTIFY_USERS => $notifyUsersForm->getConfig(),
			],
			'progressUrl' => $progressUrl,
		]);

		$templateMgr->display('management/access.tpl');
	}
}
