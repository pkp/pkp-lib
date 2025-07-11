<?php

/**
 * @file classes/plugins/ImportExportPlugin.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ImportExportPlugin
 *
 * @ingroup plugins
 *
 * @brief Abstract class for import/export plugins
 */

namespace PKP\plugins;

use APP\facades\Repo;
use APP\template\TemplateManager;
use DateTime;
use Exception;
use PKP\config\Config;
use PKP\context\Context;
use PKP\core\JSONMessage;
use PKP\core\PKPApplication;
use PKP\core\PKPRequest;
use PKP\core\PKPSessionGuard;
use PKP\db\DAORegistry;
use PKP\file\FileManager;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\RedirectAction;
use PKP\plugins\importexport\native\PKPNativeImportExportDeployment;
use PKP\plugins\importexport\PKPImportExportDeployment;

abstract class ImportExportPlugin extends Plugin
{
    /** @var PKPImportExportDeployment The deployment that processes import/export operations */
    public $_childDeployment = null;

    /** @var \APP\core\Request Request made available for plugin URL generation */
    public $_request;

    protected const EXPORT_FILE_DATE_PART_FORMAT = 'Ymd-His';

    /**
     * Execute import/export tasks using the command-line interface.
     *
     * @param string $scriptName The name of the command-line script (displayed as usage info)
     * @param array $args Parameters to the plugin
     */
    abstract public function executeCLI($scriptName, &$args);

    /**
     * Display the command-line usage information
     *
     * @param string $scriptName
     */
    abstract public function usage($scriptName);

    /**
     * Whether this plugin provides CLI import/export
     */
    public function supportsCLI(): bool
    {
        return true;
    }

    /**
     * @copydoc Plugin::getActions()
     */
    public function getActions($request, $actionArgs)
    {
        $dispatcher = $request->getDispatcher();
        return array_merge(
            [
                new LinkAction(
                    'settings',
                    new RedirectAction($dispatcher->url(
                        $request,
                        PKPApplication::ROUTE_PAGE,
                        null,
                        'management',
                        'importexport',
                        ['plugin', $this->getName()]
                    )),
                    __('manager.importExport'),
                    null
                ),
            ],
            parent::getActions($request, $actionArgs)
        );
    }

    /**
     * Display the import/export plugin.
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function display($args, $request)
    {
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->registerPlugin('function', 'plugin_url', $this->pluginUrl(...));
        $this->_request = $request; // Store this for use by the pluginUrl function
        $templateMgr->assign([
            'breadcrumbs' => [
                [
                    'id' => 'tools',
                    'name' => __('navigation.tools'),
                    'url' => $request->getRouter()->url($request, null, 'management', 'tools'),
                ],
                [
                    'id' => $this->getPluginPath(),
                    'name' => $this->getDisplayName()
                ],
            ],
            'pageTitle' => $this->getDisplayName(),
        ]);
    }

    /**
     * Generate a URL into the plugin.
     *
     * @see calling conventions at http://www.smarty.net/docsv2/en/api.register.function.tpl
     *
     * @param array $params
     * @param \Smarty $smarty
     *
     * @return string
     */
    public function pluginUrl($params, $smarty)
    {
        $dispatcher = $this->_request->getDispatcher();
        return $dispatcher->url($this->_request, PKPApplication::ROUTE_PAGE, null, 'management', 'importexport', array_merge(['plugin', $this->getName(), $params['path'] ?? []]));
    }

    /**
     * Check if this is a relative path to the xml document
     * that describes public identifiers to be imported.
     *
     * @param string $url path to the xml file
     */
    public function isRelativePath($url)
    {
        // FIXME This is not very comprehensive, but will work for now.
        if ($this->isAllowedMethod($url)) {
            return false;
        }
        if ($url[0] == '/') {
            return false;
        }
        return true;
    }

    /**
     * Determine whether the specified URL describes an allowed protocol.
     *
     * @param string $url
     *
     * @return bool
     */
    public function isAllowedMethod($url)
    {
        $allowedPrefixes = [
            'http://',
            'ftp://',
            'https://',
            'ftps://'
        ];
        foreach ($allowedPrefixes as $prefix) {
            if (substr($url, 0, strlen($prefix)) === $prefix) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get the plugin ID used as plugin settings prefix.
     *
     * @return string
     */
    public function getPluginSettingsPrefix()
    {
        return '';
    }

    /**
     * Return the plugin export directory.
     *
     * @return string The export directory path.
     */
    public function getExportPath()
    {
        return Config::getVar('files', 'files_dir') . '/temp/';
    }

    /**
     * Return the whole export file name.
     *
     * @param string $basePath Base path for temporary file storage
     * @param string $objectsFileNamePart Part different for each object type.
     * @param string $extension
     *
     * @return string
     */
    public function getExportFileName($basePath, $objectsFileNamePart, Context $context, $extension = '.xml', ?DateTime $dateFilenamePart = null)
    {
        $dateFilenamePartString = date(self::EXPORT_FILE_DATE_PART_FORMAT);

        if (isset($dateFilenamePart)) {
            $dateFilenamePartString = $dateFilenamePart->format(self::EXPORT_FILE_DATE_PART_FORMAT);
        }

        return $basePath . $this->getPluginSettingsPrefix() . '-' . $dateFilenamePartString . '-' . $objectsFileNamePart . '-' . $context->getId() . $extension;
    }

    /**
     * Display XML validation errors.
     *
     * @param array $errors
     * @param string $xml
     */
    public function displayXMLValidationErrors($errors, $xml)
    {
        if (PKPSessionGuard::isSessionDisable()) {
            echo __('plugins.importexport.common.validationErrors') . "\n";
            foreach ($errors as $error) {
                echo trim($error->message) . "\n";
            }
            libxml_clear_errors();
            echo __('plugins.importexport.common.invalidXML') . "\n";
            echo $xml . "\n";
        } else {
            header('Content-type: text/html; charset=utf-8');
            echo '<html><body>';
            echo '<h2>' . __('plugins.importexport.common.validationErrors') . '</h2>';
            foreach ($errors as $error) {
                echo '<p>' . trim($error->message) . '</p>';
            }
            libxml_clear_errors();
            echo '<h3>' . __('plugins.importexport.common.invalidXML') . '</h3>';
            echo '<p><pre>' . htmlspecialchars($xml) . '</pre></p>';
            echo '</body></html>';
        }
        throw new Exception(__('plugins.importexport.common.error.validation'));
    }

    /**
     * Set the deployment that processes import/export operations
     */
    public function setDeployment($deployment)
    {
        $this->_childDeployment = $deployment;
    }

    /**
     * Get the deployment that processes import/export operations
     *
     * @return PKPImportExportDeployment
     */
    public function getDeployment()
    {
        return $this->_childDeployment;
    }

    /**
     * Get the submissions and proceed to the export
     *
     * @param array $submissionIds Array of submissions to export
     * @param PKPNativeImportExportDeployment $deployment
     * @param array $opts
     */
    public function getExportSubmissionsDeployment($submissionIds, $deployment, $opts = [])
    {
        $filter = $this->getExportFilter('exportSubmissions');

        $submissions = [];
        foreach ($submissionIds as $submissionId) {
            $submission = Repo::submission()->get($submissionId);

            if ($submission && $submission->getData('contextId') == $deployment->getContext()->getId()) {
                $submissions[] = $submission;
            }
        }

        $deployment->export($filter, $submissions, $opts);
    }

    /**
     * Save the export result as an XML
     *
     * @param PKPNativeImportExportDeployment $deployment
     *
     * @return string
     */
    public function exportResultXML($deployment)
    {
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
     *
     * @param PKPNativeImportExportDeployment $deployment
     * @param \PKP\template\PKPTemplateManager $templateMgr
     * @param string $exportFileName
     *
     * @return string
     */
    public function getExportTemplateResult($deployment, $templateMgr, $exportFileName)
    {
        $result = $deployment->processResult;
        $problems = $deployment->getWarningsAndErrors();
        $foundErrors = $deployment->isProcessFailed();

        if (!$foundErrors) {
            $exportXml = $result->saveXml();

            if ($exportXml) {
                $dateFilenamePart = new DateTime();
                $this->writeExportedFile($exportFileName, $exportXml, $deployment->getContext(), $dateFilenamePart);
                $templateMgr->assign('exportedFileDatePart', $dateFilenamePart->format(self::EXPORT_FILE_DATE_PART_FORMAT));
                $templateMgr->assign('exportedFileContentNamePart', $exportFileName);
            }
        }

        $templateMgr->assign('validationErrors', $deployment->getXMLValidationErrors());

        $templateMgr->assign('errorsAndWarnings', $problems);
        $templateMgr->assign('errorsFound', $foundErrors);

        // Display the results
        $json = new JSONMessage(true, $templateMgr->fetch('plugins/importexport/resultsExport.tpl'));
        header('Content-Type: application/json');
        return $json->getString();
    }

    /**
     * Gets template result for the import process
     *
     * @param string $filter
     * @param string $xmlString
     * @param PKPNativeImportExportDeployment $deployment
     * @param \PKP\template\PKPTemplateManager $templateMgr
     *
     * @return string
     */
    public function getImportTemplateResult($filter, $xmlString, $deployment, $templateMgr)
    {
        $deployment->import($filter, $xmlString);

        $templateMgr->assign('content', $deployment->processResult);
        $templateMgr->assign('validationErrors', $deployment->getXMLValidationErrors());

        $problems = $deployment->getWarningsAndErrors();
        $foundErrors = $deployment->isProcessFailed();

        $templateMgr->assign('errorsAndWarnings', $problems);
        $templateMgr->assign('errorsFound', $foundErrors);

        $templateMgr->assign('importedRootObjects', $deployment->getImportedRootEntitiesWithNames());

        // Display the results
        $json = new JSONMessage(true, $templateMgr->fetch('plugins/importexport/resultsImport.tpl'));
        header('Content-Type: application/json');
        return $json->getString();
    }

    /**
     * Gets the imported file path
     *
     * @param int $temporaryFileId
     * @param \PKP\user\User $user
     *
     * @return string
     */
    public function getImportedFilePath($temporaryFileId, $user)
    {
        $temporaryFileDao = DAORegistry::getDAO('TemporaryFileDAO'); /** @var \PKP\file\TemporaryFileDAO $temporaryFileDao */

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
     *
     * @param PKPRequest $request
     * @param string $title
     * @param string $bounceUrl
     * @param array $bounceParameterArray
     *
     * @return string
     */
    public function getBounceTab($request, $title, $bounceUrl, $bounceParameterArray)
    {
        if (!$request->checkCSRF()) {
            throw new Exception('CSRF mismatch!');
        }
        $json = new JSONMessage(true);
        $json->setEvent('addTab', [
            'title' => $title,
            'url' => $request->url(
                null,
                null,
                null,
                ['plugin', $this->getName(), $bounceUrl],
                array_merge($bounceParameterArray, ['csrfToken' => $request->getSession()->token()])
            ),
        ]);
        header('Content-Type: application/json');
        return $json->getString();
    }

    /**
     * Download file given its name
     */
    public function downloadExportedFile(string $exportedFileContentNamePart, string $exportedFileDatePart, PKPImportExportDeployment $deployment)
    {
        $date = DateTime::createFromFormat(self::EXPORT_FILE_DATE_PART_FORMAT, $exportedFileDatePart);
        if (!$date) {
            return false;
        }

        $exportFileName = $this->getExportFileName($this->getExportPath(), $exportedFileContentNamePart, $deployment->getContext(), '.xml', $date);
        $fileManager = new FileManager();
        $fileManager->downloadByPath($exportFileName);
        $fileManager->deleteByPath($exportFileName);

        return true;
    }

    /**
     * Create file given its name and content
     *
     *
     * @return string
     */
    public function writeExportedFile(string $filename, string $fileContent, Context $context, ?DateTime $dateFilenamePart = null)
    {
        $fileManager = new FileManager();
        $exportFileName = $this->getExportFileName($this->getExportPath(), $filename, $context, '.xml', $dateFilenamePart);
        $fileManager->writeFile($exportFileName, $fileContent);

        return $exportFileName;
    }
}
