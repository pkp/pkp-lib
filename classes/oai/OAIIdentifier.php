<?php

/**
 * @file classes/oai/OAIIdentifier.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class OAIIdentifier
 *
 * @ingroup oai
 *
 * @see OAI
 *
 * @brief OAI-PMH Identifier
 */

namespace PKP\oai;

/**
 * OAI identifier.
 */
class OAIIdentifier
{
    /** @var string unique OAI record identifier */
    public $identifier;

    /** @var int last-modified *nix timestamp */
    public $datestamp;

    /** @var array sets this record belongs to */
    public $sets;

    /** @var string if this record is deleted */
    public $status;
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\oai\OAIIdentifier', '\OAIIdentifier');
}
