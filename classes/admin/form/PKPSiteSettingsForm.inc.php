<?php
/**
 * @defgroup admin_form
 */

/**
 * @file classes/admin/form/PKPSiteSettingsForm.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SiteSettingsForm
 * @ingroup admin_form
 *
 * @brief Form to edit site settings.
 */

// $Id$


define('SITE_MIN_PASSWORD_LENGTH', 4);
import('form.Form');

class PKPSiteSettingsForm extends Form {
	/** @var $siteSettingsDao object Site settings DAO */
	var $siteSettingsDao;

	/**
	 * Constructor.
	 */
	function PKPSiteSettingsForm() {
		parent::Form('admin/settings.tpl');
		$this->siteSettingsDao =& DAORegistry::getDAO('SiteSettingsDAO');

		// Validation checks for this form
		$this->addCheck(new FormValidatorLocale($this, 'title', 'required', 'admin.settings.form.titleRequired'));
		$this->addCheck(new FormValidatorLocale($this, 'contactName', 'required', 'admin.settings.form.contactNameRequired'));
		$this->addCheck(new FormValidatorLocaleEmail($this, 'contactEmail', 'required', 'admin.settings.form.contactEmailRequired'));
		$this->addCheck(new FormValidatorCustom($this, 'minPasswordLength', 'required', 'admin.settings.form.minPasswordLengthRequired', create_function('$l', sprintf('return $l >= %d;', SITE_MIN_PASSWORD_LENGTH))));
		$this->addCheck(new FormValidatorPost($this));
	}

	/**
	 * Display the form.
	 */
	function display() {
		$site =& Request::getSite();
		$publicFileManager = new PublicFileManager();
		$siteStyleFilename = $publicFileManager->getSiteFilesPath() . '/' . $site->getSiteStyleFilename();
		$templateMgr =& TemplateManager::getManager();
		$templateMgr->assign('originalStyleFilename', $site->getOriginalStyleFilename());
		$templateMgr->assign('pageHeaderTitleImage', $site->getSetting('pageHeaderTitleImage'));
		$templateMgr->assign('styleFilename', $site->getSiteStyleFilename());
		$templateMgr->assign('publicFilesDir', Request::getBasePath() . '/' . $publicFileManager->getSiteFilesPath());
		$templateMgr->assign('dateStyleFileUploaded', file_exists($siteStyleFilename)?filemtime($siteStyleFilename):null);
		$templateMgr->assign('siteStyleFileExists', file_exists($siteStyleFilename));
		$templateMgr->assign('helpTopicId', 'site.siteManagement');
		return parent::display();
	}

	/**
	 * Initialize form data from current settings.
	 */
	function initData() {
		$siteDao =& DAORegistry::getDAO('SiteDAO');
		$site =& $siteDao->getSite();

		$this->_data = array(
			'title' => $site->getSetting('title'), // Localized
			'intro' => $site->getSetting('intro'), // Localized
			'redirect' => $site->getRedirect(),
			'about' => $site->getSetting('about'), // Localized
			'contactName' => $site->getSetting('contactName'), // Localized
			'contactEmail' => $site->getSetting('contactEmail'), // Localized
			'minPasswordLength' => $site->getMinPasswordLength(),
			'pageHeaderTitleType' => $site->getSetting('pageHeaderTitleType') // Localized
		);
	}

	function getLocaleFieldNames() {
		return array('title', 'pageHeaderTitleType', 'intro', 'about', 'contactName', 'contactEmail');
	}

	/**
	 * Assign form data to user-submitted data.
	 */
	function readInputData() {
		$this->readUserVars(
			array('pageHeaderTitleType', 'title', 'intro', 'about', 'redirect', 'contactName', 'contactEmail', 'minPasswordLength', 'pageHeaderTitleImageAltText')
		);
	}

	/**
	 * Save site settings.
	 */
	function execute() {
		$siteDao =& DAORegistry::getDAO('SiteDAO');
		$site =& $siteDao->getSite();

		$site->setRedirect($this->getData('redirect'));
		$site->setMinPasswordLength($this->getData('minPasswordLength'));

		$siteSettingsDao =& $this->siteSettingsDao;
		foreach ($this->getLocaleFieldNames() as $setting) {
			$siteSettingsDao->updateSetting($setting, $this->getData($setting), null, true);
		}

		$setting = $site->getSetting('pageHeaderTitleImage');
		if (!empty($setting)) {
			$imageAltText = $this->getData('pageHeaderTitleImageAltText');
			$locale = $this->getFormLocale();
			$setting[$locale]['altText'] = $imageAltText[$locale];
			$site->updateSetting('pageHeaderTitleImage', $setting, 'object', true);
		}

		$siteDao->updateObject($site);
		return true;
	}

	/**
	 * Uploads custom site stylesheet.
	 */
	function uploadSiteStyleSheet() {
		import('file.PublicFileManager');
		$fileManager = new PublicFileManager();
		$site =& Request::getSite();
		if ($fileManager->uploadedFileExists('siteStyleSheet')) {
			$type = $fileManager->getUploadedFileType('siteStyleSheet');
			if ($type != 'text/plain' && $type != 'text/css') {
				return false;
			}

			$uploadName = $site->getSiteStyleFilename();
			if($fileManager->uploadSiteFile('siteStyleSheet', $uploadName)) {
				$siteDao =& DAORegistry::getDAO('SiteDAO');
				$site->setOriginalStyleFilename($fileManager->getUploadedFileName('siteStyleSheet'));
				$siteDao->updateObject($site);
			}
		}

		return true;
	}

	/**
	 * Uploads custom site logo.
	 */
	function uploadPageHeaderTitleImage($locale) {
		import('file.PublicFileManager');
		$fileManager = new PublicFileManager();
		$site =& Request::getSite();
		if ($fileManager->uploadedFileExists('pageHeaderTitleImage')) {
			$type = $fileManager->getUploadedFileType('pageHeaderTitleImage');
			$extension = $fileManager->getImageExtension($type);
			if (!$extension) return false;

			$uploadName = 'pageHeaderTitleImage_' . $locale . $extension;
			if($fileManager->uploadSiteFile('pageHeaderTitleImage', $uploadName)) {
				$siteDao =& DAORegistry::getDAO('SiteDAO');
				$setting = $site->getSetting('pageHeaderTitleImage');
				list($width, $height) = getimagesize($fileManager->getSiteFilesPath() . '/' . $uploadName);
				$setting[$locale] = array(
					'originalFilename' => $fileManager->getUploadedFileName('pageHeaderTitleImage'),
					'width' => $width,
					'height' => $height,
					'uploadName' => $uploadName,
					'dateUploaded' => Core::getCurrentDate()
				);
				$site->updateSetting('pageHeaderTitleImage', $setting, 'object', true);
			}
		}

		return true;
	}
}

?>
