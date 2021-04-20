<?php

/**
 * @file classes/oai/OAIStruct.inc.php
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
 * @brief Data structures associated with the OAI request handler.
 */

define('OAIRECORD_STATUS_DELETED', 0);
define('OAIRECORD_STATUS_ALIVE', 1);

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
     * @param $record OAIRecord
     * @param $format string OAI metadata prefix
     *
     * @return string
     */
    public function toXml($record, $format = null)
    {
        return '';
    }
}


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
