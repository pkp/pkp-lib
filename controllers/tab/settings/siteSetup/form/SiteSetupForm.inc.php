<?php

/**
 * @file controllers/tab/settings/siteSetup/form/SiteSetupForm.inc.php
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SiteSetupForm
 * @ingroup admin_form
 * @see PKPSiteSettingsForm
 *
 * @brief Form to edit site settings.
 */


import('lib.pkp.classes.admin.form.PKPSiteSettingsForm');

class SiteSetupForm extends PKPSiteSettingsForm {
	/**
	 * Constructor.
	 */
	function SiteSetupForm() {
		parent::Form('controllers/tab/settings/siteSetup/form/siteSetupForm.tpl');
		$this->siteSettingsDao = DAORegistry::getDAO('SiteSettingsDAO');

		// Validation checks for this form
		$this->addCheck(new FormValidatorLocale($this, 'title', 'required', 'admin.settings.form.titleRequired'));
		$this->addCheck(new FormValidatorLocale($this, 'contactName', 'required', 'admin.settings.form.contactNameRequired'));
		$this->addCheck(new FormValidatorLocaleEmail($this, 'contactEmail', 'required', 'admin.settings.form.contactEmailRequired'));
		$this->addCheck(new FormValidatorCustom($this, 'minPasswordLength', 'required', 'admin.settings.form.minPasswordLengthRequired', create_function('$l', sprintf('return $l >= %d;', SITE_MIN_PASSWORD_LENGTH))));
		$this->addCheck(new FormValidatorPost($this));

		AppLocale::requireComponents(LOCALE_COMPONENT_APP_COMMON);
	}

	//
	// Extended methods from Form.
	//
	/**
	 * @copydoc Form::fetch()
	 */
	function fetch($request, $params = null) {
		$site = $request->getSite();
		$publicFileManager = new PublicFileManager();
		$contextDao = Application::getContextDAO();
		$contexts = $contextDao->getNames();
		$siteStyleFilename = $publicFileManager->getSiteFilesPath() . '/' . $site->getSiteStyleFilename();

		$cssSettingName = 'siteStyleSheet';
		$imageSettingName = 'pageHeaderTitleImage';

		// Get link actions.
		$uploadCssLinkAction = $this->_getFileUploadLinkAction($cssSettingName, 'css', $request);
		$uploadImageLinkAction = $this->_getFileUploadLinkAction($imageSettingName, 'image', $request);

		// Get the files view.
		$cssView = $this->renderFileView($cssSettingName, $request);
		$imageView = $this->renderFileView($imageSettingName, $request);

		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('locale', AppLocale::getLocale());
		$templateMgr->assign('siteStyleFileExists', file_exists($siteStyleFilename));
		$templateMgr->assign('uploadCssLinkAction', $uploadCssLinkAction);
		$templateMgr->assign('uploadImageLinkAction', $uploadImageLinkAction);
		$templateMgr->assign('cssView', $cssView);
		$templateMgr->assign('imageView', $imageView);
		$templateMgr->assign('redirectOptions', $contexts);
		$templateMgr->assign('pageHeaderTitleImage', $site->getSetting($imageSettingName));
		$templateMgr->assign('helpTopicId', 'site.siteManagement');

		return parent::fetch($request);
	}


	//
	// Extend method from PKPSiteSettingsForm
	//
	/**
	 * @copydoc PKPSiteSettingsForm::initData()
	 */
	function initData($request) {
		$site = $request->getSite();
		$publicFileManager = $publicFileManager = new PublicFileManager();
		$siteStyleFilename = $publicFileManager->getSiteFilesPath() . '/' . $site->getSiteStyleFilename();

		// Get the files settings that can be uploaded within this form.

		// FIXME Change the way we get the style sheet setting when
		// it's implemented in site settings table, like pageHeaderTitleImage.
		$siteStyleSheet = null;
		if (file_exists($siteStyleFilename)) {
			$siteStyleSheet = array(
				'name' => $site->getOriginalStyleFilename(),
				'uploadName' => $site->getSiteStyleFilename(),
				'dateUploaded' => filemtime($siteStyleFilename)
			);
		}

		$pageHeaderTitleImage = $site->getSetting('pageHeaderTitleImage');

		$this->setData('siteStyleSheet', $siteStyleSheet);
		$this->setData('pageHeaderTitleImage', $pageHeaderTitleImage);

		parent::initData();
	}

	/**
	 * Assign form data to user-submitted data.
	 */
	function readInputData() {
		$this->readUserVars(
			array('pageHeaderTitleType', 'title', 'intro', 'about', 'redirect', 'contactName', 'contactEmail', 'minPasswordLength')
		);
	}

	/**
	 * Save site settings.
	 */
	function execute() {
		$siteDao = DAORegistry::getDAO('SiteDAO');
		$site = $siteDao->getSite();

		$site->setRedirect($this->getData('redirect'));
		$site->setMinPasswordLength($this->getData('minPasswordLength'));

		$siteSettingsDao = $this->siteSettingsDao;
		foreach ($this->getLocaleFieldNames() as $setting) {
			$siteSettingsDao->updateSetting($setting, $this->getData($setting), null, true);
		}

		$siteDao->updateObject($site);
		return true;
	}

	//
	// Public methods.
	//
	/**
	 * Render a template to show details about an uploaded file in the form
	 * and a link action to delete it.
	 * @param $fileSettingName string The uploaded file setting name.
	 * @param $request Request
	 * @return string
	 */
	function renderFileView($fileSettingName, $request) {
		$file = $this->getData($fileSettingName);
		$locale = AppLocale::getLocale();

		// Check if the file is localized.
		if (!is_null($file) && key_exists($locale, $file)) {
			// We use the current localized file value.
			$file = $file[$locale];
		}

		// Only render the file view if we have a file.
		if (is_array($file)) {
			$templateMgr = TemplateManager::getManager($request);
			$deleteLinkAction = $this->_getDeleteFileLinkAction($fileSettingName, $request);

			// Get the right template to render the view.
			if ($fileSettingName == 'pageHeaderTitleImage') {
				$template = 'controllers/tab/settings/formImageView.tpl';

				// Get the common alternate text for the image.
				$localeKey = 'admin.settings.homeHeaderImage.altText';
				$commonAltText = __($localeKey);
				$templateMgr->assign('commonAltText', $commonAltText);
			} else {
				$template = 'controllers/tab/settings/formFileView.tpl';
			}

			$publicFileManager = $publicFileManager = new PublicFileManager();

			$templateMgr->assign('publicFilesDir', $request->getBasePath() . '/' . $publicFileManager->getSiteFilesPath());
			$templateMgr->assign('file', $file);
			$templateMgr->assign('deleteLinkAction', $deleteLinkAction);
			$templateMgr->assign('fileSettingName', $fileSettingName);

			return $templateMgr->fetch($template);
		} else {
			return null;
		}
	}

	/**
	 * Delete an uploaded file.
	 * @param $fileSettingName string
	 * @return boolean
	 */
	function deleteFile($fileSettingName, $request) {
		$locale = AppLocale::getLocale();

		// Get the file.
		$file = $this->getData($fileSettingName);

		// Check if the file is localized.
		if (key_exists($locale, $file)) {
			// We use the current localized file value.
			$file = $file[$locale];
		} else {
			$locale = null;
		}

		// Deletes the file and its settings.
		import('classes.file.PublicFileManager');
		$publicFileManager = new PublicFileManager();
		if ($publicFileManager->removeSiteFile($file['uploadName'])) {
			$settingsDao = DAORegistry::getDAO('SiteSettingsDAO');
			$settingsDao->deleteSetting($fileSettingName, $locale);
			return true;
		} else {
			return false;
		}
	}


	//
	// Private helper methods.
	//
	/**
	 * Get a link action for file upload.
	 * @param $settingName string
	 * @param $fileType string The uploaded file type.
	 * @param $request Request
	 * @return LinkAction
	 */
	function _getFileUploadLinkAction($settingName, $fileType, $request) {
		$router = $request->getRouter();
		import('lib.pkp.classes.linkAction.request.AjaxModal');

		$ajaxModal = new AjaxModal(
			$router->url(
				$request, null, null, 'showFileUploadForm', null, array(
					'fileSettingName' => $settingName,
					'fileType' => $fileType
				)
			)
		);
		import('lib.pkp.classes.linkAction.LinkAction');
		$linkAction = new LinkAction(
			'uploadFile-' . $settingName,
			$ajaxModal,
			__('common.upload'),
			null
		);

		return $linkAction;
	}

	/**
	 * Get the delete file link action.
	 * @param $setttingName string File setting name.
	 * @param $request Request
	 * @return LinkAction
	 */
	function _getDeleteFileLinkAction($settingName, $request) {
		$router = $request->getRouter();
		import('lib.pkp.classes.linkAction.request.RemoteActionConfirmationModal');

		$confirmationModal = new RemoteActionConfirmationModal(
			__('common.confirmDelete'), null,
			$router->url(
				$request, null, null, 'deleteFile', null, array(
					'fileSettingName' => $settingName,
					'tab' => 'siteSetup'
				)
			)
		);
		$linkAction = new LinkAction(
			'deleteFile-' . $settingName,
			$confirmationModal,
			__('common.delete'),
			null
		);

		return $linkAction;
	}
}

?>
