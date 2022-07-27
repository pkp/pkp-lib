<?php

/**
 * @file classes/oai/OAISet.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class OAISet
 * @ingroup oai
 *
 * @see OAI
 *
 * @brief OAI-PMH set
 */

namespace PKP\oai;

/**
 * OAI set.
 * Identifies a set of related records.
 */
class OAISet
{
    /** @var string unique set specifier */
    public $spec;

    /** @var string set name */
    public $name;

    /** @var string set description */
    public $description;


    /**
     * Constructor.
     */
    public function __construct($spec, $name, $description)
    {
        $this->spec = $spec;
        $this->name = $name;
        $this->description = $description;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\oai\OAISet', '\OAISet');
}
