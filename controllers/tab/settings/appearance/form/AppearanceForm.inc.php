<?php

/**
 * @file controllers/tab/settings/appearance/form/AppearanceForm.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class AppearanceForm
 * @ingroup controllers_tab_settings_appearance_form
 *
 * @brief Form to edit appearance settings.
 */

import('lib.pkp.classes.controllers.tab.settings.form.ContextSettingsForm');

class AppearanceForm extends ContextSettingsForm {

	/** @var array */
	var $_imagesSettingsName;

	/**
	 * Constructor.
	 * @param $wizardMode bool True IFF this form is to be opened in wizard mode
	 * @param $additionalSettings array Additional settings to add, if any
	 */
	function AppearanceForm($wizardMode = false, $additionalSettings = array()) {

		$settings = array_merge($additionalSettings, array(
			'pageHeaderTitleType' => 'int',
			'pageHeaderTitle' => 'string',
			'additionalHomeContent' => 'string',
			'pageHeader' => 'string',
			'pageFooter' => 'string',
			'navItems' => 'object',
			'itemsPerPage' => 'int',
			'numPageLinks' => 'int',
			'themePluginPath' => 'string',
		));

		AppLocale::requireComponents(LOCALE_COMPONENT_APP_COMMON);

		parent::ContextSettingsForm($settings, 'controllers/tab/settings/appearance/form/appearanceForm.tpl', $wizardMode);
	}


	//
	// Getters and Setters
	//
	/**
	 * Get the images settings name (setting name => alt text locale key).
	 * @return array
	 */
	function getImagesSettingsName() {
		return array(
			'homepageImage' => 'common.homepageImage.altText',
			'pageHeaderTitleImage' => 'common.pageHeader.altText',
			'pageHeaderLogoImage' => 'common.pageHeaderLogo.altText',
		);
	}

	//
	// Implement template methods from Form.
	//
	/**
	 * @copydoc Form::getLocaleFieldNames()
	 */
	function getLocaleFieldNames() {
		return array(
			'pageHeaderTitleType',
			'pageHeaderTitle',
			'additionalHomeContent',
			'pageHeader',
			'pageFooter',
		);
	}


	//
	// Extend methods from ContextSettingsForm.
	//
	/**
	 * @copydoc ContextSettingsForm::fetch()
	 */
	function fetch($request) {
		// Get all upload form image link actions.
		$uploadImageLinkActions = array();
		foreach ($this->getImagesSettingsName() as $settingName => $altText) {
			$uploadImageLinkActions[$settingName] = $this->_getFileUploadLinkAction($settingName, 'image', $request);
		}
		// Get the css upload link action.
		$uploadCssLinkAction = $this->_getFileUploadLinkAction('styleSheet', 'css', $request);

		$imagesViews = $this->_renderAllFormImagesViews($request);
		$cssView = $this->renderFileView('styleSheet', $request);

		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('uploadImageLinkActions', $uploadImageLinkActions);
		$templateMgr->assign('uploadCssLinkAction', $uploadCssLinkAction);

		$themePlugins = PluginRegistry::loadCategory('themes');
		$themePluginOptions = array();
		foreach ($themePlugins as $themePlugin) {
			$themePluginOptions[basename($themePlugin->getPluginPath())] = $themePlugin->getDisplayName();
		}
		$templateMgr->assign('themePluginOptions', $themePluginOptions);

		$params = array(
			'imagesViews' => $imagesViews,
			'styleSheetView' => $cssView,
			'locale' => AppLocale::getLocale()
		);

		return parent::fetch($request, $params);
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
			$imagesSettingsName = $this->getImagesSettingsName();
			if (in_array($fileSettingName, array_keys($imagesSettingsName))) {
				$template = 'controllers/tab/settings/formImageView.tpl';

				// Get the common alternate text for the image.
				$localeKey = $imagesSettingsName[$fileSettingName];
				$commonAltText = __($localeKey);
				$templateMgr->assign('commonAltText', $commonAltText);
			} else {
				$template = 'controllers/tab/settings/formFileView.tpl';
			}

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
	 * @param $request PKPRequest
	 * @return boolean
	 */
	function deleteFile($fileSettingName, $request) {
		$context = $request->getContext();
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
		if ($publicFileManager->removeContextFile($context->getAssocType(), $context->getId(), $file['uploadName'])) {
			$settingsDao = $context->getSettingsDao();
			$settingsDao->deleteSetting($context->getId(), $fileSettingName, $locale);
			return true;
		} else {
			return false;
		}
	}

	/**
	 * @copydoc ContextSettingsForm::execute()
	 */
	function execute($request) {
		parent::execute($request);

		// Save block plugins context positions.
		import('lib.pkp.classes.controllers.listbuilder.ListbuilderHandler');
		ListbuilderHandler::unpack($request, $request->getUserVar('blocks'));

		// Activate the selected theme plugin
		$context = $request->getContext();
		$themePlugins = PluginRegistry::loadCategory('themes');
		$selectedThemePluginPath = $this->getData('themePluginPath');
		$selectedThemePlugin = null;
		foreach ($themePlugins as $themePlugin) {
			if (basename($themePlugin->getPluginPath()) != $selectedThemePluginPath) {
				if ($themePlugin->getEnabled()) {
					$themePlugin->setEnabled(false);
				}
			} else {
				$selectedThemePlugin = $themePlugin;
			}
		}
		if ($selectedThemePlugin) {
			// Activate the selected theme to trigger a CSS recompile.
			$selectedThemePlugin->setEnabled(true);
		} else {
			assert(false); // Couldn't identify the selected theme plugin
		}
	}

	/**
	 * Overriden method from ListbuilderHandler.
	 * @param $request Request
	 * @param $rowId mixed
	 * @param $newRowId array
	 */
	function updateEntry($request, $rowId, $newRowId) {
		$plugins =& PluginRegistry::loadCategory('blocks');
		$plugin =& $plugins[$rowId]; // Ref hack
		switch ($newRowId['listId']) {
			case 'unselected':
				$plugin->setEnabled(false);
				break;
			case 'leftContext':
				$plugin->setEnabled(true);
				$plugin->setBlockContext(BLOCK_CONTEXT_LEFT_SIDEBAR);
				$plugin->setSeq((int) $newRowId['sequence']);
				break;
			case 'rightContext':
				$plugin->setEnabled(true);
				$plugin->setBlockContext(BLOCK_CONTEXT_RIGHT_SIDEBAR);
				$plugin->setSeq((int) $newRowId['sequence']);
				break;
			default:
				assert(false);
		}
	}

	/**
	 * Avoid warnings when Listbuilder::unpack tries to call this method.
	 */
	function deleteEntry() {
		return false;
	}

	/**
	 * Avoid warnings when Listbuilder::unpack tries to call this method.
	 */
	function insertEntry() {
		return false;
	}


	//
	// Private helper methods
	//
	/**
	 * Render all form images views.
	 * @param $request Request
	 * @return array
	 */
	function _renderAllFormImagesViews($request) {
		$imagesViews = array();
		foreach ($this->getImagesSettingsName() as $imageSettingName => $altText) {
			$imagesViews[$imageSettingName] = $this->renderFileView($imageSettingName, $request);
		}

		return $imagesViews;
	}

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
		import('lib.pkp.classes.linkAction.LinkAction');
		return new LinkAction(
			'uploadFile-' . $settingName,
			new AjaxModal(
				$router->url(
					$request, null, null, 'showFileUploadForm', null, array(
						'fileSettingName' => $settingName,
						'fileType' => $fileType
					)
				),
				__('common.upload'),
				'modal_add_file'
			),
			__('common.upload'),
			'add'
		);
	}

	/**
	 * Get the delete file link action.
	 * @param $settingName string File setting name.
	 * @param $request Request
	 * @return LinkAction
	 */
	function _getDeleteFileLinkAction($settingName, $request) {
		$router = $request->getRouter();
		import('lib.pkp.classes.linkAction.request.RemoteActionConfirmationModal');

		return new LinkAction(
			'deleteFile-' . $settingName,
			new RemoteActionConfirmationModal(
				__('common.confirmDelete'), null,
				$router->url(
					$request, null, null, 'deleteFile', null, array(
						'fileSettingName' => $settingName,
						'tab' => 'appearance'
					)
				),
				'modal_delete'
			),
			__('common.delete'),
			null
		);
	}
}

?>
