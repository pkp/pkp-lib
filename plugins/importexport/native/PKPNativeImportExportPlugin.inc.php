<?php

/**
 * @file plugins/importexport/native/NativeImportExportPlugin.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NativeImportExportPlugin
 * @ingroup plugins_importexport_native
 *
 * @brief Native XML import/export plugin
 */
use Colors\Color;

import('lib.pkp.classes.plugins.ImportExportPlugin');

class PKPNativeImportExportPlugin extends ImportExportPlugin {

	var $_childDeployment = null;

	/**
	 * @copydoc Plugin::register()
	 */
	function register($category, $path, $mainContextId = null) {
		$success = parent::register($category, $path, $mainContextId);
		if (!Config::getVar('general', 'installed') || defined('RUNNING_UPGRADE')) return $success;
		if ($success && $this->getEnabled()) {
			$this->addLocaleData();
			$this->import('NativeImportExportDeployment');
		}
		return $success;
	}

	/**
	 * Get the name of this plugin. The name must be unique within
	 * its category.
	 * @return String name of plugin
	 */
	function getName() {
		return 'NativeImportExportPlugin';
	}

	/**
	 * Get the display name.
	 * @return string
	 */
	function getDisplayName() {
		return __('plugins.importexport.native.displayName');
	}

	/**
	 * Get the display description.
	 * @return string
	 */
	function getDescription() {
		return __('plugins.importexport.native.description');
	}

	/**
	 * @copydoc ImportExportPlugin::getPluginSettingsPrefix()
	 */
	function getPluginSettingsPrefix() {
		return 'native';
	}

	/**
	 * Display the plugin.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function display($args, $request) {
		$templateMgr = TemplateManager::getManager($request);
		parent::display($args, $request);

		$context = $this->getDeployment()->getContext();
		$user = $this->getDeployment()->getUser();

		$opType = array_shift($args);
		switch ($opType) {
			case 'index':
			case '':
				$apiUrl = $request->getDispatcher()->url($request, ROUTE_API, $context->getPath(), 'submissions');
				$submissionsListPanel = new \APP\components\listPanels\SubmissionsListPanel(
					'submissions',
					__('common.publications'),
					[
						'apiUrl' => $apiUrl,
						'count' => 100,
						'getParams' => new stdClass(),
						'lazyLoad' => true,
					]
				);
				$submissionsConfig = $submissionsListPanel->getConfig();
				$submissionsConfig['addUrl'] = '';
				$submissionsConfig['filters'] = array_slice($submissionsConfig['filters'], 1);
				$templateMgr->setState([
					'components' => [
						'submissions' => $submissionsConfig,
					],
				]);
				$templateMgr->assign([
					'pageComponent' => 'ImportExportPage',
				]);

				$templateMgr->display($this->getTemplateResource('index.tpl'));

				return array(null, true);
			case 'uploadImportXML':
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
				return array ($json->getString(), true);
			case 'importBounce':
				$tab = $this->getBounceTab($request,
					__('plugins.importexport.native.results'),
					'import',
					array('temporaryFileId' => $request->getUserVar('temporaryFileId'))
				);
				return array($tab, true);
			case 'exportSubmissionsBounce':
				$tab = $this->getBounceTab($request,
					__('plugins.importexport.native.export.submissions.results'),
						'exportSubmissions',
						array('selectedSubmissions' => $request->getUserVar('selectedSubmissions'))
				);
				return array($tab, true);
			case 'import':
				$temporaryFilePath = $this->getImportedFilePath($request->getUserVar('temporaryFileId'), $this->getDeployment()->getUser());
				list ($filter, $xmlString) = $this->getImportFilter($temporaryFilePath);
				$result = $this->getImportTemplateResult($filter, $xmlString, $this->getDeployment(), $templateMgr);

				return array($result, true);
			case 'exportSubmissions':
				$submissionIds = (array) $request->getUserVar('selectedSubmissions');

				$this->getExportSubmissionsDeployment($submissionIds, $this->_childDeployment);

				$result = $this->getExportTemplateResult($this->getDeployment(), $templateMgr, 'submissions');

				return array($result, true);
			// case 'exportSubmissions':
			// 	$submissionIds = (array) $request->getUserVar('selectedSubmissions');

			// 	$deployment = $this->getExportSubmissionsDeployment('article=>native_xml', $submissionIds, $request->getContext(), $request->getUser());

			// 	$result = $deployment->processResult;
			// 	$problems = $deployment->getWarningsAndErrors();
			// 	$foundErrors = $deployment->isProcessFailed();

			// 	if ($foundErrors) {
			// 		$templateMgr->assign('validationErrors', $deployment->getXMLValidationErrors());

			// 		$templateMgr->assign('errorsAndWarnings', $problems);
			// 		$templateMgr->assign('errorsFound', $foundErrors);
			// 	} else {
			// 		$exportXml = $result->saveXml();

			// 		if ($exportXml) {
			// 			$path = $this->writeExportedFile('articles', $exportXml, $context);
			// 			$templateMgr->assign('exportPath', $path);
			// 		}
			// 	}

			// 	$json = new JSONMessage(true, $templateMgr->fetch($this->getTemplateResource('resultsExport.tpl')));
			// 	header('Content-Type: application/json');
			// 	return $json->getString();
			// case 'exportIssues':
			// 	$selectedEntitiesIds = (array) $request->getUserVar('selectedIssues');

			// 	$deployment = $this->getExportIssuesDeployment($selectedEntitiesIds, $request->getContext(), $request->getUser());

			// 	$result = $deployment->processResult;
			// 	$problems = $deployment->getWarningsAndErrors();
			// 	$foundErrors = $deployment->isProcessFailed();

			// 	if ($foundErrors) {
			// 		$templateMgr->assign('validationErrors', $deployment->getXMLValidationErrors());

			// 		$templateMgr->assign('errorsAndWarnings', $problems);
			// 		$templateMgr->assign('errorsFound', $foundErrors);
			// 	} else {
			// 		$exportXml = $result->saveXml();

			// 		if ($exportXml) {
			// 			$path = $this->writeExportedFile('issues', $exportXml, $context);
			// 			$templateMgr->assign('exportPath', $path);
			// 		}
			// 	}

			// 	// Display the results
			// 	$json = new JSONMessage(true, $templateMgr->fetch($this->getTemplateResource('resultsExport.tpl')));
			// 	header('Content-Type: application/json');
			// 	return $json->getString();
			case 'downloadExportFile':
				$downloadPath = $request->getUserVar('downloadFilePath');
				$this->downloadExportedFile($downloadPath);

				return array(null, true);
			// default:
			// 	$dispatcher = $request->getDispatcher();
			// 	$dispatcher->handle404();
		}
	}

	function getExportSubmissionsDeployment($submissionIds, &$deployment, $opts = array()) {
		$filter = $this->getExportFilter('exportSubmissions');

		$submissions = array();
		foreach ($submissionIds as $submissionId) {
			/** @var $submissionService APP\Services\SubmissionService */
			$submissionService = Services::get('submission');
			$submission = $submissionService->get($submissionId);

			if ($submission) $submissions[] = $submission;
		}

		$deployment->export($filter, $submissions, $opts);
	}

	function exportResultXML($deployment) {
		$result = $deployment->processResult;
		$foundErrors = $deployment->isProcessFailed();

		$xml = null;
		if (!$foundErrors && $result) {
			$xml = $result->saveXml();
		}

		return $xml;
	}

	function getExportTemplateResult($deployment, $templateMgr, $exportFileName) {
		$result = $deployment->processResult;
		$problems = $deployment->getWarningsAndErrors();
		$foundErrors = $deployment->isProcessFailed();

		if ($foundErrors) {
			$templateMgr->assign('validationErrors', $deployment->getXMLValidationErrors());

			$templateMgr->assign('errorsAndWarnings', $problems);
			$templateMgr->assign('errorsFound', $foundErrors);
		} else {
			$exportXml = $result->saveXml();

			if ($exportXml) {
				$path = $this->writeExportedFile($exportFileName, $exportXml, $deployment->getContext());
				$templateMgr->assign('exportPath', $path);
			}
		}

		// Display the results
		$json = new JSONMessage(true, $templateMgr->fetch($this->getTemplateResource('resultsExport.tpl')));
		header('Content-Type: application/json');
		return $json->getString();
	}

	function getImportTemplateResult($filter, $xmlString, $deployment, $templateMgr) {
		$deployment->import($filter, $xmlString);

		$templateMgr->assign('content', $deployment->processResult);
		$templateMgr->assign('validationErrors', $deployment->getXMLValidationErrors());

		$problems = $deployment->getWarningsAndErrors();
		$foundErrors = $deployment->isProcessFailed();

		$templateMgr->assign('errorsAndWarnings', $problems);
		$templateMgr->assign('errorsFound', $foundErrors);

		$templateMgr->assign('importedRootObjects', $deployment->getImportedRootEntitiesWithNames());

		// Display the results
		$json = new JSONMessage(true, $templateMgr->fetch($this->getTemplateResource('resultsImport.tpl')));
		header('Content-Type: application/json');
		return $json->getString();
	}

	function getImportedFilePath($temporaryFileId, $user) {
		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_SUBMISSION);

		$temporaryFileDao = DAORegistry::getDAO('TemporaryFileDAO'); /** @var $temporaryFileDao TemporaryFileDAO */

		$temporaryFile = $temporaryFileDao->getTemporaryFile($temporaryFileId, $user->getId());
		if (!$temporaryFile) {
			$json = new JSONMessage(true, __('plugins.inportexport.native.uploadFile'));
			header('Content-Type: application/json');
			return $json->getString();
		}
		$temporaryFilePath = $temporaryFile->getFilePath();

		return $temporaryFilePath;
	}

	// function getExportSubmissionsDeployment($submissionIds, $context, $user, $opts = array()) {
	// 	$submissions = array();
	// 	foreach ($submissionIds as $submissionId) {
	// 		/** @var $submissionService APP\Services\SubmissionService */
	// 		$submissionService = Services::get('submission');
	// 		$submission = $submissionService->get($submissionId);

	// 		if ($submission) $submissions[] = $submission;
	// 	}

	// 	$deployment = new NativeImportExportDeployment($context, $user);
	// 	$deployment->export('article=>native-xml', $submissions, $opts);

	// 	return $deployment;
	// }

	// /**
	//  * Get the XML for a set of submissions.
	//  * @param $submissionIds array Array of submission IDs
	//  * @param $context Context
	//  * @param $user User|null
	//  * @param $opts array
	//  * @return string XML contents representing the supplied submission IDs.
	//  */
	// function exportSubmissions($submissionIds, $context, $user, $opts = array()) {
	// 	$deployment = $this->getExportSubmissionsDeployment($submissionIds, $context, $user, $opt);

	// 	$result = $deployment->processResult;
	// 	$foundErrors = $deployment->isProcessFailed();

	// 	$xml = null;
	// 	if (!$foundErrors && $result) {
	// 		$xml = $result->saveXml();
	// 	}

	// 	return $xml;
	// }

	// function getExportIssuesDeployment($issueIds, $context, $user, $opts = array()) {
	// 	$issueDao = DAORegistry::getDAO('IssueDAO'); /** @var $issueDao IssueDAO */
	// 	$issues = array();
	// 	foreach ($issueIds as $issueId) {
	// 		$issue = $issueDao->getById($issueId, $context->getId());
	// 		if ($issue) $issues[] = $issue;
	// 	}

	// 	$deployment = new NativeImportExportDeployment($context, $user);
	// 	$deployment->export('issue=>native-xml', $issues, $opts);

	// 	return $deployment;
	// }

	// /**
	//  * Get the XML for a set of issues.
	//  * @param $issueIds array Array of issue IDs
	//  * @param $context Context
	//  * @param $user User
	//  * @return string XML contents representing the supplied issue IDs.
	//  */
	// function exportIssues($issueIds, $context, $user, $opts = array()) {
	// 	$deployment = $this->getExportIssuesDeployment($issueIds, $context, $user, $opt);

	// 	$result = $deployment->processResult;
	// 	$foundErrors = $deployment->isProcessFailed();

	// 	$xml = null;
	// 	if (!$foundErrors && $result) {
	// 		$xml = $result->saveXml();
	// 	}

	// 	return $xml;
	// }

	function getBounceTab($request, $title, $bounceUrl, $bounceParameterArray) {
		if (!$request->checkCSRF()) throw new Exception('CSRF mismatch!');
		$json = new JSONMessage(true);
		$json->setEvent('addTab', array(
			'title' => $title,
			'url' => $request->url(null, null, null, array('plugin', $this->getName(), $bounceUrl), $bounceParameterArray),
		));
		header('Content-Type: application/json');
		return $json->getString();
	}

	/**
	 * Create anf download file given it's name and content
	 * @param $filename string
	 * @param $fileContent string
	 * @param $context Context
	 */
	function writeExportedFile($filename, $fileContent, $context) {
		import('lib.pkp.classes.file.FileManager');
		$fileManager = new FileManager();
		$exportFileName = $this->getExportFileName($this->getExportPath(), $filename, $context, '.xml');
		$fileManager->writeFile($exportFileName, $fileContent);

		return $exportFileName;
	}

	/**
	 * Create anf download file given it's name and content
	 * @param $filename string
	 * @param $fileContent string
	 * @param $context Context
	 */
	function downloadExportedFile($exportFileName) {
		import('lib.pkp.classes.file.FileManager');
		$fileManager = new FileManager();
		$fileManager->downloadByPath($exportFileName);
		$fileManager->deleteByPath($exportFileName);
	}

	// function getImportFilter($xmlFile) {
	// 	$filter = 'native-xml=>issue';
	// 	// is this articles import:
	// 	$xmlString = file_get_contents($xmlFile);
	// 	$document = new DOMDocument();
	// 	$document->loadXml($xmlString);
	// 	if (in_array($document->documentElement->tagName, array('article', 'articles'))) {
	// 		$filter = 'native-xml=>article';
	// 	}

	// 	return array($filter, $xmlString);
	// }

	/**
	 * @copydoc PKPImportExportPlugin::usage
	 */
	function usage($scriptName) {
		echo __('plugins.importexport.native.cliUsage', array(
			'scriptName' => $scriptName,
			'pluginName' => $this->getName()
		)) . "\n";
	}

	function executeCLI($scriptName, &$args) {
		return false;
	}
	// /**
	//  * @see PKPImportExportPlugin::executeCLI()
	//  */
	// function executeCLI($scriptName, &$args) {
	// 	$c = new Color();

	// 	$opts = $this->parseOpts($args, ['no-embed', 'use-file-urls']);
	// 	$command = array_shift($args);
	// 	$xmlFile = array_shift($args);
	// 	$journalPath = array_shift($args);

	// 	AppLocale::requireComponents(LOCALE_COMPONENT_APP_MANAGER, LOCALE_COMPONENT_PKP_MANAGER, LOCALE_COMPONENT_PKP_SUBMISSION);

	// 	$journalDao = DAORegistry::getDAO('JournalDAO'); /** @var $journalDao JournalDAO */
	// 	$userDao = DAORegistry::getDAO('UserDAO'); /** @var $userDao UserDAO */

	// 	$journal = $journalDao->getByPath($journalPath);

	// 	if (!$journal) {
	// 		if ($journalPath != '') {
	// 			$this->echoCLIError(__('plugins.importexport.common.error.unknownJournal', array('journalPath' => $journalPath)));
	// 		}
	// 		$this->usage($scriptName);
	// 		return;
	// 	}

	// 	if ($xmlFile && $this->isRelativePath($xmlFile)) {
	// 		$xmlFile = PWD . '/' . $xmlFile;
	// 	}

	// 	switch ($command) {
	// 		case 'import':
	// 			$userName = array_shift($args);
	// 			$user = $userDao->getByUsername($userName);

	// 			if (!$user) {
	// 				if ($userName != '') {
	// 					$this->echoCLIError(__('plugins.importexport.native.error.unknownUser', array('userName' => $userName)));
	// 				}
	// 				$this->usage($scriptName);
	// 				return;
	// 			}

	// 			if (!file_exists($xmlFile)) {
	// 				$this->echoCLIError(__('plugins.importexport.common.export.error.inputFileNotReadable', array('param' => $xmlFile)));

	// 				$this->usage($scriptName);
	// 				return;
	// 			}

	// 			list ($filter, $xmlString) = $this->getImportFilter($xmlFile);

	// 			$deployment = new NativeImportExportDeployment($journal, $user);
	// 			$deployment->setImportPath(dirname($xmlFile));

	// 			$deployment->import($filter, $xmlString);

	// 			$this->getCLIImportResult($deployment);
	// 			$this->getCLIProblems($deployment);
	// 			return;
	// 		case 'export':
	// 			$outputDir = dirname($xmlFile);
	// 			if (!is_writable($outputDir) || (file_exists($xmlFile) && !is_writable($xmlFile))) {
	// 				$this->echoCLIError(__('plugins.importexport.common.export.error.outputFileNotWritable', array('param' => $xmlFile)));

	// 				$this->usage($scriptName);
	// 				return;
	// 			}
	// 			if ($xmlFile != '') switch (array_shift($args)) {
	// 				case 'article':
	// 				case 'articles':
	// 					$deployment = $this->getExportSubmissionsDeployment(
	// 						$args,
	// 						$journal,
	// 						null,
	// 						$opts
	// 					);

	// 					$this->getCLIExportResult($deployment, $xmlFile);
	// 					$this->getCLIProblems($deployment);
	// 					return;
	// 				case 'issue':
	// 				case 'issues':
	// 					$deployment = $this->getExportIssuesDeployment(
	// 						$args,
	// 						$journal,
	// 						null,
	// 						$opts
	// 					);

	// 					$this->getCLIExportResult($deployment, $xmlFile);
	// 					$this->getCLIProblems($deployment);

	// 					return;
	// 			}
	// 			break;
	// 	}
	// 	$this->usage($scriptName);
	// }

	function echoCLIError($errorMessage, $c = null) {
		if (!isset($c)) $c = new Color();

		echo $c(__('plugins.importexport.common.cliError'))->white()->bold()->highlight('red') . PHP_EOL;
		echo $c($errorMessage)->red()->bold() . PHP_EOL;
	}

	function getCLIExportResult($deployment, $xmlFile) {
		$c = new Color();

		$result = $deployment->processResult;
		$foundErrors = $deployment->isProcessFailed();

		if (!$foundErrors) {
			$xml = $result->saveXml();
			file_put_contents($xmlFile, $xml);
			echo $c(__('plugins.importexport.native.export.completed'))->green()->bold() . PHP_EOL . PHP_EOL;
		} else {
			echo $c(__('plugins.importexport.native.processFailed'))->red()->bold() . PHP_EOL . PHP_EOL;
		}
	}

	function getCLIImportResult($deployment) {
		$c = new Color();

		$result = $deployment->processResult;
		$foundErrors = $deployment->isProcessFailed();
		$importedRootObjects = $deployment->getImportedRootEntitiesWithNames();

		if (!$foundErrors) {
			echo $c(__('plugins.importexport.native.importComplete'))->green()->bold() . PHP_EOL . PHP_EOL;

			foreach ($importedRootObjects as $contentItemName => $contentItemArrays) {
				echo $c($contentItemName)->white()->bold()->highlight('black') . PHP_EOL;
				foreach ($contentItemArrays as $contentItemArray) {
					foreach ($contentItemArray as $contentItem) {
						echo $c('-' . $contentItemName)->white()->bold() . PHP_EOL;
					}
				}
			}
		} else {
			echo $c(__('plugins.importexport.native.processFailed'))->red()->bold() . PHP_EOL . PHP_EOL;
		}
	}

	function getCLIProblems($deployment) {
		$result = $deployment->processResult;
		$problems = $deployment->getWarningsAndErrors();
		$foundErrors = $deployment->isProcessFailed();

		$warnings = array();
		if (array_key_exists('warnings', $problems)) {
			$warnings = $problems['warnings'];
		}

		$errors = array();
		if (array_key_exists('errors', $problems)) {
			$errors = $problems['errors'];
		}

		// Are there any import warnings? Display them.
		$this->displayCLIIssues($warnings, __('plugins.importexport.common.warningsEncountered'));
		$this->displayCLIIssues($errors, __('plugins.importexport.common.errorsOccured'));
	}

	function displayCLIIssues($relatedIssues, $title) {
		$c = new Color();

		if(count($relatedIssues) > 0) {
			echo $c($title)->black()->bold()->highlight('light_gray') . PHP_EOL;
			$i = 0;
			foreach($relatedIssues as $relatedTypeName => $allRelatedTypes) {
				foreach($allRelatedTypes as $thisTypeId => $thisTypeIds) {
					if(count($thisTypeIds) > 0) {
						echo ++$i . '.' . $relatedTypeName . PHP_EOL;
						foreach($thisTypeIds as $idRelatedItems) {
							foreach($idRelatedItems as $relatedItemMessage) {
								echo '- ' . $relatedItemMessage . PHP_EOL;
							}
						}
					}
				}
			}
		}
	}

	/**
	 * Pull out getopt style long options.
	 * WARNING: This method is checked for by name in DepositPackage in the PLN plugin
	 * to determine if options are supported!
	 * @param &$args array
	 * #param $optCodes array
	 */
	function parseOpts(&$args, $optCodes) {
		$newArgs = [];
		$opts = [];
		$sticky = null;
		foreach ($args as $arg) {
			if ($sticky) {
				$opts[$sticky] = $arg;
				$sticky = null;
				continue;
			}
			if (substr($arg, 0, 2) != '--') {
				$newArgs[] = $arg;
				continue;
			}
			$opt = substr($arg, 2);
			if (in_array($opt, $optCodes)) {
				$opts[$opt] = true;
				continue;
			}
			if (in_array($opt . ":", $optCodes)) {
				$sticky = $opt;
				continue;
			}
		}
		$args = $newArgs;
		return $opts;
	}

	public function setDeployment($deployment) {
		$this->_childDeployment = $deployment;
	}

	public function getDeployment() {
		return $this->_childDeployment;
	}

	function getImportFilter($xmlFile) {
		return false;
	}

	function getExportFilter($exportType) {
		return false;
	}
}


