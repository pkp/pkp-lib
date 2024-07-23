<?php

/**
 * @file classes/plugins/importexport/PKPImportExportFilter.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPImportExportFilter
 *
 * @ingroup plugins_importexport_native
 *
 * @brief Base helper class for import/export filters
 */

namespace PKP\plugins\importexport;

use APP\plugins\importexport\native\NativeImportExportDeployment;
use Exception;
use PKP\db\DAORegistry;
use PKP\filter\Filter;
use PKP\filter\FilterDAO;
use PKP\filter\PersistableFilter;
use PKP\plugins\importexport\native\filter\NativeExportFilter;
use PKP\plugins\importexport\native\PKPNativeImportExportDeployment;

abstract class PKPImportExportFilter extends PersistableFilter
{
    /** @var PKPNativeImportExportDeployment */
    private $_deployment;

    //
    // Deployment management
    //
    /**
     * Set the import/export deployment
     *
     * @param NativeImportExportDeployment $deployment
     */
    public function setDeployment($deployment)
    {
        $this->_deployment = $deployment;
    }

    /**
     * Get the import/export deployment
     *
     * @return PKPNativeImportExportDeployment
     */
    public function getDeployment()
    {
        return $this->_deployment;
    }

    /**
     * Static method that gets the filter object given its name
     *
     * @param string $filter
     * @param PKPImportExportDeployment $deployment
     * @param array $opts
     *
     * @return Filter
     */
    public static function getFilter($filter, $deployment, $opts = [])
    {
        $filterDao = DAORegistry::getDAO('FilterDAO'); /** @var FilterDAO $filterDao */
        $filters = $filterDao->getObjectsByGroup($filter);

        if (count($filters) != 1) {
            throw new Exception(
                __(
                    'plugins.importexport.native.common.error.filter.configuration.count',
                    [
                        'filterName' => $filter,
                        'filterCount' => count($filters)
                    ]
                )
            );
        }

        $currentFilter = array_shift($filters);
        $currentFilter->setDeployment($deployment);

        if ($currentFilter instanceof NativeExportFilter) {
            $currentFilter->setOpts($opts);
        }

        return $currentFilter;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\plugins\importexport\PKPImportExportFilter', '\PKPImportExportFilter');
}
