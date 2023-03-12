<?php

/**
 * @file plugins/importexport/native/NativeImportExportDeployment.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NativeImportExportDeployment
 * @ingroup plugins_importexport_native
 *
 * @brief Class configuring the native import/export process to this
 * application's specifics.
 */

namespace APP\plugins\importexport\native;

use APP\core\Application;

class NativeImportExportDeployment extends \PKP\plugins\importexport\native\PKPNativeImportExportDeployment
{
    //
    // Deployment items for subclasses to override
    //
    /**
     * Get the submission node name
     *
     * @return string
     */
    public function getSubmissionNodeName()
    {
        return 'preprint';
    }

    /**
     * Get the submissions node name
     *
     * @return string
     */
    public function getSubmissionsNodeName()
    {
        return 'preprints';
    }

    /**
     * Get the representation node name
     */
    public function getRepresentationNodeName()
    {
        return 'preprint_galley';
    }

    /**
     * Get the schema filename.
     *
     * @return string
     */
    public function getSchemaFilename()
    {
        return 'native.xsd';
    }

    /**
     * @see PKPNativeImportExportDeployment::getObjectTypes()
     */
    protected function getObjectTypes()
    {
        $objectTypes = parent::getObjectTypes();
        $objectTypes = $objectTypes + [
            Application::ASSOC_TYPE_SERVER => __('context.context'),
            Application::ASSOC_TYPE_SECTION => __('section.section'),
            Application::ASSOC_TYPE_PUBLICATION => __('common.publication'),
        ];

        return $objectTypes;
    }
}
