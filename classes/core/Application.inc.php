<?php

/**
 * @file classes/core/Application.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Application
 * @ingroup core
 *
 * @see PKPApplication
 *
 * @brief Class describing this application.
 *
 */

namespace APP\core;

use PKP\core\PKPApplication;
use PKP\db\DAORegistry;

define('REQUIRES_XSL', false);

define('ASSOC_TYPE_PREPRINT', PKPApplication::ASSOC_TYPE_SUBMISSION); // DEPRECATED but needed by filter framework
define('ASSOC_TYPE_GALLEY', PKPApplication::ASSOC_TYPE_REPRESENTATION);

define('ASSOC_TYPE_SERVER', 0x0000100);

define('METRIC_TYPE_COUNTER', 'ops::counter');

class Application extends PKPApplication
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        if (!PKP_STRICT_MODE && !class_exists('\Application')) {
            class_alias('\APP\core\Application', '\Application');
        }
    }

    /**
     * Get the "context depth" of this application, i.e. the number of
     * parts of the URL after index.php that represent the context of
     * the current request (e.g. Server [1], or Conference and
     * Scheduled Conference [2]).
     *
     * @return int
     */
    public function getContextDepth()
    {
        return 1;
    }

    /**
     * Get the list of context elements.
     *
     * @return array
     */
    public function getContextList()
    {
        return ['server'];
    }

    /**
     * Get the symbolic name of this application
     *
     * @return string
     */
    public static function getName()
    {
        return 'ops';
    }

    /**
     * Get the locale key for the name of this application.
     *
     * @return string
     */
    public function getNameKey()
    {
        return('common.software');
    }

    /**
     * Get the URL to the XML descriptor for the current version of this
     * application.
     *
     * @return string
     */
    public function getVersionDescriptorUrl()
    {
        return('http://pkp.sfu.ca/ops/xml/ops-version.xml');
    }

    /**
     * Get the map of DAOName => full.class.Path for this application.
     *
     * @return array
     */
    public function getDAOMap()
    {
        return array_merge(parent::getDAOMap(), [
            'PreprintGalleyDAO' => 'APP\preprint\PreprintGalleyDAO',
            'PreprintSearchDAO' => 'APP\search\PreprintSearchDAO',
            'ServerDAO' => 'APP\server\ServerDAO',
            'ServerSettingsDAO' => 'APP\server\ServerSettingsDAO',
            'MetricsDAO' => 'APP\statistics\MetricsDAO',
            'OAIDAO' => 'APP\oai\ops\OAIDAO',
            'SectionDAO' => 'APP\server\SectionDAO',
        ]);
    }

    /**
     * Get the list of plugin categories for this application.
     *
     * @return array
     */
    public function getPluginCategories()
    {
        return [
            // NB: Meta-data plug-ins are first in the list as this
            // will make them load (and install) first.
            // This is necessary as several other plug-in categories
            // depend on meta-data. This is a very rudimentary type of
            // dependency management for plug-ins.
            'metadata',
            'auth',
            'blocks',
            'gateways',
            'generic',
            'importexport',
            'oaiMetadataFormats',
            'paymethod',
            'pubIds',
            'reports',
            'themes'
        ];
    }

    /**
     * Get the top-level context DAO.
     *
     * @return ContextDAO
     */
    public static function getContextDAO()
    {
        return DAORegistry::getDAO('ServerDAO');
    }

    /**
     * Get the section DAO.
     *
     * @return SectionDAO
     */
    public static function getSectionDAO()
    {
        return DAORegistry::getDAO('SectionDAO');
    }

    /**
     * Get the representation DAO.
     *
     * @return RepresentationDAO
     */
    public static function getRepresentationDAO()
    {
        return DAORegistry::getDAO('PreprintGalleyDAO');
    }

    /**
     * Get a SubmissionSearchIndex instance.
     */
    public static function getSubmissionSearchIndex()
    {
        return new \APP\search\PreprintSearchIndex();
    }

    /**
     * Get a SubmissionSearchDAO instance.
     */
    public static function getSubmissionSearchDAO()
    {
        return DAORegistry::getDAO('PreprintSearchDAO');
    }

    /**
     * Get the stages used by the application.
     *
     * @return array
     */
    public static function getApplicationStages()
    {
        // Only one stage in OPS
        return [
            WORKFLOW_STAGE_ID_PRODUCTION
        ];
    }

    /**
     * Returns the context type for this application.
     *
     * @return int ASSOC_TYPE_...
     */
    public static function getContextAssocType()
    {
        return ASSOC_TYPE_SERVER;
    }

    /**
     * Get the file directory array map used by the application.
     */
    public static function getFileDirectories()
    {
        return ['context' => '/contexts/', 'submission' => '/submissions/'];
    }
}
