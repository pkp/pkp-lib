<?php

/**
 * @file classes/oai/OAIRecord.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class OAIRecord
 *
 * @ingroup oai
 *
 * @see OAI
 *
 * @brief OAI-PMH record.
 */

namespace PKP\oai;

/**
 * OAI record.
 * Describes metadata for a single record in the repository.
 */
class OAIRecord extends OAIIdentifier
{
    public $data;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->data = [];
    }

    public function setData($name, &$value)
    {
        $this->data[$name] = & $value;
    }

    public function &getData($name)
    {
        if (isset($this->data[$name])) {
            $returner = & $this->data[$name];
        } else {
            $returner = null;
        }

        return $returner;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\oai\OAIRecord', '\OAIRecord');
}
