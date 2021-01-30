<?php

/**
 * @file controllers/grid/plugins/form/UploadPluginForm.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UploadPluginForm
 * @ingroup controllers_grid_plugins_form
 *
 * @brief Form to upload a plugin file.
 */

// Import the base Form.
import('lib.pkp.classes.form.Form');

import('lib.pkp.classes.plugins.PluginHelper');
import('lib.pkp.classes.file.FileManager');

class UploadPluginForm extends Form {

	/** @var String PLUGIN_ACTION_... */
	var $_function;


	/**
	 * Constructor.
	 * @param $function string PLUGIN_ACTION_...
	 */
	function __construct($function) {
		parent::__construct('controllers/grid/plugins/form/uploadPluginForm.tpl');

		$this->_function = $function;

		$this->addCheck(new FormValidator($this, 'temporaryFileId', 'required', 'manager.plugins.uploadFailed'));
	}

	//
	// Implement template methods from Form.
	//
	/**
	 * @copydoc Form::readInputData()
	 */
	function readInputData() {
		$this->readUserVars(array('temporaryFileId'));
	}

	/**
	 * @copydoc Form::fetch()
	 */
	function fetch($request, $template = null, $display = false) {
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign(array(
			'function' => $this->_function,
			'category' => $request->getUserVar('category'),
			'plugin' => $request->getUserVar('plugin'),
		));

		return parent::fetch($request, $template, $display);
	}

	/**
	 * @copydoc Form::execute()
	 */
	function execute(...$functionArgs) {
		parent::execute(...$functionArgs);

		$request = Application::get()->getRequest();
		$user = $request->getUser();
		$pluginHelper = new PluginHelper();
		$notificationMgr = new NotificationManager();

		// Retrieve the temporary file.
		import('lib.pkp.classes.file.TemporaryFileManager');
		$temporaryFileManager = new TemporaryFileManager();
		$temporaryFileDao = DAORegistry::getDAO('TemporaryFileDAO'); /* @var $temporaryFileDao TemporaryFileDAO */
		$temporaryFile = $temporaryFileDao->getTemporaryFile($this->getData('temporaryFileId'), $user->getId());

		// Extract the temporary file into a temporary location.
		try {
			$pluginDir = $pluginHelper->extractPlugin($temporaryFile->getFilePath(), $temporaryFile->getOriginalFileName());
		} catch (Exception $e) {
			$notificationMgr->createTrivialNotification($user->getId(), NOTIFICATION_TYPE_ERROR, array('contents' => $e->getMessage()));
			return false;
		} finally {
			$temporaryFileManager->deleteById($temporaryFile->getId(), $user->getId());
		}

		// Install or upgrade the extracted plugin.
		try {
			switch ($this->_function) {
				case PLUGIN_ACTION_UPLOAD:
					$pluginVersion = $pluginHelper->installPlugin($pluginDir);
					$notificationMgr->createTrivialNotification(
						$user->getId(),
						NOTIFICATION_TYPE_SUCCESS,
						array('contents' =>
							__('manager.plugins.installSuccessful', array('versionNumber' => $pluginVersion->getVersionString(false))))
					);
					break;
				case PLUGIN_ACTION_UPGRADE:
					$plugin = PluginRegistry::getPlugin($request->getUserVar('category'), $request->getUserVar('plugin'));
					$pluginVersion = $pluginHelper->upgradePlugin(
						$request->getUserVar('category'),
						basename($plugin->getPluginPath()),
						$pluginDir
					);
					$notificationMgr->createTrivialNotification(
						$user->getId(),
						NOTIFICATION_TYPE_SUCCESS,
						array('contents' => __('manager.plugins.upgradeSuccessful', array('versionString' => $pluginVersion->getVersionString(false))))
					);
					break;
				default: assert(false); // Illegal PLUGIN_ACTION_...
			}
		} catch (Exception $e) {
			$notificationMgr->createTrivialNotification($user->getId(), NOTIFICATION_TYPE_ERROR, array('contents' => $e->getMessage()));
			$temporaryFileManager->rmtree($pluginDir);
			return false;
		}
		return true;
	}
}


