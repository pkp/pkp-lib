<?php

/**
 * @file plugins/importexport/native/NativeImportExportPlugin.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NativeImportExportPlugin
 *
 * @ingroup plugins_importexport_native
 *
 * @brief Native XML import/export plugin
 */

namespace APP\plugins\importexport\native;

use APP\template\TemplateManager;

class NativeImportExportPlugin extends \PKP\plugins\importexport\native\PKPNativeImportExportPlugin
{
    /**
     * @see ImportExportPlugin::display()
     */
    public function display($args, $request)
    {
        parent::display($args, $request);

        if ($this->isResultManaged) {
            if ($this->result) {
                return $this->result;
            }

            return false;
        }

        $templateMgr = TemplateManager::getManager($request);

        switch ($this->opType) {
            default:
                $dispatcher = $request->getDispatcher();
                $dispatcher->handle404();
        }
    }

    /**
     * @see PKPNativeImportExportPlugin::getImportFilter
     */
    public function getImportFilter($xmlFile)
    {
        $filter = 'native-xml=>preprint';

        $xmlString = file_get_contents($xmlFile);

        return [$filter, $xmlString];
    }

    /**
     * @see PKPNativeImportExportPlugin::getExportFilter
     */
    public function getExportFilter($exportType)
    {
        $filter = false;
        if ($exportType == 'exportSubmissions') {
            $filter = 'preprint=>native-xml';
        }

        return $filter;
    }

    /**
     * @see PKPNativeImportExportPlugin::getAppSpecificDeployment
     */
    public function getAppSpecificDeployment($journal, $user)
    {
        return new NativeImportExportDeployment($journal, $user);
    }
}
