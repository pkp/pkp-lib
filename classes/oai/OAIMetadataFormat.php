<?php

/**
 * @file classes/oai/OAIMetadataFormat.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class OAIMetadataFormat
 * @ingroup oai
 *
 * @see OAI
 *
 * @brief OAI-PMH metadata format
 */

namespace PKP\oai;

/**
 * OAI metadata format.
 * Used to generated metadata XML according to a specified schema.
 */
class OAIMetadataFormat
{
    /** @var string metadata prefix */
    public $prefix;

    /** @var string XML schema */
    public $schema;

    /** @var string XML namespace */
    public $namespace;

    /**
     * Constructor.
     */
    public function __construct($prefix, $schema, $namespace)
    {
        $this->prefix = $prefix;
        $this->schema = $schema;
        $this->namespace = $namespace;
    }

    public function getLocalizedData($data, $locale)
    {
        foreach ($data as $element) {
            if (isset($data[$locale])) {
                return $data[$locale];
            }
        }
        return '';
    }

    /**
     * Retrieve XML-formatted metadata for the specified record.
     *
     * @param OAIRecord $record
     * @param string $format OAI metadata prefix
     *
     * @return string
     */
    public function toXml($record, $format = null)
    {
        return '';
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\oai\OAIMetadataFormat', '\OAIMetadataFormat');
}
