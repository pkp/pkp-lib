<?php

/**
 * @file classes/oai/OAIRepository.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class OAIRepository
 * @ingroup oai
 *
 * @see OAI
 *
 * @brief OAI-PMH Repository
 */

namespace PKP\oai;

/**
 * OAI repository information.
 */
class OAIRepository
{
    /** @var string name of the repository */
    public $repositoryName;

    /** @var string administrative contact email */
    public $adminEmail;

    /** @var int earliest *nix timestamp in the repository */
    public $earliestDatestamp;

    /** @var string delimiter in identifier */
    public $delimiter = ':';

    /** @var string example identifier */
    public $sampleIdentifier;

    /** @var string toolkit/software title (e.g. Open Journal Systems) */
    public $toolkitTitle;

    /** @var string toolkit/software version */
    public $toolkitVersion;

    /** @var string toolkit/software URL */
    public $toolkitURL;
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\oai\OAIRepository', '\OAIRepository');
}
