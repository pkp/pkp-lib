<?php

/**
 * @file plugins/importexport/users/PKPUserImportExportPlugin.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class UserImportExportPlugin
 * @ingroup plugins_importexport_users
 *
 * @brief User XML import/export plugin
 */

import('lib.pkp.classes.plugins.ImportExportPlugin');

abstract class PKPUserImportExportPlugin extends ImportExportPlugin {

	/**
	 * @copydoc Plugin::register()
	 */
	function register($category, $path, $mainContextId = null) {
		$success = parent::register($category, $path, $mainContextId);
		$this->addLocaleData();
		$this->import('PKPUserImportExportDeployment');
		return $success;
	}

	/**
	 * Get the name of this plugin. The name must be unique within
	 * its category.
	 * @return String name of plugin
	 */
	function getName() {
		return 'UserImportExportPlugin';
	}

	/**
	 * Get the display name.
	 * @return string
	 */
	function getDisplayName() {
		return __('plugins.importexport.users.displayName');
	}

	/**
	 * Get the display description.
	 * @return string
	 */
	function getDescription() {
		return __('plugins.importexport.users.description');
	}

	/**
	 * @copydoc ImportExportPlugin::getPluginSettingsPrefix()
	 */
	function getPluginSettingsPrefix() {
		return 'users';
	}

	/**
	 * Display the plugin.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function display($args, $request) {
		$templateMgr = TemplateManager::getManager($request);
		$context = $request->getContext();

		parent::display($args, $request);

		$templateMgr->assign('plugin', $this);

		switch (array_shift($args)) {
			case 'index':
			case '':
				$templateMgr->display($this->getTemplateResource('index.tpl'));
				break;
			case 'uploadImportXML':
				$user = $request->getUser();
				import('lib.pkp.classes.file.TemporaryFileManager');
				$temporaryFileManager = new TemporaryFileManager();
				$temporaryFile = $temporaryFileManager->handleUpload('uploadedFile', $user->getId());
				if ($temporaryFile) {
					$json = new JSONMessage(true);
					$json->setAdditionalAttributes(array(
						'temporaryFileId' => $temporaryFile->getId()
					));
				} else {
					$json = new JSONMessage(false, __('common.uploadFailed'));
				}

				header('Content-Type: application/json');
				return $json->getString();
			case 'importBounce':
				$json = new JSONMessage(true);
				$json->setEvent('addTab', array(
					'title' => __('plugins.importexport.users.results'),
					'url' => $request->url(null, null, null, array('plugin', $this->getName(), 'import'), array('temporaryFileId' => $request->getUserVar('temporaryFileId'))),
				));
				header('Content-Type: application/json');
				return $json->getString();
			case 'import':
				$temporaryFileId = $request->getUserVar('temporaryFileId');
				$temporaryFileDao = DAORegistry::getDAO('TemporaryFileDAO');
				$user = $request->getUser();
				$temporaryFile = $temporaryFileDao->getTemporaryFile($temporaryFileId, $user->getId());
				if (!$temporaryFile) {
					$json = new JSONMessage(true, __('plugins.importexport.users.uploadFile'));
					header('Content-Type: application/json');
					return $json->getString();
				}
				$temporaryFilePath = $temporaryFile->getFilePath();
				libxml_use_internal_errors(true);

				$filter = $this->getUserImportExportFilter($context, $user);
				$users = $this->importUsers(file_get_contents($temporaryFilePath), $context, $user, $filter);
				$validationErrors = array_filter(libxml_get_errors(), function($a) {
					return $a->level == LIBXML_ERR_ERROR || $a->level == LIBXML_ERR_FATAL;
				});
				$templateMgr->assign('validationErrors', $validationErrors);
				libxml_clear_errors();
				if ($filter->hasErrors()) {
					$templateMgr->assign('filterErrors', $filter->getErrors());
				}
				$templateMgr->assign('users', $users);
				$json = new JSONMessage(true, $templateMgr->fetch($this->getTemplateResource('results.tpl')));
				header('Content-Type: application/json');
				return $json->getString();
			case 'export':
				$filter = $this->getUserImportExportFilter($request->getContext(), $request->getUser(), false);

				$exportXml = $this->exportUsers(
					(array) $request->getUserVar('selectedUsers'),
					$request->getContext(),
					$request->getUser(),
					$filter
				);
				import('lib.pkp.classes.file.FileManager');
				$fileManager = new FileManager();
				$exportFileName = $this->getExportFileName($this->getExportPath(), 'users', $context, '.xml');
				$fileManager->writeFile($exportFileName, $exportXml);
				$fileManager->downloadFileByPath($exportFileName);
				$fileManager->deleteFileByPath($exportFileName);
				break;
			case 'exportAllUsers':
				$filter = $this->getUserImportExportFilter($request->getContext(), $request->getUser(), false);

				$exportXml = $this->exportAllUsers(
					$request->getContext(),
					$request->getUser(),
					$filter
				);
				import('lib.pkp.classes.file.FileManager');
				$fileManager = new FileManager();
				$exportFileName = $this->getExportFileName($this->getExportPath(), 'users', $context, '.xml');
				$fileManager->writeFile($exportFileName, $exportXml);
				$fileManager->downloadFileByPath($exportFileName);
				$fileManager->deleteFileByPath($exportFileName);
				break;
			default:
				$dispatcher = $request->getDispatcher();
				$dispatcher->handle404();
		}
	}

	/**
	 * Get the XML for all of users.
	 * @param $context Context
	 * @param $user User
	 * @param $filter Filter byRef parameter - import/export filter used
	 * @return string XML contents representing the supplied user IDs.
	 */
	function exportAllUsers($context, $user, &$filter = null) {
		$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
		$users = $userGroupDao->getUsersByContextId($context->getId());
		if (!$filter) {
			$filter = $this->getUserImportExportFilter($context, $user, false);
		}

		return $this->exportUsers($users->toArray(), $context, $user, $filter);
	}

	/**
	 * Get the XML for a set of users.
	 * @param $ids array mixed Array of users or user IDs
	 * @param $context Context
	 * @param $user User
	 * @param $filter Filter byRef parameter - import/export filter used
	 * @return string XML contents representing the supplied user IDs.
	 */
	function exportUsers($ids, $context, $user, &$filter = null) {
		$userDao = DAORegistry::getDAO('UserDAO');
		$xml = '';

		if (!$filter) {
			$filter = $this->getUserImportExportFilter($context, $user, false);
		}

		$users = array();
		foreach ($ids as $id) {
			if (is_a($id, 'User')) {
				$users[] = $id;
			} else {
				$user = $userDao->getById($id, $context->getId());
				if ($user) $users[] = $user;
			}
		}


		$userXml = $filter->execute($users);
		if ($userXml) $xml = $userXml->saveXml();
		else fatalError('Could not convert users.');
		return $xml;
	}

	/**
	 * Get the XML for a set of users.
	 * @param $importXml string XML contents to import
	 * @param $context Context
	 * @param $user User
	 * @param $filter Filter byRef parameter - import/export filter used
	 * @return array Set of imported users
	 */
	function importUsers($importXml, $context, $user, &$filter = null) {
		if (!$filter) {
			$filter = $this->getUserImportExportFilter($context, $user);
		}

		return $filter->execute($importXml);
	}

	/**
	 * Return user filter for import purposes
	 * @param $context Context
	 * @param $user User
	 * @param $isImport bool return Import Filter if true - export if false
	 * @return Filter
	 */
	function getUserImportExportFilter($context, $user, $isImport = true) {
		$filterDao = DAORegistry::getDAO('FilterDAO');

		if ($isImport) {
			$userFilters = $filterDao->getObjectsByGroup('user-xml=>user');
		} else {
			$userFilters = $filterDao->getObjectsByGroup('user=>user-xml');
		}

		assert(count($userFilters) == 1); // Assert only a single unserialization filter
		$filter = array_shift($userFilters);
		$filter->setDeployment(new PKPUserImportExportDeployment($context, $user));

		return $filter;
	}
}

?>
