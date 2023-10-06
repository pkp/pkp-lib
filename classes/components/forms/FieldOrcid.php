<?php

/**
 * @file classes/components/form/FieldOrcid.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FieldOrcid
 *
 * @ingroup classes_controllers_form
 *
 * @brief A component managing and displaying ORCIDs
 */

namespace PKP\components\forms;

class FieldOrcid extends Field
{
    /** @copydoc Field::$component */
    public $component = 'field-orcid';

    /** @var string ORCID URL */
    public string $orcid = '';

    /** @var int Author ID associated with the ORCID */
    public int $authorId = 0;

    /** @var bool Whether the provided ORCID ID has been verified/authenticated by the author */
    public bool $isVerified = false;

    /** @copydoc Field::$isInert */
    public bool $isInert = true;

    /**
     * @copydoc Field::getConfig()
     */
    public function getConfig()
    {
        $config = parent::getConfig();
        $config['orcid'] = $this->orcid;
        $config['authorId'] = $this->authorId;
        $config['isVerified'] = $this->isVerified;

        return $config;
    }
}
