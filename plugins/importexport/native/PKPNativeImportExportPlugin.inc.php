<?php

/**
 * @file plugins/importexport/native/PKPNativeImportExportPlugin.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPNativeImportExportPlugin
 * @ingroup plugins_importexport_native
 *
 * @brief Native XML import/export plugin
 */

import('lib.pkp.plugins.importexport.native.PKPNativeImportExportCLIDeployment');
import('lib.pkp.plugins.importexport.native.PKPNativeImportExportCLIToolKit');

use APP\core\Application;
use APP\template\TemplateManager;
use PKP\core\JSONMessage;
use PKP\file\TemporaryFileManager;

use PKP\plugins\ImportExportPlugin;
use PKP\plugins\PluginRegistry;

abstract class PKPNativeImportExportPlugin extends ImportExportPlugin
{
    /** @var PKPNativeImportExportCLIDeployment CLI Deployment for import/export operations */
    protected $cliDeployment = null;

    /** @var string Display operation result */
    protected $result = null;

    /** @var bool Indication that the parent code has managed the display operation */
    protected $isResultManaged = false;

    /** @var PKPNativeImportExportCLIToolKit The helper for CLI import/export operations */
    protected $cliToolkit;

    /** @var string Operation type for display method */
    protected $opType;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->cliToolkit = new PKPNativeImportExportCLIToolKit();
    }

    /**
     * @copydoc Plugin::register()
     *
     * @param null|mixed $mainContextId
     */
    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path, $mainContextId);
        if (!Application::isReady()) {
            return $success;
        }
        if ($success && $this->getEnabled()) {
            $this->addLocaleData();
            $this->import('NativeImportExportDeployment');
        }
        return $success;
    }

    /**
     * Get the name of this plugin. The name must be unique within
     * its category.
     *
     * @return string name of plugin
     */
    public function getName()
    {
        return 'NativeImportExportPlugin';
    }

    /**
     * Get the display name.
     *
     * @return string
     */
    public function getDisplayName()
    {
        return __('plugins.importexport.native.displayName');
    }

    /**
     * Get the display description.
     *
     * @return string
     */
    public function getDescription()
    {
        return __('plugins.importexport.native.description');
    }

    /**
     * @copydoc ImportExportPlugin::getPluginSettingsPrefix()
     */
    public function getPluginSettingsPrefix()
    {
        return 'native';
    }

    /**
     * @see ImportExportPlugin::display()
     */
    public function display($args, $request)
    {
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
                $temporaryFileManager = new TemporaryFileManager();
                $temporaryFile = $temporaryFileManager->handleUpload('uploadedFile', $user->getId());
                if ($temporaryFile) {
                    $json = new JSONMessage(true);
                    $json->setAdditionalAttributes([
                        'temporaryFileId' => $temporaryFile->getId()
                    ]);
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

                $tab = $this->getBounceTab(
                    $request,
                    __('plugins.importexport.native.results'),
                    'import',
                    ['temporaryFileId' => $tempFileId]
                );

                $this->result = $tab;
                $this->isResultManaged = true;
                break;
            case 'exportSubmissionsBounce':
                $tab = $this->getBounceTab(
                    $request,
                    __('plugins.importexport.native.export.submissions.results'),
                    'exportSubmissions',
                    ['selectedSubmissions' => $request->getUserVar('selectedSubmissions')]
                );

                $this->result = $tab;
                $this->isResultManaged = true;

                break;
            case 'import':
                if (!$request->checkCSRF()) {
                    throw new Exception('CSRF mismatch!');
                }
                $temporaryFilePath = $this->getImportedFilePath($request->getUserVar('temporaryFileId'), $user);
                [$filter, $xmlString] = $this->getImportFilter($temporaryFilePath);
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
     * Get the XML for a set of submissions.
     *
     * @param array $submissionIds Array of submission IDs
     * @param Context $context
     * @param User|null $user
     * @param array $opts
     *
     * @return string XML contents representing the supplied submission IDs.
     */
    public function exportSubmissions($submissionIds, $context, $user, $opts = [])
    {
        $appSpecificDeployment = $this->getAppSpecificDeployment($context, null);
        $this->setDeployment($appSpecificDeployment);

        $this->getExportSubmissionsDeployment($submissionIds, $appSpecificDeployment, $opts);

        return $this->exportResultXML($appSpecificDeployment);
    }

    /**
     * @copydoc PKPImportExportPlugin::usage
     */
    public function usage($scriptName)
    {
        echo __('plugins.importexport.native.cliUsage', [
            'scriptName' => $scriptName,
            'pluginName' => $this->getName()
        ]) . "\n";
    }

    /**
     * @see PKPImportExportPlugin::executeCLI()
     */
    public function executeCLI($scriptName, &$args)
    {
        $cliDeployment = new PKPNativeImportExportCLIDeployment($scriptName, $args);
        $this->cliDeployment = $cliDeployment;

        $contextDao = Application::getContextDAO(); /** @var ContextDAO $contextDao */

        $contextPath = $cliDeployment->contextPath;
        $context = $contextDao->getByPath($contextPath);

        if (!$context) {
            if ($contextPath != '') {
                $this->cliToolkit->echoCLIError(__('plugins.importexport.common.error.unknownContext', ['contextPath' => $contextPath]));
            }
            $this->usage($scriptName);
            return true;
        }

        PluginRegistry::loadCategory('pubIds', true, $context->getId());

        $xmlFile = $cliDeployment->xmlFile;
        if ($xmlFile && $this->isRelativePath($xmlFile)) {
            $xmlFile = PWD . '/' . $xmlFile;
        }

        $appSpecificDeployment = $this->getAppSpecificDeployment($context, null);
        $this->setDeployment($appSpecificDeployment);

        switch ($cliDeployment->command) {
            case 'import':
                $user = Application::get()->getRequest()->getUser();

                if (!$user) {
                    $this->cliToolkit->echoCLIError(__('plugins.importexport.native.error.unknownUser'));
                    $this->usage($scriptName);
                    return true;
                }

                if (!file_exists($xmlFile)) {
                    $this->cliToolkit->echoCLIError(__('plugins.importexport.common.export.error.inputFileNotReadable', ['param' => $xmlFile]));

                    $this->usage($scriptName);
                    return true;
                }

                [$filter, $xmlString] = $this->getImportFilter($xmlFile);

                $deployment = $this->getDeployment(); /** @var PKPNativeImportExportDeployment $deployment */
                $deployment->setUser($user);
                $deployment->setImportPath(dirname($xmlFile));

                $deployment->import($filter, $xmlString);

                $this->cliToolkit->getCLIImportResult($deployment);
                $this->cliToolkit->getCLIProblems($deployment);
                return true;
            case 'export':
                $deployment = $this->getDeployment(); /** @var PKPNativeImportExportDeployment $deployment */

                $outputDir = dirname($xmlFile);
                if (!is_writable($outputDir) || (file_exists($xmlFile) && !is_writable($xmlFile))) {
                    $this->cliToolkit->echoCLIError(__('plugins.importexport.common.export.error.outputFileNotWritable', ['param' => $xmlFile]));

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
}
