<?php

/**
 * @file classes/oai/OAIConfig.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class OAIConfig
 * @ingroup oai
 *
 * @see OAI
 *
 * @brief OAI-PMH server configuration
 */

namespace PKP\oai;

use PKP\config\Config;

/**
 * OAI repository configuration.
 */
class OAIConfig
{
    /** @var string URL to the OAI front-end */
    public $baseUrl = '';

    /** @var string identifier of the repository */
    public $repositoryId = 'oai';

    /** @var string record datestamp granularity;
     * Must be either 'YYYY-MM-DD' or 'YYYY-MM-DDThh:mm:ssZ'
     */
    public $granularity = 'YYYY-MM-DDThh:mm:ssZ';

    /** @var int TTL of resumption tokens */
    public $tokenLifetime = 86400;

    /** @var int maximum identifiers returned per request */
    public $maxIdentifiers = 500;

    /** @var int maximum records returned per request */
    public $maxRecords;

    /** @var int maximum sets returned per request (must be 0 if sets not supported) */
    public $maxSets = 50;


    /**
     * Constructor.
     */
    public function __construct($baseUrl, $repositoryId)
    {
        $this->baseUrl = $baseUrl;
        $this->repositoryId = $repositoryId;

        $this->maxRecords = Config::getVar('oai', 'oai_max_records');
        if (!$this->maxRecords) {
            $this->maxRecords = 100;
        }
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\oai\OAIConfig', '\OAIConfig');
}
