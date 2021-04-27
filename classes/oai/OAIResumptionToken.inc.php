<?php

/**
 * @file classes/oai/OAIResumptionToken.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class OAIResumptionToken
 * @ingroup oai
 *
 * @see OAI
 *
 * @brief OAI-PMH Resumption Token
 */

namespace PKP\oai;

/**
 * OAI resumption token.
 * Used to resume a record retrieval at the last-retrieved offset.
 */
class OAIResumptionToken
{
    /** @var string unique token ID */
    public $id;

    /** @var int record offset */
    public $offset;

    /** @var array request parameters */
    public $params;

    /** @var int expiration timestamp */
    public $expire;


    /**
     * Constructor.
     */
    public function __construct($id, $offset, $params, $expire)
    {
        $this->id = $id;
        $this->offset = $offset;
        $this->params = $params;
        $this->expire = $expire;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\oai\OAIResumptionToken', '\OAIResumptionToken');
}
