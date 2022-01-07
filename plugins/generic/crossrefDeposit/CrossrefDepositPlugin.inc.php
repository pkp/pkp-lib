<?php
/**
 * @file plugins/generic/crossrefDeposit/CrossrefDepositPlugin.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CrossrefDepositPlugin
 * @ingroup plugins_generic_crossrefDeposit
 *
 * @brief Deposit DOIs during the publish action
 */

use APP\core\Application;
use APP\facades\Repo;
use PKP\file\FileManager;
use PKP\plugins\PluginRegistry;

class CrossrefDepositPlugin extends GenericPlugin
{
    /**
     * @copydoc Plugin::register
     *
     * @param null|mixed $mainContextId
     */
    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path);
        if ($success && $this->getEnabled()) {
            \HookRegistry::register('Publication::publish', [$this, 'depositOnPublish']);
        }
        return $success;
    }

    /**
     * @copydoc PKPPlugin::getDisplayName
     */
    public function getDisplayName()
    {
        return __('plugins.generic.crossrefDeposit.name');
    }

    /**
     * @copydoc PKPPlugin::getDescription
     */
    public function getDescription()
    {
        return __('plugins.generic.crossrefDeposit.description');
    }

    /**
     * Deposit DOIs on publish
     *
     * @param string $hookName
     * @param array $args [
     *		@option Publication The new version of the publication
     *		@option Publication The old version of the publication
     * ]
     */
    public function depositOnPublish($hookName, $args)
    {
        PluginRegistry::loadCategory('importexport');
        $crossrefExportPlugin = PluginRegistry::getPlugin('importexport', 'CrossRefExportPlugin');

        $newPublication = $args[0];
        $objects[] = Repo::submission()->get($newPublication->getData('submissionId'));
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        $filter = $crossrefExportPlugin->getSubmissionFilter();
        $objectsFileNamePart = 'preprints';
        $noValidation = null;

        $fileManager = new FileManager();
        $resultErrors = [];
        $errorsOccured = false;

        foreach ($objects as $object) {
            $exportXml = $crossrefExportPlugin->exportXML([$object], $filter, $context, $noValidation);
            $objectsFileNamePart = $objectsFileNamePart . '-' . $object->getId();
            $exportFileName = $crossrefExportPlugin->getExportFileName($crossrefExportPlugin->getExportPath(), $objectsFileNamePart, $context, '.xml');
            $fileManager->writeFile($exportFileName, $exportXml);
            $result = $crossrefExportPlugin->depositXML($object, $context, $exportFileName);
            if (!$result) {
                $errorsOccured = true;
            }
            if (is_array($result)) {
                $resultErrors[] = $result;
            }
            $fileManager->deleteByPath($exportFileName);
        }
        return true;
    }
}
