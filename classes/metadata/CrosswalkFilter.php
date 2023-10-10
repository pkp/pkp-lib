<?php

/**
 * @file classes/metadata/CrosswalkFilter.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CrosswalkFilter
 *
 * @ingroup metadata
 *
 * @see MetadataDescription
 *
 * @brief Class that provides methods to convert one type of
 *  meta-data description into another. This is an abstract
 *  class that must be sub-classed by specific cross-walk
 *  implementations.
 */

namespace PKP\metadata;

use PKP\filter\Filter;

class CrosswalkFilter extends Filter
{
    /**
     * Constructor
     *
     * @param string $fromSchema fully qualified class name of supported input meta-data schema
     * @param string $toSchema fully qualified class name of supported output meta-data schema
     */
    public function __construct($fromSchema, $toSchema)
    {
        parent::__construct('metadata::' . $fromSchema . '(*)', 'metadata::' . $toSchema . '(*)');
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\metadata\CrosswalkFilter', '\CrosswalkFilter');
}
