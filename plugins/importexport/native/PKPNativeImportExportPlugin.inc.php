<?php

/**
 * @file plugins/importexport/native/PKPNativeImportExportPlugin.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPNativeImportExportPlugin
 * @ingroup plugins_importexport_native
 *
 * @brief Native XML import/export plugin
 */

import('lib.pkp.classes.plugins.ImportExportPlugin');
import('lib.pkp.plugins.importexport.native.PKPNativeImportExportCLIDeployment');
import('lib.pkp.plugins.importexport.native.PKPNativeImportExportCLIToolKit');

define('ASSOC_TYPE_NONE', -1000);

abstract class PKPNativeImportExportPlugin extends ImportExportPlugin {

	/**
	 * The deployment that processes import/export operations
	 * @var PKPNativeImportExportDeployment
	 */
	var $_childDeployment = null;

	/**
	 * CLI Deployment for import/export operations
	 * @var PKPNativeImportExportCLIDeployment
	 */
	var $cliDeployment = null;

	/**
	 * Display operation result
	 * @var string
	 */
	var $result = null;

	/**
	 * Indication that the parent code has managed the display operation
	 * @var bool
	 */
	var $isResultManaged = false;

	/**
	 * The helper for CLI import/export operations
	 * @var PKPNativeImportExportCLIToolKit
	 */
	var $cliToolkit;

	/**
	 *
	 * @var string
	 */
	var $opType;

	function __construct() {
		$this->cliToolkit = new PKPNativeImportExportCLIToolKit();
	}

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
	 * @see ImportExportPlugin::display()
	 */
	function display($args, $request) {
		$templateMgr = TemplateManager::getManager($request);
		parent::display($args, $request);

		$context = $request->getContext();
		$user = $request->getUser();
		$deployment = $this->getAppSpecificDeployment($context, $user);
		$this->setDeployment($deployment);

		$this->opType = array_shift($args);
		switch ($this->opType) {
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

				$this->isResultManaged = true;
				break;
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

				$this->result = $json->getString();
				$this->isResultManaged = true;

				break;
			case 'importBounce':
				$tempFileId = $request->getUserVar('temporaryFileId');

				if (empty($tempFileId)) {
					$this->result = new JSONMessage(false);
					$this->isResultManaged = true;
					break;
				}

				$tab = $this->getBounceTab($request,
					__('plugins.importexport.native.results'),
					'import',
					array('temporaryFileId' => $tempFileId)
				);

				$this->result = $tab;
				$this->isResultManaged = true;
				break;
			case 'exportSubmissionsBounce':
				$tab = $this->getBounceTab($request,
					__('plugins.importexport.native.export.submissions.results'),
						'exportSubmissions',
						array('selectedSubmissions' => $request->getUserVar('selectedSubmissions'))
				);

				$this->result = $tab;
				$this->isResultManaged = true;

				break;
			case 'import':
				$temporaryFilePath = $this->getImportedFilePath($request->getUserVar('temporaryFileId'), $user);
				list ($filter, $xmlString) = $this->getImportFilter($temporaryFilePath);
				$result = $this->getImportTemplateResult($filter, $xmlString, $this->getDeployment(), $templateMgr);

				$this->result = $result;
				$this->isResultManaged = true;

				break;
			case 'exportSubmissions':
				$submissionIds = (array) $request->getUserVar('selectedSubmissions');

				$this->getExportSubmissionsDeployment($submissionIds, $this->_childDeployment);

				$result = $this->getExportTemplateResult($this->getDeployment(), $templateMgr, 'submissions');

				$this->result = $result;
				$this->isResultManaged = true;

				break;
			case 'downloadExportFile':
				$downloadPath = $request->getUserVar('downloadFilePath');
				$this->downloadExportedFile($downloadPath);

				$this->isResultManaged = true;

				break;
		}
	}

	/**
	 * Get the submissions and proceed to the export
	 * @param $submissionIds array Array of submissions to export
	 * @param $deployment PKPNativeImportExportDeployment
	 * @param $opts array
	 */
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

	/**
	 * Save the export result as an XML
	 * @param $deployment PKPNativeImportExportDeployment
	 * @return string
	 */
	function exportResultXML($deployment) {
		$result = $deployment->processResult;
		$foundErrors = $deployment->isProcessFailed();

		$xml = null;
		if (!$foundErrors && $result) {
			$xml = $result->saveXml();
		}

		return $xml;
	}

	/**
	 * Gets template result for the export process
	 * @param $deployment PKPNativeImportExportDeployment
	 * @param $templateMgr PKPTemplateManager
	 * @param $exportFileName string
	 * @return string
	 */
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

	/**
	 * Gets template result for the import process
	 * @param $filter string
	 * @param $xmlString string
	 * @param $deployment PKPNativeImportExportDeployment
	 * @param $templateMgr PKPTemplateManager
	 * @return string
	 */
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

	/**
	 * Gets the imported file path
	 * @param $temporaryFileId int
	 * @param $user User
	 * @return string
	 */
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

	/**
	 * Gets a tab to display after the import/export operation is over
	 * @param $request PKPRequest
	 * @param $title string
	 * @param $bounceUrl string
	 * @param $bounceParameterArray array
	 * @return string
	 */
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
	 * Download file given it's name
	 * @param $exportFileName string
	 */
	function downloadExportedFile($exportFileName) {
		import('lib.pkp.classes.file.FileManager');
		$fileManager = new FileManager();
		$fileManager->downloadByPath($exportFileName);
		$fileManager->deleteByPath($exportFileName);
	}

	/**
	 * Create file given it's name and content
	 * @param $filename string
	 * @param $fileContent string
	 * @param $context Context
	 * @return string
	 */
	function writeExportedFile($filename, $fileContent, $context) {
		import('lib.pkp.classes.file.FileManager');
		$fileManager = new FileManager();
		$exportFileName = $this->getExportFileName($this->getExportPath(), $filename, $context, '.xml');
		$fileManager->writeFile($exportFileName, $fileContent);

		return $exportFileName;
	}

	/**
	 * Get the XML for a set of submissions.
	 * @param $submissionIds array Array of submission IDs
	 * @param $context Context
	 * @param $user User|null
	 * @param $opts array
	 * @return string XML contents representing the supplied submission IDs.
	 */
	function exportSubmissions($submissionIds, $context, $user, $opts = array()) {
		$appSpecificDeployment = $this->getAppSpecificDeployment($context, null);
		$this->setDeployment($appSpecificDeployment);

		$this->getExportSubmissionsDeployment($submissionIds, $appSpecificDeployment, $opts);

		return $this->exportResultXML($appSpecificDeployment);
	}

	/**
	 * @copydoc PKPImportExportPlugin::usage
	 */
	function usage($scriptName) {
		echo __('plugins.importexport.native.cliUsage', array(
			'scriptName' => $scriptName,
			'pluginName' => $this->getName()
		)) . "\n";
	}

	/**
	 * @see PKPImportExportPlugin::executeCLI()
	 */
	function executeCLI($scriptName, &$args) {
		$cliDeployment = new PKPNativeImportExportCLIDeployment($scriptName, $args);
		$this->cliDeployment = $cliDeployment;

		AppLocale::requireComponents(LOCALE_COMPONENT_APP_MANAGER, LOCALE_COMPONENT_PKP_MANAGER, LOCALE_COMPONENT_PKP_SUBMISSION);

		$contextDao = Application::getContextDAO(); /** @var $contextDao ContextDAO */
		$userDao = DAORegistry::getDAO('UserDAO'); /** @var $userDao UserDAO */

		$contextPath = $cliDeployment->contextPath;
		$context = $contextDao->getByPath($contextPath);

		if (!$context) {
			if ($contextPath != '') {
				$this->cliToolkit->echoCLIError(__('plugins.importexport.common.error.unknownJournal', array('contextPath' => $contextPath)));
			}
			$this->usage($scriptName);
			return true;
		}

		$xmlFile = $cliDeployment->xmlFile;
		if ($xmlFile && $this->isRelativePath($xmlFile)) {
			$xmlFile = PWD . '/' . $xmlFile;
		}

		$appSpecificDeployment = $this->getAppSpecificDeployment($context, null);
		$this->setDeployment($appSpecificDeployment);

		switch ($cliDeployment->command) {
			case 'import':
				$userName = $cliDeployment->userName;
				$user = $userDao->getByUsername($userName);

				if (!$user) {
					if ($userName != '') {
						$this->cliToolkit->echoCLIError(__('plugins.importexport.native.error.unknownUser', array('userName' => $userName)));
					}
					$this->usage($scriptName);
					return true;
				}

				if (!file_exists($xmlFile)) {
					$this->cliToolkit->echoCLIError(__('plugins.importexport.common.export.error.inputFileNotReadable', array('param' => $xmlFile)));

					$this->usage($scriptName);
					return true;
				}

				list ($filter, $xmlString) = $this->getImportFilter($xmlFile);

				$deployment = $this->getDeployment(); /** @var $deployment PKPNativeImportExportDeployment */
				$deployment->setUser($user);
				$deployment->setImportPath(dirname($xmlFile));

				$deployment->import($filter, $xmlString);

				$this->cliToolkit->getCLIImportResult($deployment);
				$this->cliToolkit->getCLIProblems($deployment);
				return true;
			case 'export':
				$deployment = $this->getDeployment(); /** @var $deployment PKPNativeImportExportDeployment */

				$outputDir = dirname($xmlFile);
				if (!is_writable($outputDir) || (file_exists($xmlFile) && !is_writable($xmlFile))) {
					$this->cliToolkit->echoCLIError(__('plugins.importexport.common.export.error.outputFileNotWritable', array('param' => $xmlFile)));

					$this->usage($scriptName);
					return true;
				}

				if ($cliDeployment->xmlFile != '') {
					switch ($cliDeployment->exportEntity) {
						case $deployment->getSubmissionNodeName():
						case $deployment->getSubmissionsNodeName():
							$this->getExportSubmissionsDeployment(
								$cliDeployment->args,
								$deployment,
								$cliDeployment->opts
							);

							$this->cliToolkit->getCLIExportResult($deployment, $xmlFile);
							$this->cliToolkit->getCLIProblems($deployment);
							return true;
						default:
							return false;
					}
				}
				return true;
		}
	}

	/**
	 * Set the deployment that processes import/export operations
	 */
	public function setDeployment($deployment) {
		$this->_childDeployment = $deployment;
	}

	/**
	 * Get the deployment that processes import/export operations
	 */
	public function getDeployment() {
		return $this->_childDeployment;
	}

	/**
	 * Define the appropriate import filter given the imported XML file path
	 * @param $xmlFile string
	 * @return array Containing the filter and the xmlString of the imported file
	 */
	abstract public function getImportFilter($xmlFile);

	/**
	 * Define the appropriate export filter given the export operation
	 * @param $exportType string
	 * @return string
	 */
	abstract public function getExportFilter($exportType);

	/**
	 * Get the application specific deployment object
	 * @param $context Context
	 * @param $user User
	 * @return NativeImportExportDeployment
	 */
	abstract public function getAppSpecificDeployment($context, $user);
}


