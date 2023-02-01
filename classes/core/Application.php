<?php

/**
 * @file classes/core/Application.php
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

use APP\facades\Repo;
use PKP\core\PKPApplication;
use PKP\db\DAORegistry;
use PKP\facades\Locale;
use PKP\submission\RepresentationDAOInterface;

class Application extends PKPApplication
{
    public const ASSOC_TYPE_PREPRINT = self::ASSOC_TYPE_SUBMISSION;
    public const ASSOC_TYPE_GALLEY = self::ASSOC_TYPE_REPRESENTATION;
    public const ASSOC_TYPE_SERVER = 0x0000100;
    public const REQUIRES_XSL = false;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        if (!PKP_STRICT_MODE) {
            foreach ([
                'REQUIRES_XSL',
                'ASSOC_TYPE_PREPRINT',
                'ASSOC_TYPE_GALLEY',
                'ASSOC_TYPE_SERVER',
            ] as $constantName) {
                if (!defined($constantName)) {
                    define($constantName, constant('self::' . $constantName));
                }
            }
            if (!class_exists('\Application')) {
                class_alias('\APP\core\Application', '\Application');
            }
        }

        // Add application locales
        Locale::registerPath(BASE_SYS_DIR . '/locale');
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
        return 'https://pkp.sfu.ca/ops/xml/ops-version.xml';
    }

    /**
     * Get the map of DAOName => full.class.Path for this application.
     *
     * @return array
     */
    public function getDAOMap()
    {
        return array_merge(parent::getDAOMap(), [
            'PreprintSearchDAO' => 'APP\search\PreprintSearchDAO',
            'ServerDAO' => 'APP\server\ServerDAO',
            'OAIDAO' => 'APP\oai\ops\OAIDAO',
            'TemporaryTotalsDAO' => 'APP\statistics\TemporaryTotalsDAO',
            'TemporaryItemInvestigationsDAO' => 'APP\statistics\TemporaryItemInvestigationsDAO',
            'TemporaryItemRequestsDAO' => 'APP\statistics\TemporaryItemRequestsDAO',
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
     * Get the representation DAO.
     */
    public static function getRepresentationDAO(): RepresentationDAOInterface
    {
        return Repo::galley()->dao;
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
