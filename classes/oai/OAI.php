<?php

/**
 * @defgroup oai OAI
 * Implements an OAI (Open Archives Initiative) OAI-PMH interface. See
 * http://www.openarchives.org for information on OAI-PMH.
 */

/**
 * @file classes/oai/OAI.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class OAI
 *
 * @ingroup oai
 *
 * @see OAIDAO
 *
 * @brief Class to process and respond to OAI requests.
 */

namespace PKP\oai;

use APP\core\Application;
use PKP\plugins\Hook;

abstract class OAI
{
    public const OAIRECORD_STATUS_DELETED = 0;
    public const OAIRECORD_STATUS_ALIVE = 1;

    /** @var OAIConfig configuration parameters */
    public $config;

    /** @var array list of request parameters */
    public $params;

    /** @var string version of the OAI protocol supported by this class */
    public $protocolVersion = '2.0';


    /**
     * Constructor.
     * Initializes object and parses user input.
     *
     * @param OAIConfig $config repository configuration
     */
    public function __construct($config)
    {
        $this->config = $config;

        // Initialize parameters from GET or POST variables
        $this->params = [];

        if (isset($GLOBALS['HTTP_RAW_POST_DATA']) && !empty($GLOBALS['HTTP_RAW_POST_DATA'])) {
            OAIUtils::parseStr($GLOBALS['HTTP_RAW_POST_DATA'], $this->params);
        } elseif (!empty($_SERVER['QUERY_STRING'])) {
            OAIUtils::parseStr($_SERVER['QUERY_STRING'], $this->params);
        } else {
            $this->params = array_merge($_GET, $_POST);
        }

        // Clean input variables
        OAIUtils::prepInput($this->params);

        // Encode data with gzip, deflate, or none, depending on browser support
        if (!ini_get('zlib.output_compression')) {
            @ob_start('ob_gzhandler');
        }
    }

    /**
     * Execute the requested OAI protocol request
     * and output the response.
     */
    public function execute()
    {
        switch ($this->getParam('verb')) {
            case 'GetRecord':
                $this->GetRecord();
                break;
            case 'Identify':
                $this->Identify();
                break;
            case 'ListIdentifiers':
                $this->ListIdentifiers();
                break;
            case 'ListMetadataFormats':
                $this->ListMetadataFormats();
                break;
            case 'ListRecords':
                $this->ListRecords();
                break;
            case 'ListSets':
                $this->ListSets();
                break;
            default:
                $this->error('badVerb', 'Illegal OAI verb');
                break;
        }
    }


    //
    // Abstract implementation-specific functions
    // (to be overridden in subclass)
    //

    /**
     * Return information about the repository.
     *
     * @return OAIRepository
     */
    abstract public function repositoryInfo();

    /**
     * Check if identifier is in the valid format.
     *
     * @param string $identifier
     *
     * @return bool
     */
    public function validIdentifier($identifier)
    {
        return false;
    }

    /**
     * Check if identifier exists.
     *
     * @param string $identifier
     *
     * @return bool
     */
    public function identifierExists($identifier)
    {
        return false;
    }

    /**
     * Return OAI record for specified identifier.
     *
     * @param string $identifier
     *
     * @return OAIRecord (or false, if identifier is invalid)
     */
    abstract public function record($identifier);

    /**
     * Return set of OAI records.
     *
     * @param string $metadataPrefix specified metadata prefix
     * @param int $from minimum timestamp
     * @param int $until maximum timestamp
     * @param string $set specified set
     * @param int $offset current record offset
     * @param int $limit maximum number of records to return
     * @param int $total output parameter, set to total number of records
     *
     * @return array OAIRecord
     */
    public function records($metadataPrefix, $from, $until, $set, $offset, $limit, &$total)
    {
        return [];
    }

    /**
     * Return set of OAI identifiers.
     *
     * @see getRecords
     *
     * @return array OAIIdentifier
     */
    public function identifiers($metadataPrefix, $from, $until, $set, $offset, $limit, &$total)
    {
        return [];
    }

    /**
     * Return set of OAI sets.
     *
     * @param int $offset current set offset
     * @param int $limit Maximum number of sets to return
     * @param int $total output parameter, set to total number of sets
     */
    public function sets($offset, $limit, &$total)
    {
        return [];
    }

    /**
     * Retrieve a resumption token.
     *
     * @param string $tokenId
     *
     * @return OAIResumptionToken|false
     *
     * @hook OAI::metadataFormats [[$namesOnly, $identifier, &$formats]]
     */
    abstract public function resumptionToken($tokenId);

    /**
     * Save a resumption token.
     *
     * @param int $offset current offset
     * @param array $params request parameters
     *
     * @return OAIResumptionToken the saved token
     *
     * @hook OAI::metadataFormats [[$namesOnly, $identifier, &$formats]]
     */
    abstract public function saveResumptionToken($offset, $params);

    /**
     * Return array of supported metadata formats.
     *
     * @param bool $namesOnly return array of format prefix names only
     * @param string $identifier return formats for specific identifier
     *
     * @return array
     *
     * @hook OAI::metadataFormats [[$namesOnly, $identifier, &$formats]]
     */
    public function metadataFormats($namesOnly = false, $identifier = null)
    {
        $formats = [];
        Hook::call('OAI::metadataFormats', [$namesOnly, $identifier, &$formats]);

        return $formats;
    }

    //
    // Protocol request handlers
    //

    /**
     * Handle OAI GetRecord request.
     * Retrieves an individual record from the repository.
     */
    public function GetRecord()
    {
        // Validate parameters
        if (!$this->checkParams(['identifier', 'metadataPrefix'])) {
            return;
        }

        $identifier = $this->getParam('identifier');
        $metadataPrefix = $this->getParam('metadataPrefix');

        // Check that identifier is in valid format
        if ($this->validIdentifier($identifier) === false) {
            $this->error('badArgument', 'Identifier is not in a valid format');
            return;
        }

        // Get metadata for requested identifier
        if (($record = $this->record($identifier)) === false) {
            $this->error('idDoesNotExist', 'No matching identifier in this repository');
            return;
        }

        // Check that the requested metadata format is supported for this identifier
        if (!in_array($metadataPrefix, $this->metadataFormats(true, $identifier))) {
            $this->error('cannotDisseminateFormat', 'The requested metadataPrefix is not supported by this repository');
            return;
        }

        // Display response
        $response = "\t<GetRecord>\n" .
            "\t\t<record>\n" .
            "\t\t\t<header" . (($record->status == self::OAIRECORD_STATUS_DELETED) ? " status=\"deleted\">\n" : ">\n") .
            "\t\t\t\t<identifier>" . $record->identifier . "</identifier>\n" .
            "\t\t\t\t<datestamp>" . $record->datestamp . "</datestamp>\n";
        // Output set memberships
        foreach ($record->sets as $setSpec) {
            $response .= "\t\t\t\t<setSpec>{$setSpec}</setSpec>\n";
        }
        $response .= "\t\t\t</header>\n";
        if (!empty($record->data)) {
            $response .= "\t\t\t<metadata>\n";
            // Output metadata
            $response .= $this->formatMetadata($metadataPrefix, $record);
            $response .= "\t\t\t</metadata>\n";
        }
        $response .= "\t\t</record>\n" .
            "\t</GetRecord>\n";

        $this->response($response);
    }


    /**
     * Handle OAI Identify request.
     * Retrieves information about a repository.
     */
    public function Identify()
    {
        // Validate parameters
        if (!$this->checkParams()) {
            return;
        }

        $info = $this->repositoryInfo();

        // Format body of response
        $response = "\t<Identify>\n" .
            "\t\t<repositoryName>" . OAIUtils::prepOutput($info->repositoryName) . "</repositoryName>\n" .
            "\t\t<baseURL>" . $this->config->baseUrl . "</baseURL>\n" .
            "\t\t<protocolVersion>" . $this->protocolVersion . "</protocolVersion>\n" .
            "\t\t<adminEmail>" . $info->adminEmail . "</adminEmail>\n" .
            "\t\t<earliestDatestamp>" . OAIUtils::UTCDate($info->earliestDatestamp) . "</earliestDatestamp>\n" .
            "\t\t<deletedRecord>persistent</deletedRecord>\n" .
            "\t\t<granularity>" . $this->config->granularity . "</granularity>\n";
        if (extension_loaded('zlib')) {
            // Show compression options if server supports Zlib
            $response .= "\t\t<compression>gzip</compression>\n" .
                "\t\t<compression>deflate</compression>\n";
        }
        $response .= "\t\t<description>\n" .
            "\t\t\t<oai-identifier\n" .
            "\t\t\t\txmlns=\"http://www.openarchives.org/OAI/2.0/oai-identifier\"\n" .
            "\t\t\t\txmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\"\n" .
            "\t\t\t\txsi:schemaLocation=\"http://www.openarchives.org/OAI/2.0/oai-identifier\n" .
            "\t\t\t\t\thttp://www.openarchives.org/OAI/2.0/oai-identifier.xsd\">\n" .
            "\t\t\t\t<scheme>oai</scheme>\n" .
            "\t\t\t\t<repositoryIdentifier>" . $this->config->repositoryId . "</repositoryIdentifier>\n" .
            "\t\t\t\t<delimiter>" . $info->delimiter . "</delimiter>\n" .
            "\t\t\t\t<sampleIdentifier>" . $info->sampleIdentifier . "</sampleIdentifier>\n" .
            "\t\t\t</oai-identifier>\n" .
            "\t\t</description>\n";
        $response .= "\t\t<description>\n" .
            "\t\t\t<toolkit\n" .
            "\t\t\t\txmlns=\"http://oai.dlib.vt.edu/OAI/metadata/toolkit\"\n" .
            "\t\t\t\txsi:schemaLocation=\"http://oai.dlib.vt.edu/OAI/metadata/toolkit\n" .
            "\t\t\t\t\thttp://oai.dlib.vt.edu/OAI/metadata/toolkit.xsd\">\n" .
            "\t\t\t\t<title>" . $info->toolkitTitle . "</title>\n" .
            "\t\t\t\t<author>\n" .
            "\t\t\t\t\t<name>Public Knowledge Project</name>\n" .
            "\t\t\t\t\t<email>pkp.contact@gmail.com</email>\n" .
            "\t\t\t\t</author>\n" .
            "\t\t\t\t<version>" . $info->toolkitVersion . "</version>\n" .
            "\t\t\t\t<URL>" . $info->toolkitURL . "</URL>\n" .
            "\t\t\t</toolkit>\n" .
            "\t\t</description>\n";
        $response .= "\t</Identify>\n";

        $this->response($response);
    }

    /**
     * Handle OAI ListIdentifiers request.
     * Retrieves headers of records from the repository.
     */
    public function ListIdentifiers()
    {
        $offset = 0;

        // Check for resumption token
        if ($this->paramExists('resumptionToken')) {
            // Validate parameters
            if (!$this->checkParams(['resumptionToken'])) {
                return;
            }

            // Get parameters from resumption token
            if (($token = $this->resumptionToken($this->getParam('resumptionToken'))) === false) {
                $this->error('badResumptionToken', 'The requested resumptionToken is invalid or has expired');
                return;
            }

            $this->setParams($token->params);
            $offset = $token->offset;
        }

        // Validate parameters
        if (!$this->checkParams(['metadataPrefix'], ['from', 'until', 'set'])) {
            return;
        }

        $metadataPrefix = $this->getParam('metadataPrefix');
        $set = $this->getParam('set');

        // Check that the requested metadata format is supported
        if (!in_array($metadataPrefix, $this->metadataFormats(true))) {
            $this->error('cannotDisseminateFormat', 'The requested metadataPrefix is not supported by this repository');
            return;
        }

        // If a set was passed in check if repository supports sets
        if (isset($set) && $this->config->maxSets == 0) {
            $this->error('noSetHierarchy', 'This repository does not support sets');
            return;
        }

        // Get UNIX timestamps for from and until dates, if applicable
        if (!$this->extractDateParams($this->getParams(), $from, $until)) {
            return;
        }

        // Store current offset and total records for resumption token, if needed
        $cursor = $offset;
        $total = 0;

        // Get list of matching identifiers
        $records = $this->identifiers($metadataPrefix, $from, $until, $set, $offset, $this->config->maxIdentifiers, $total);
        if (empty($records)) {
            $this->error('noRecordsMatch', 'No matching records in this repository');
            return;
        }

        // Format body of response
        $response = "\t<ListIdentifiers>\n";

        // Output identifiers
        for ($i = 0, $num = count($records); $i < $num; $i++) {
            $record = $records[$i];
            $response .= "\t\t<header" . (($record->status == self::OAIRECORD_STATUS_DELETED) ? " status=\"deleted\">\n" : ">\n") .
                "\t\t\t<identifier>" . $record->identifier . "</identifier>\n" .
                "\t\t\t<datestamp>" . $record->datestamp . "</datestamp>\n";
            // Output set memberships
            foreach ($record->sets as $setSpec) {
                $response .= "\t\t\t<setSpec>" . OAIUtils::prepOutput($setSpec) . "</setSpec>\n";
            }
            $response .= "\t\t</header>\n";
        }
        $offset += $num;

        if ($offset != 0 && $offset < $total) {
            // Partial result, save resumption token
            $token = $this->saveResumptionToken($offset, $this->getParams());

            $response .= "\t\t<resumptionToken expirationDate=\"" . OAIUtils::UTCDate($token->expire) . "\"\n" .
                "\t\t\tcompleteListSize=\"{$total}\"\n" .
                "\t\t\tcursor=\"{$cursor}\">" . $token->id . "</resumptionToken>\n";
        } elseif (isset($token)) {
            // Current request completes a previous incomplete list, add empty resumption token
            $response .= "\t\t<resumptionToken completeListSize=\"{$total}\" cursor=\"{$cursor}\" />\n";
        }

        $response .= "\t</ListIdentifiers>\n";

        $this->response($response);
    }

    /**
     * Handle OAI ListMetadataFormats request.
     * Retrieves metadata formats supported by the repository.
     */
    public function ListMetadataFormats()
    {
        // Validate parameters
        if (!$this->checkParams([], ['identifier'])) {
            return;
        }

        // Get list of metadata formats for selected identifier, or all formats if no identifier was passed
        if ($this->paramExists('identifier')) {
            if (!$this->identifierExists($this->getParam('identifier'))) {
                $this->error('idDoesNotExist', 'No matching identifier in this repository');
                return;
            } else {
                $formats = $this->metadataFormats(false, $this->getParam('identifier'));
            }
        } else {
            $formats = $this->metadataFormats();
        }

        if (empty($formats) || !is_array($formats)) {
            $this->error('noMetadataFormats', 'No metadata formats are available');
            return;
        }

        // Format body of response
        $response = "\t<ListMetadataFormats>\n";

        // output metadata formats
        foreach ($formats as $format) {
            $response .= "\t\t<metadataFormat>\n" .
                "\t\t\t<metadataPrefix>" . $format->prefix . "</metadataPrefix>\n" .
                "\t\t\t<schema>" . $format->schema . "</schema>\n" .
                "\t\t\t<metadataNamespace>" . $format->namespace . "</metadataNamespace>\n" .
                "\t\t</metadataFormat>\n";
        }

        $response .= "\t</ListMetadataFormats>\n";

        $this->response($response);
    }

    /**
     * Handle OAI ListRecords request.
     * Retrieves records from the repository.
     */
    public function ListRecords()
    {
        $offset = 0;

        // Check for resumption token
        if ($this->paramExists('resumptionToken')) {
            // Validate parameters
            if (!$this->checkParams(['resumptionToken'])) {
                return;
            }

            // get parameters from resumption token
            if (($token = $this->resumptionToken($this->getParam('resumptionToken'))) === false) {
                $this->error('badResumptionToken', 'The requested resumptionToken is invalid or has expired');
                return;
            }

            $this->setParams($token->params);
            $offset = $token->offset;
        }

        // Validate parameters
        if (!$this->checkParams(['metadataPrefix'], ['from', 'until', 'set'])) {
            return;
        }

        $metadataPrefix = $this->getParam('metadataPrefix');
        $set = $this->getParam('set');

        // Check that the requested metadata format is supported
        if (!in_array($metadataPrefix, $this->metadataFormats(true))) {
            $this->error('cannotDisseminateFormat', 'The requested metadataPrefix is not supported by this repository');
            return;
        }

        // If a set was passed check if repository supports sets
        if (isset($set) && $this->config->maxSets == 0) {
            $this->error('noSetHierarchy', 'This repository does not support sets');
            return;
        }

        // Get UNIX timestamps for from and until dates, if applicable
        if (!$this->extractDateParams($this->getParams(), $from, $until)) {
            return;
        }

        // Store current offset and total records for resumption token, if needed
        $cursor = $offset;
        $total = 0;

        // Get list of matching records
        $records = $this->records($metadataPrefix, $from, $until, $set, $offset, $this->config->maxRecords, $total);
        if (empty($records)) {
            $this->error('noRecordsMatch', 'No matching records in this repository');
            return;
        }

        // Format body of response
        $response = "\t<ListRecords>\n";

        // Output records
        for ($i = 0, $num = count($records); $i < $num; $i++) {
            $record = $records[$i];
            $response .= "\t\t<record>\n" .
                "\t\t\t<header" . (($record->status == self::OAIRECORD_STATUS_DELETED) ? " status=\"deleted\">\n" : ">\n") .
                "\t\t\t\t<identifier>" . $record->identifier . "</identifier>\n" .
                "\t\t\t\t<datestamp>" . $record->datestamp . "</datestamp>\n";
            // Output set memberships
            foreach ($record->sets as $setSpec) {
                $response .= "\t\t\t\t<setSpec>" . OAIUtils::prepOutput($setSpec) . "</setSpec>\n";
            }
            $response .= "\t\t\t</header>\n";
            if (!empty($record->data)) {
                $response .= "\t\t\t<metadata>\n";
                // Output metadata
                $response .= $this->formatMetadata($this->getParam('metadataPrefix'), $record);
                $response .= "\t\t\t</metadata>\n";
            }
            $response .= "\t\t</record>\n";
        }
        $offset += $num;

        if ($offset != 0 && $offset < $total) {
            // Partial result, save resumption token
            $token = $this->saveResumptionToken($offset, $this->getParams());

            $response .= "\t\t<resumptionToken expirationDate=\"" . OAIUtils::UTCDate($token->expire) . "\"\n" .
                    "\t\t\tcompleteListSize=\"{$total}\"\n" .
                    "\t\t\tcursor=\"{$cursor}\">" . $token->id . "</resumptionToken>\n";
        } elseif (isset($token)) {
            // Current request completes a previous incomplete list, add empty resumption token
            $response .= "\t\t<resumptionToken completeListSize=\"{$total}\" cursor=\"{$cursor}\" />\n";
        }

        $response .= "\t</ListRecords>\n";

        $this->response($response);
    }

    /**
     * Handle OAI ListSets request.
     * Retrieves sets from a repository.
     */
    public function ListSets()
    {
        $offset = 0;

        // Check for resumption token
        if ($this->paramExists('resumptionToken')) {
            // Validate parameters
            if (!$this->checkParams(['resumptionToken'])) {
                return;
            }

            // Get parameters from resumption token
            if (($token = $this->resumptionToken($this->getParam('resumptionToken'))) === false) {
                $this->error('badResumptionToken', 'The requested resumptionToken is invalid or has expired');
                return;
            }

            $this->setParams($token->params);
            $offset = $token->offset;
        }

        // Validate parameters
        if (!$this->checkParams()) {
            return;
        }

        // Store current offset and total sets for resumption token, if needed
        $cursor = $offset;
        $total = 0;

        // Get list of matching sets
        $sets = $this->sets($offset, $this->config->maxRecords, $total);
        if (empty($sets)) {
            $this->error('noSetHierarchy', 'This repository does not support sets');
            return;
        }

        // Format body of response
        $response = "\t<ListSets>\n";

        // Output sets
        for ($i = 0, $num = count($sets); $i < $num; $i++) {
            $set = $sets[$i];
            $response .= "\t\t<set>\n" .
                    "\t\t\t<setSpec>" . OAIUtils::prepOutput($set->spec) . "</setSpec>\n" .
                    "\t\t\t<setName>" . OAIUtils::prepOutput($set->name) . "</setName>\n";
            // output set description, if applicable
            if (isset($set->description)) {
                $response .= "\t\t\t<setDescription>\n" .
                        "\t\t\t\t<oai_dc:dc\n" .
                        "\t\t\t\t\txmlns:oai_dc=\"http://www.openarchives.org/OAI/2.0/oai_dc/\"\n" .
                        "\t\t\t\t\txmlns:dc=\"http://purl.org/dc/elements/1.1/\"\n" .
                        "\t\t\t\t\txmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\"\n" .
                        "\t\t\t\t\txsi:schemaLocation=\"http://www.openarchives.org/OAI/2.0/oai_dc/\n" .
                        "\t\t\t\t\t\thttp://www.openarchives.org/OAI/2.0/oai_dc.xsd\">\n" .
                        "\t\t\t\t\t<dc:description>" . OAIUtils::prepOutput($set->description) . "</dc:description>\n" .
                        "\t\t\t\t</oai_dc:dc>\n" .
                        "\t\t\t</setDescription>\n";
            }
            $response .= "\t\t</set>\n";
        }
        $offset += $num;

        if ($offset != 0 && $offset < $total) {
            // Partial result, set resumption token
            $token = $this->saveResumptionToken($offset, $this->getParams());

            $response .= "\t\t<resumptionToken expirationDate=\"" . OAIUtils::UTCDate($token->expire) . "\"\n" .
                    "\t\t\tcompleteListSize=\"{$total}\"\n" .
                    "\t\t\tcursor=\"{$cursor}\">" . $token->id . "</resumptionToken>\n";
        } elseif (isset($token)) {
            // current request completes a previous incomplete list, add empty resumption token
            $response .= "\t\t<resumptionToken completeListSize=\"{$total}\" cursor=\"{$cursor}\" />\n";
        }

        $response .= "\t</ListSets>\n";

        $this->response($response);
    }


    //
    // Private helper functions
    //

    /**
     * Display OAI error response.
     */
    public function error($code, $message)
    {
        if (in_array($code, ['badVerb', 'badArgument'])) {
            $printParams = false;
        } else {
            $printParams = true;
        }

        $this->response("\t<error code=\"{$code}\">{$message}</error>", $printParams);
    }

    /**
     * Output OAI response.
     *
     * @param string $response text of response message.
     * @param bool $printParams display request parameters
     */
    public function response($response, $printParams = true)
    {
        $request = Application::get()->getRequest();
        header('Content-Type: text/xml');

        echo	"<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n" .
            '<?xml-stylesheet type="text/xsl" href="' . $request->getBaseUrl() . "/lib/pkp/xml/oai2.xsl\" ?>\n" .
            "<OAI-PMH xmlns=\"http://www.openarchives.org/OAI/2.0/\"\n" .
            "\txmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\"\n" .
            "\txsi:schemaLocation=\"http://www.openarchives.org/OAI/2.0/\n" .
            "\t\thttp://www.openarchives.org/OAI/2.0/OAI-PMH.xsd\">\n" .
            "\t<responseDate>" . OAIUtils::UTCDate() . "</responseDate>\n" .
            "\t<request";

        // print request params, if applicable
        if ($printParams) {
            foreach ($this->params as $k => $v) {
                echo " {$k}=\"" . OAIUtils::prepOutput($v) . '"';
            }
        }

        echo	'>' . OAIUtils::prepOutput($this->config->baseUrl) . "</request>\n" .
            $response .
            "</OAI-PMH>\n";
    }

    /**
     * Returns the value of the specified parameter.
     *
     * @param string $name
     *
     * @return string
     */
    public function getParam($name)
    {
        return $this->params[$name] ?? null;
    }

    /**
     * Returns an associative array of all request parameters.
     *
     * @return array
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * Set the request parameters.
     *
     * @param array $params
     */
    public function setParams($params)
    {
        $this->params = $params;
    }

    /**
     * Returns true if the requested parameter is set, false if it is not set.
     *
     * @param string $name
     *
     * @return bool
     */
    public function paramExists($name)
    {
        return isset($this->params[$name]);
    }

    /**
     * Check request parameters.
     * Outputs error response if an invalid parameter is found.
     *
     * @param array $required required parameters for the current request
     * @param array $optional optional parameters for the current request
     *
     * @return bool
     */
    public function checkParams($required = [], $optional = [])
    {
        // Get allowed parameters for current request
        $requiredParams = array_merge(['verb'], $required);
        $validParams = array_merge($requiredParams, $optional);

        // Check for missing or duplicate required parameters
        foreach ($requiredParams as $k) {
            if (!$this->paramExists($k)) {
                $this->error('badArgument', "Missing {$k} parameter");
                return false;
            } elseif (is_array($this->getParam($k))) {
                $this->error('badArgument', "Multiple values are not allowed for the {$k} parameter");
                return false;
            }
        }

        // Check for duplicate optional parameters
        foreach ($optional as $k) {
            if ($this->paramExists($k) && is_array($this->getParam($k))) {
                $this->error('badArgument', "Multiple values are not allowed for the {$k} parameter");
                return false;
            }
        }

        // Check for illegal parameters
        $request = Application::get()->getRequest();
        foreach ($this->params as $k => $v) {
            if (!in_array($k, $validParams)) {
                $this->error('badArgument', "{$k} is an illegal parameter");
                return false;
            }
        }

        return true;
    }

    /**
     * Returns formatted metadata response in specified format.
     *
     * @param string $format
     *
     * @return string
     */
    public function formatMetadata($format, $record)
    {
        $formats = $this->metadataFormats();
        $metadata = $formats[$format]->toXml($record);
        return $metadata;
    }

    /**
     * Checks if from and until parameters have been passed.
     * If passed, validate and convert to UNIX timestamps.
     *
     * @param array $params request parameters
     * @param int $from from timestamp (output parameter)
     * @param int $until until timestamp (output parameter)
     *
     * @return bool
     */
    public function extractDateParams($params, &$from, &$until)
    {
        if (isset($params['from'])) {
            $from = OAIUtils::UTCtoTimestamp($params['from'], $this->config->granularity);

            if ($from == 'invalid') {
                $this->error('badArgument', 'Illegal from parameter');
                return false;
            } elseif ($from == 'invalid_granularity') {
                $this->error('badArgument', 'Illegal granularity for from parameter');
                return false;
            }
        }

        if (isset($params['until'])) {
            $until = OAIUtils::UTCtoTimestamp($params['until'], $this->config->granularity);

            if ($until == 'invalid') {
                $this->error('badArgument', 'Illegal until parameter');
                return false;
            } elseif ($until == 'invalid_granularity') {
                $this->error('badArgument', 'Illegal granularity for until parameter');
                return false;
            }

            // Check that until value is greater than or equal to from value
            if (isset($from) && $from > $until) {
                $this->error('badArgument', 'until parameter must be greater than or equal to from parameter');
                return false;
            }

            // Check that granularities are equal
            if (isset($from) && strlen($params['from']) != strlen($params['until'])) {
                $this->error('badArgument', 'until and from parameters must be of the same granularity');
                return false;
            }

            if (strlen($params['until']) == 10) {
                // Until date is inclusive
                $until += 86399;
            }
        }

        return true;
    }
}
