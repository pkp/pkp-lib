<?php

/**
 * @file lib/pkp/classes/cliTool/ConvertLogFile.php
 *
 * Copyright (c) 2022 Simon Fraser University
 * Copyright (c) 2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ConvertLogFile
 * @ingroup tools
 *
 * @brief Tool to convert usage stats log file (used in releases < 3.4) into the new format.
 *
 * Special cases from the release 2.x:
 * HTML and remote galley:
 * article/view/articleId/galleyId.
 *
 * Supp File:
 * article/downloadSuppFile/articleId/galleyId
 */

namespace PKP\cliTool;

use APP\core\Application;
use APP\facades\Repo;
use APP\statistics\StatisticsHelper;
use DateTime;
use Exception;
use PKP\core\Core;
use PKP\core\Registry;
use PKP\db\DAORegistry;
use PKP\file\FileManager;
use PKP\submission\Genre;

abstract class ConvertLogFile extends \PKP\cliTool\CommandLineTool
{
    /** List of contexts by their paths */
    public array $contextsByPath;

    /**
     * Constructor.
     *
     * @param array $argv command-line arguments (see usage)
     */
    public function __construct($argv = [])
    {
        parent::__construct($argv);

        $contextDao = Application::getContextDAO(); /* @var $contextDao ContextDAO */
        $contextFactory = $contextDao->getAll(); /* @var $contextFactory DAOResultFactory */
        $this->contextsByPath = [];
        while ($context = $contextFactory->next()) { /* @var $context Context */
            $this->contextsByPath[$context->getPath()] = $context;
        }
    }

    /**
     * Get the folder the log file is in.
     */
    abstract public function getLogFileDir(): string;

    /**
     * Get regular expression to parse log file entries
     */
    abstract public function getParseRegex(): string;

    /**
     * Get the datetime format used in the log file
     */
    abstract public function getPhpDateTimeFormat(): string;

    /**
     * Weather the URL parameters are used instead of CGI PATH_INFO.
     * This will determine how URLs are parsed.
     */
    abstract public function isPathInfoDisabled(): bool;

    /**
     * Weather this is an apache access log file
     */
    abstract public function isApacheAccessLogFile(): bool;

    /**
     * Convert log file into the new format.
     *
     * The old log file will be renamed: '_old' is added at the end of the file name.
     */
    public function convert(string $fileName): void
    {
        $filePath = $this->getLogFileDir() . '/' . $fileName;

        $pathParts = pathinfo($filePath);
        $extension = $pathParts['extension'];

        $newFilePath = $this->getLogFileDir() . '/' . $pathParts['filename'] . '_new.log';
        if ($extension == 'gz') {
            $fileMgr = new FileManager();
            try {
                $filePath = $fileMgr->gzDecompressFile($filePath);
            } catch (Exception $e) {
                fwrite(STDERR, $e->getMessage() . PHP_EOL);
                exit(1);
            }
        }

        $fhandle = fopen($filePath, 'r');
        if (!$fhandle) {
            fwrite(STDERR, "Error: Can not open file {$filePath}." . PHP_EOL);
            exit(2);
        }

        $fnewHandle = fopen($newFilePath, 'a+b');
        if (!$fnewHandle) {
            fwrite(STDERR, "Error: Can not open file {$newFilePath}." . PHP_EOL);
            exit(3);
        }

        // Read the salt for IP hashing here and not for each line
        $saltFileName = StatisticsHelper::getSaltFileName();
        $salt = trim(file_get_contents($saltFileName));

        $lineNumber = 0;
        $isSuccessful = false;
        while (!feof($fhandle)) {
            $newEntry = [];
            $lineNumber++;
            $line = trim(fgets($fhandle));
            if (empty($line) || substr($line, 0, 1) === '#') {
                continue;
            } // Spacing or comment lines.

            $entryData = $this->getDataFromLogEntry($line);

            if (!$this->isLogEntryValid($entryData)) {
                fwrite(STDERR, "Invalid log entry at line {$lineNumber}." . PHP_EOL);
                continue;
            }

            // Avoid internal apache requests.
            if ($entryData['url'] == '*') {
                continue;
            }

            // Avoid non sucessful requests.
            $sucessfulReturnCodes = [200, 304];
            if (!in_array($entryData['returnCode'], $sucessfulReturnCodes)) {
                continue;
            }

            $newEntry['time'] = $entryData['date'];

            $ip = $entryData['ip'];
            $ipNotHashed = filter_var($ip, FILTER_VALIDATE_IP);
            if ($ipNotHashed) {
                // valid IP address i.e. the IP is not hashed
                $hashedIp = StatisticsHelper::hashIp($ip, $salt);
                $newEntry['ip'] = $hashedIp;
            } else {
                // check if it is a string(64) i.e. sha256 ?
                $newEntry['ip'] = $ip;
            }

            $newEntry['userAgent'] = $entryData['userAgent'];
            $newEntry['canonicalUrl'] = $entryData['url'];

            [
                'workingAssocType' => $assocType,
                'contextPaths' => $contextPaths,
                'page' => $page,
                'operation' => $op,
                'args' => $args
            ] = $this->getUrlMatches($entryData['url'], $lineNumber);

            if ($assocType && $contextPaths && $page && $op) {
                $foundContextPath = current($contextPaths);
                if (!array_key_exists($foundContextPath, $this->contextsByPath)) {
                    fwrite(STDERR, "Context with the path {$foundContextPath} does not exist." . PHP_EOL);
                    continue;
                }
                $context = $this->contextsByPath[$foundContextPath];
                $newEntry['contextId'] = $context->getId();

                $this->setAssoc($assocType, $op, $args, $newEntry);
                if (!array_key_exists('assocType', $newEntry)) {
                    if (!$this->isApacheAccessLogFile()) {
                        fwrite(STDERR, "The URL {$entryData['url']} in the line number {$lineNumber} was not considered." . PHP_EOL);
                    }
                    continue;
                }
            } else {
                continue;
            }

            // Geo data
            $country = $region = $city = null;
            if ($ipNotHashed) {
                $statisticsHelper = new StatisticsHelper();
                $site = Application::get()->getRequest()->getSite();
                [$country, $region, $city] = $statisticsHelper->getGeoData($site, $context, $ip, $hashedIp, false);
            }
            $newEntry['country'] = $country;
            $newEntry['region'] = $region;
            $newEntry['city'] = $city;

            // institutions IDs
            $institutionIds = [];
            if ($ipNotHashed && $context->isInstitutionStatsEnabled($site)) {
                $institutionIds = $statisticsHelper->getInstitutionIds($context->getId(), $ip, $hashedIp, false);
            }
            $newEntry['institutionIds'] = $institutionIds;

            $newEntry['version'] = Registry::get('appVersion');

            // write to a new file
            $newLogEntry = json_encode($newEntry) . PHP_EOL;
            fwrite($fnewHandle, $newLogEntry);
            $isSuccessful = true;
        }
        fclose($fhandle);
        fclose($fnewHandle);


        if ($isSuccessful) {
            $renameToOldFilePath = $this->getLogFileDir() . '/' . $pathParts['filename'] . '_old.log';
            if (!rename($filePath, $renameToOldFilePath)) {
                fwrite(STDERR, "Error: Cound not rename the file {$filePath} to {$renameToOldFilePath}." . PHP_EOL);
                exit(4);
            } else {
                if (!$this->isApacheAccessLogFile()) {
                    // This is not important information for the apache log file conversion --
                    // the file is in a temporary folder that will be removed.
                    echo "The original file is renamed to {$renameToOldFilePath}.\n";
                }
            }
            if (!rename($newFilePath, $filePath)) {
                fwrite(STDERR, "Error: Cound not rename the new file {$newFilePath} to {$filePath}." . PHP_EOL);
                exit(5);
            } else {
                echo "File {$filePath} is converted.\n";
            }
            if ($extension == 'gz') {
                try {
                    $renameToOldFilePath = $fileMgr->gzCompressFile($renameToOldFilePath);
                    $filePath = $fileMgr->gzCompressFile($filePath);
                } catch (Exception $e) {
                    fwrite(STDERR, $e->getMessage() . PHP_EOL);
                    exit(6);
                }
            }
        } else {
            fwrite(STDERR, "Error: File {$filePath} could not be successfully converted." . PHP_EOL);
            exit(7);
        }
    }

    /**
     * Get data from the passed log entry.
     */
    protected function getDataFromLogEntry(string $entry): array
    {
        $entryData = [];
        if (preg_match($this->getParseRegex(), $entry, $m)) {
            $associative = count(array_filter(array_keys($m), 'is_string')) > 0;
            $entryData['ip'] = $associative ? $m['ip'] : $m[1];
            $time = $associative ? $m['date'] : $m[2];
            $dateTime = DateTime::createFromFormat($this->getPhpDateTimeFormat(), $time);
            $entryData['date'] = $dateTime->format('Y-m-d H:i:s');
            // Apache log file URL can be relative, is this OK to be so in the new format or what to do in that case?
            $entryData['url'] = urldecode($associative ? $m['url'] : $m[3]);
            $entryData['returnCode'] = $associative ? $m['returnCode'] : $m[4];
            $entryData['userAgent'] = $associative ? $m['userAgent'] : $m[5];
        }
        return $entryData;
    }

    /**
     * Validate a log entry.
     * This maybe does not have much sense, but because it was used till now, we will leave it.
     */
    protected function isLogEntryValid(array $entry): bool
    {
        if (empty($entry)) {
            return false;
        }
        $date = $entry['date'];
        if (!is_numeric($date) && $date <= 0) {
            return false;
        }
        return true;
    }

    /**
     * Get assoc type, page, operation and args from the passed url,
     * if it matches any that's defined in getExpectedPageAndOp().
     */
    protected function getUrlMatches(string $url, int $lineNumber): array
    {
        $noMatchesReturner = [
            'workingAssocType' => null,
            'contextPaths' => null,
            'page' => null,
            'operation' => null,
            'args' => null
        ];

        $expectedPageAndOp = $this->getExpectedPageAndOp();

        // Apache and usage stats plugin log files come with complete or partial base url,
        // remove it so we can retrieve path, page, operation and args.
        $url = Core::removeBaseUrl($url);
        if ($url) {
            $contextPaths = $this->getContextPaths($url, !$this->isPathInfoDisabled());
            $page = Core::getPage($url, !$this->isPathInfoDisabled());
            $operation = Core::getOp($url, !$this->isPathInfoDisabled());
            $args = Core::getArgs($url, !$this->isPathInfoDisabled());
        } else {
            // Could not remove the base URL, can't go on.
            fwrite(STDERR, "The line number {$lineNumber} contains an url that the system can't remove the base URL from." . PHP_EOL);
            return $noMatchesReturner;
        }

        if ($this->isApacheAccessLogFile()) {
            // in apache access log files there could be all kind of URLs, e.g.
            // /favicon.ico, /plugins/..., /lib/pkp/...
            // In that case stop here to look further.
            if (empty(array_intersect($contextPaths, array_keys($this->contextsByPath)))) {
                return $noMatchesReturner;
            }
        }

        // See bug #8698#.
        if (is_array($contextPaths) && !$page && $operation == 'index') {
            $page = 'index';
        }

        if (empty($contextPaths) || !$page || !$operation) {
            fwrite(STDERR, 'Either context paths, page or operation could not be parsed from the URL correctly.' . PHP_EOL);
            return $noMatchesReturner;
        }

        $pageAndOperation = $page . '/' . $operation;

        $pageAndOpMatch = false;
        foreach ($expectedPageAndOp as $workingAssocType => $workingPageAndOps) {
            foreach ($workingPageAndOps as $workingPageAndOp) {
                if ($pageAndOperation == $workingPageAndOp) {
                    // Expected url, don't look any futher.
                    $pageAndOpMatch = true;
                    break 2;
                }
            }
        }
        if ($pageAndOpMatch) {
            return [
                'workingAssocType' => $workingAssocType,
                'contextPaths' => $contextPaths,
                'page' => $page,
                'operation' => $operation,
                'args' => $args
            ];
        } else {
            if (!$this->isApacheAccessLogFile()) {
                fwrite(STDERR, "No matching page and operation found on line number {$lineNumber}." . PHP_EOL);
            }
            return $noMatchesReturner;
        }
    }

    /**
    * Get the expected page and operation.
    * They are grouped by the object type constant that
    * they give access to.
    */
    protected function getExpectedPageAndOp(): array
    {
        $pageAndOp = [
            Application::getContextAssocType() => [
                'index/index'
            ]
        ];
        $application = Application::get();
        $applicationName = $application->getName();
        switch ($applicationName) {
            case 'ojs2':
                $pageAndOp = $pageAndOp + [
                    Application::ASSOC_TYPE_SUBMISSION_FILE_COUNTER_OTHER => [
                        'article/downloadSuppFile'],
                    Application::ASSOC_TYPE_SUBMISSION_FILE => [
                        'article/download'],
                    Application::ASSOC_TYPE_SUBMISSION => [
                        'article/view'],
                    Application::ASSOC_TYPE_ISSUE => [
                        'issue/view'],
                    Application::ASSOC_TYPE_ISSUE_GALLEY => [
                        'issue/download']
                ];
                $pageAndOp[Application::getContextAssocType()][] = 'index';
                break;
            case 'omp':
                // Before 3.4 OMP did not have chapter assoc type i.e. chapter landing page
                // so no need to consider it here
                $pageAndOp = $pageAndOp + [
                    Application::ASSOC_TYPE_SUBMISSION_FILE => [
                        'catalog/download'],
                    Application::ASSOC_TYPE_MONOGRAPH => [
                        'catalog/book'],
                    Application::ASSOC_TYPE_SERIES => [
                        'catalog/series']
                ];
                $pageAndOp[Application::getContextAssocType()][] = 'catalog/index';
                break;
            case 'ops':
                $pageAndOp = $pageAndOp + [
                    Application::ASSOC_TYPE_SUBMISSION_FILE => [
                        'preprint/download'],
                    Application::ASSOC_TYPE_SUBMISSION => [
                        'preprint/view']
                ];
                $pageAndOp[Application::getContextAssocType()][] = 'index';
                break;
            default:
                throw new Exception('Unrecognized application name.');
        }
        return $pageAndOp;
    }

    /**
     * Get context paths present into the passed
     * url information.
     */
    protected static function getContextPaths(string $urlInfo, bool $isPathInfo): array
    {
        $contextPaths = [];
        $application = Application::get();
        $contextList = $application->getContextList();
        $contextDepth = $application->getContextDepth();
        // Handle context depth 0
        if (!$contextDepth) {
            return $contextPaths;
        }

        if ($isPathInfo) {
            // Split the path info into its constituents. Save all non-context
            // path info in $contextPaths[$contextDepth]
            // by limiting the explode statement.
            $contextPaths = explode('/', trim((string) $urlInfo, '/'), $contextDepth + 1);
            // Remove the part of the path info that is not relevant for context (if present)
            unset($contextPaths[$contextDepth]);
        } else {
            // Retrieve context from url query string
            foreach ($contextList as $key => $contextName) {
                parse_str((string) parse_url($urlInfo, PHP_URL_QUERY), $userVarsFromUrl);
                $contextPaths[$key] = $userVarsFromUrl[$contextName] ?? null;
            }
        }

        // Canonicalize and clean context paths
        for ($key = 0; $key < $contextDepth; $key++) {
            $contextPaths[$key] = (
                isset($contextPaths[$key]) && !empty($contextPaths[$key]) ?
                $contextPaths[$key] : 'index'
            );
            $contextPaths[$key] = Core::cleanFileVar($contextPaths[$key]);
        }
        return $contextPaths;
    }

    /**
     * Get the page present into
     * the passed url information. It expects that urls
     * were built using the system.
     */
    protected static function getPage(string $urlInfo, bool $isPathInfo): string
    {
        $page = self::getUrlComponents($urlInfo, $isPathInfo, 0, 'page');
        return Core::cleanFileVar(is_null($page) ? '' : $page);
    }

    /**
     * Get the operation present into
     * the passed url information. It expects that urls
     * were built using the system.
     */
    protected static function getOp(string $urlInfo, bool $isPathInfo): string
    {
        $operation = self::getUrlComponents($urlInfo, $isPathInfo, 1, 'op');
        return Core::cleanFileVar(empty($operation) ? 'index' : $operation);
    }

    /**
     * Get the arguments present into
     * the passed url information (not GET/POST arguments,
     * only arguments appended to the URL separated by "/").
     * It expects that urls were built using the system.
     */
    protected static function getArgs(string $urlInfo, bool $isPathInfo): array
    {
        return self::getUrlComponents($urlInfo, $isPathInfo, 2, 'path');
    }

    /**
     * Get url components (page, operation and args)
     * based on the passed offset.
     */
    protected static function getUrlComponents(string $urlInfo, bool $isPathInfo, int $offset, string $varName = ''): mixed
    {
        $component = null;

        $isArrayComponent = false;
        if ($varName == 'path') {
            $isArrayComponent = true;
        }
        if ($isPathInfo) {
            $application = Application::get();
            $contextDepth = $application->getContextDepth();

            $vars = explode('/', trim($urlInfo, '/'));
            if (count($vars) > $contextDepth + $offset) {
                if ($isArrayComponent) {
                    $component = array_slice($vars, $contextDepth + $offset);
                } else {
                    $component = $vars[$contextDepth + $offset];
                }
            }
        } else {
            parse_str((string) parse_url($urlInfo, PHP_URL_QUERY), $userVarsFromUrl);
            $component = $userVarsFromUrl[$varName] ?? null;
        }

        if ($isArrayComponent) {
            if (empty($component)) {
                $component = [];
            } elseif (!is_array($component)) {
                $component = [$component];
            }
        }

        return $component;
    }

    /**
     * Set assoc type and IDs from the passed page, operation and arguments.
     */
    protected function setAssoc(int $assocType, string $op, array $args, array &$newEntry): void
    {
        $application = Application::get();
        $applicationName = $application->getName();
        switch ($applicationName) {
            case 'ojs2':
                $this->setOJSAssoc($assocType, $args, $newEntry);
                break;
            case 'omp':
                $this->setOMPAssoc($assocType, $args, $newEntry);
                break;
            case 'ops':
                $this->setOPSAssoc($assocType, $args, $newEntry);
                break;
            default:
                throw new Exception('Unrecognized application name!');
        }
    }

    /**
     * Set assoc type and IDs from the passed page, operation and
     * arguments specific to OJS.
     */
    protected function setOJSAssoc(int $assocType, array $args, array &$newEntry): void
    {
        switch ($assocType) {
            case Application::getContextAssocType():
                // $newEntry['contextId'] has already been set
                $newEntry['assocType'] = $assocType;
                $newEntry['submissionId'] = null;
                $newEntry['representationId'] = null;
                $newEntry['submissionFileId'] = null;
                $newEntry['fileType'] = null;
                $newEntry['issueId'] = null;
                $newEntry['issueGalleyId'] = null;
                break;

            case Application::ASSOC_TYPE_SUBMISSION:
                if (!isset($args[0])) {
                    fwrite(STDERR, 'Missing submission ID URL parameter.' . PHP_EOL);
                    break;
                }
                $submissionId = (int) $args[0];
                if (!Repo::submission()->exists($submissionId, $newEntry['contextId'])) {
                    fwrite(STDERR, "Submission with the ID {$submissionId} does not exist in the journal with the ID {$newEntry['contextId']}." . PHP_EOL);
                    break;
                }
                // If it is an older submission version, the arguments must be:
                // $submissionId/version/$publicationId.
                $representationId = null;
                if (in_array('version', $args)) {
                    if ($args[1] !== 'version' || !isset($args[2])) {
                        fwrite(STDERR, 'The following arguments are expected and not found: <submissionId>/version/<publicationId>.' . PHP_EOL);
                        break;
                    }
                    $publicationId = (int) $args[2];
                    if (!Repo::publication()->exists($publicationId, $submissionId)) {
                        fwrite(STDERR, "Publication (submission version) with the ID {$publicationId} does not exist in the submission with the ID {$submissionId}." . PHP_EOL);
                        break;
                    }
                } elseif (count($args) == 2) {
                    // Consider usage stats log files from OJS releases 2.x:
                    // The URL article/view/{$articleId}/{$galleyId} was used for assoc type galley (HTML and remote galleys).
                    $representationId = (int) $args[1];
                    $galley = Repo::galley()->get($representationId);
                    $submissionFileId = $galley->getData('submissionFileId');
                    if (!$submissionFileId) {
                        fwrite(STDERR, 'This is a remote galley from release 2.x.' . PHP_EOL);
                        break;
                    }
                    $submissionFile = Repo::submissionFile()->get($submissionFileId, $submissionId);
                    if (!$submissionFile) {
                        fwrite(STDERR, "Submission file with the ID {$submissionFileId} does not exist in the submission with the ID {$submissionId}." . PHP_EOL);
                        break;
                    }
                    // This should be then the HTML full text file
                    $newEntry['assocType'] = Application::ASSOC_TYPE_SUBMISSION_FILE;
                    $newEntry['submissionId'] = $submissionId;
                    $newEntry['representationId'] = $representationId;
                    $newEntry['submissionFileId'] = $submissionFileId;
                    $newEntry['fileType'] = StatisticsHelper::getDocumentType($submissionFile->getData('mimetype'));
                    $newEntry['issueId'] = null;
                    $newEntry['issueGalleyId'] = null;
                    break;
                }
                $newEntry['submissionId'] = $submissionId;
                $newEntry['assocType'] = $assocType;
                $newEntry['representationId'] = null;
                $newEntry['submissionFileId'] = null;
                $newEntry['fileType'] = null;
                $newEntry['issueId'] = null;
                $newEntry['issueGalleyId'] = null;
                break;

            case Application::ASSOC_TYPE_SUBMISSION_FILE:
                if (!isset($args[0])) {
                    fwrite(STDERR, 'Missing submission ID URL parameter.' . PHP_EOL);
                    break;
                }
                if (!isset($args[1])) {
                    fwrite(STDERR, 'Missing galley ID URL parameter.' . PHP_EOL);
                    break;
                }

                $submissionId = (int) $args[0];
                $submissionExists = Repo::submission()->exists($submissionId, $newEntry['contextId']);
                if (!$submissionExists) {
                    fwrite(STDERR, "Submission with the ID {$submissionId} does not exist in the journal with the ID {$newEntry['contextId']}." . PHP_EOL);
                    break;
                }

                // If it is an older submission version, the arguments must be:
                // $submissionId/version/$publicationId/$representationId/$submissionFileId.
                // Consider also release 2.x where log files can contain URL
                // download/$submissionId/$representationId i.e. without $submissionFileId argument.
                $publicationId = $submissionFileId = null; // do not necessarily exist
                if (in_array('version', $args)) {
                    // This is a newer log file and it should contain submissionId in this case
                    if ($args[1] !== 'version' || !isset($args[2]) || !isset($args[3]) || !isset($args[4])) {
                        fwrite(STDERR, 'The following arguments are expected and not found: <submissionId>/version/<publicationId>/<galleyId>/<fileId>.' . PHP_EOL);
                        break;
                    }
                    $publicationId = (int) $args[2];
                    $representationId = (int) $args[3];
                    $submissionFileId = (int) $args[4];
                    if (!Repo::publication()->exists($publicationId, $submissionId)) {
                        fwrite(STDERR, "Publication (submission version) with the ID {$publicationId} does not exist in the submission with the ID {$submissionId}." . PHP_EOL);
                        break;
                    }
                } else {
                    $representationId = (int) $args[1];
                    if (isset($args[2])) {
                        $submissionFileId = (int) $args[2];
                    }
                }

                $galley = Repo::galley()->get($representationId, $publicationId);
                if (!$galley) {
                    fwrite(STDERR, "Galley with the ID {$representationId} does not exist." . PHP_EOL);
                    break;
                }
                if (!$submissionFileId) { // Log files from releases 2.x
                    $submissionFileId = $galley->getData('submissionFileId');
                }
                $submissionFile = Repo::submissionFile()->get($submissionFileId, $submissionId);
                if (!$submissionFile) {
                    fwrite(STDERR, "Submission file with the ID {$submissionFileId} does not exist in the submission with the ID {$submissionId}." . PHP_EOL);
                    break;
                }
                if ($galley->getData('submissionFileId') != $submissionFileId) {
                    // This check is relevant if representation and submission file ID are provided as arguments
                    fwrite(STDERR, "Submission file with the ID {$submissionFileId} does not belong to the galley with the ID {$representationId}." . PHP_EOL);
                    break;
                }

                // is this a full text or supp file
                $genreDao = DAORegistry::getDAO('GenreDAO');
                $genre = $genreDao->getById($submissionFile->getData('genreId'));
                if ($genre->getCategory() != Genre::GENRE_CATEGORY_DOCUMENT || $genre->getSupplementary() || $genre->getDependent()) {
                    $newEntry['assocType'] = Application::ASSOC_TYPE_SUBMISSION_FILE_COUNTER_OTHER;
                } else {
                    $newEntry['assocType'] = $assocType;
                }
                $newEntry['submissionId'] = $submissionId;
                $newEntry['representationId'] = $representationId;
                $newEntry['submissionFileId'] = $submissionFileId;
                $newEntry['fileType'] = StatisticsHelper::getDocumentType($submissionFile->getData('mimetype'));
                $newEntry['issueId'] = null;
                $newEntry['issueGalleyId'] = null;
                break;

            case Application::ASSOC_TYPE_SUBMISSION_FILE_COUNTER_OTHER:
                // This is the URL article/downloadSuppFile/articleId/suppFileId from a 2.x usage stats log file
                if (!isset($args[0])) {
                    fwrite(STDERR, 'Missing submission ID URL parameter.' . PHP_EOL);
                    break;
                }
                if (!isset($args[1])) {
                    fwrite(STDERR, 'Missing supp file ID URL parameter.' . PHP_EOL);
                    break;
                }
                $submissionId = (int) $args[0];
                $submission = Repo::submission()->get($submissionId, $newEntry['contextId']);
                if (!$submission) {
                    fwrite(STDERR, "Submission with the ID {$submissionId} does not exist in the journal with the ID {$newEntry['contextId']}." . PHP_EOL);
                    break;
                }
                $publications = $submission->getData('publications');
                foreach ($publications as $publication) {
                    foreach ($publication->getData('galleys') as $publicationGalley) {
                        $submissionFileId = $publicationGalley->getData('submissionFileId');
                        if ($submissionFileId) {
                            $submissionFile = Repo::submissionFile()->get($submissionFileId, $submissionId);
                            if ($submissionFile && $submissionFile->getData('old-supp-id') == $args[1]) {
                                // File found
                                $newEntry['assocType'] = $assocType;
                                $newEntry['submissionId'] = $submissionId;
                                $newEntry['representationId'] = $publicationGalley->getId();
                                $newEntry['submissionFileId'] = $submissionFileId;
                                $newEntry['fileType'] = StatisticsHelper::getDocumentType($submissionFile->getData('mimetype'));
                                $newEntry['issueId'] = null;
                                $newEntry['issueGalleyId'] = null;
                                break 3;
                            }
                        }
                    }
                }
                fwrite(STDERR, 'Supp file could not be found.' . PHP_EOL);
                break;

            case Application::ASSOC_TYPE_ISSUE:
                if (!isset($args[0])) {
                    fwrite(STDERR, 'Missing issue ID URL parameter.' . PHP_EOL);
                    break;
                }
                $issueId = (int) $args[0];
                if (!Repo::issue()->exists($issueId, $newEntry['contextId'])) {
                    fwrite(STDERR, "Issue with the ID {$issueId} does not exist in the journal with the ID {$newEntry['contextId']}." . PHP_EOL);
                    break;
                }
                $newEntry['submissionId'] = null;
                $newEntry['representationId'] = null;
                $newEntry['submissionFileId'] = null;
                $newEntry['fileType'] = null;
                $newEntry['issueGalleyId'] = null;
                $newEntry['issueId'] = $issueId;
                $newEntry['assocType'] = $assocType;
                break;

            case Application::ASSOC_TYPE_ISSUE_GALLEY:
                if (!isset($args[0])) {
                    fwrite(STDERR, 'Missing issue ID URL parameter.' . PHP_EOL);
                    break;
                }
                if (!isset($args[1])) {
                    fwrite(STDERR, 'Missing issue galley ID URL parameter.' . PHP_EOL);
                    break;
                }
                $issueGalleyDao = DAORegistry::getDAO('IssueGalleyDAO');
                $issueId = (int) $args[0];
                if (!Repo::issue()->exists($issueId, $newEntry['contextId'])) {
                    fwrite(STDERR, "Issue with the ID {$issueId} does not exist in the journal with the ID {$newEntry['contextId']}." . PHP_EOL);
                    break;
                }
                $issueGalley = $issueGalleyDao->getByBestId($args[1], $issueId);
                if (!$issueGalley) {
                    fwrite(STDERR, "Issue galley with the URL path or ID {$args[1]} does not exist in the issue with the ID {$issueId}." . PHP_EOL);
                    break;
                }
                $newEntry['submissionId'] = null;
                $newEntry['representationId'] = null;
                $newEntry['submissionFileId'] = null;
                $newEntry['fileType'] = null;
                $newEntry['issueId'] = $issueId;
                $newEntry['issueGalleyId'] = $issueGalley->getId();
                $newEntry['assocType'] = $assocType;
                break;
        }
    }

    /**
     * Set assoc type and IDs from the passed page, operation and
     * arguments specific to OMP.
     */
    protected function setOMPAssoc(int $assocType, array $args, array &$newEntry): void
    {
        switch ($assocType) {
            case Application::getContextAssocType():
                // $newEntry['contextId'] has already been set
                $newEntry['assocType'] = $assocType;
                $newEntry['submissionId'] = null;
                $newEntry['representationId'] = null;
                $newEntry['submissionFileId'] = null;
                $newEntry['fileType'] = null;
                $newEntry['chapterId'] = null;
                $newEntry['seriesId'] = null;
                break;

            case Application::ASSOC_TYPE_SUBMISSION:
                if (!isset($args[0])) {
                    fwrite(STDERR, 'Missing submission ID URL parameter.' . PHP_EOL);
                    break;
                }
                $submissionId = (int) $args[0];
                if (!Repo::submission()->exists($submissionId, $newEntry['contextId'])) {
                    fwrite(STDERR, "Submission with the ID {$submissionId} does not exist in the press with the ID {$newEntry['contextId']}." . PHP_EOL);
                    break;
                }
                // If it is an older submission version, the arguments must be:
                // $submissionId/version/$publicationId.
                if (in_array('version', $args)) {
                    if ($args[1] !== 'version' || !isset($args[2])) {
                        fwrite(STDERR, 'The following arguments are expected and not found: <submissionId>/version/<publicationId>.' . PHP_EOL);
                        break;
                    }
                    $publicationId = (int) $args[2];
                    if (!Repo::publication()->exists($publicationId, $submissionId)) {
                        fwrite(STDERR, "Publication (submission version) with the ID {$publicationId} does not exist in the submission with the ID {$submissionId}." . PHP_EOL);
                        break;
                    }
                }
                $newEntry['submissionId'] = $submissionId;
                $newEntry['assocType'] = $assocType;
                $newEntry['representationId'] = null;
                $newEntry['submissionFileId'] = null;
                $newEntry['fileType'] = null;
                $newEntry['chapterId'] = null;
                $newEntry['seriesId'] = null;
                break;

            case Application::ASSOC_TYPE_SUBMISSION_FILE:
                if (!isset($args[0])) {
                    fwrite(STDERR, 'Missing submission ID URL parameter.' . PHP_EOL);
                    break;
                }
                if (!isset($args[1])) {
                    fwrite(STDERR, 'Missing publication format ID URL parameter.' . PHP_EOL);
                    break;
                }
                if (!isset($args[2])) {
                    fwrite(STDERR, 'Missing file or publication ID URL parameter.' . PHP_EOL);
                    break;
                }

                $submissionId = (int) $args[0];
                $submissionExists = Repo::submission()->exists($submissionId, $newEntry['contextId']);
                if (!$submissionExists) {
                    fwrite(STDERR, "Submission with the ID {$submissionId} does not exist in the press with the ID {$newEntry['contextId']}." . PHP_EOL);
                    break;
                }

                // If it is an older submission version, the arguments must be:
                // $submissionId/version/$publicationId/$representationId/$submissionFileId.
                $publicationId = null;
                if (in_array('version', $args)) {
                    // This is a newer log file and it should contain submissionId in this case
                    if ($args[1] !== 'version' || !isset($args[2]) || !isset($args[3]) || !isset($args[4])) {
                        fwrite(STDERR, 'The following arguments are expected and not found: <submissionId>/version/<publicationId>/<publicationFormatId>/<fileId>.' . PHP_EOL);
                        break;
                    }
                    $publicationId = (int) $args[2];
                    $representationId = (int) $args[3];
                    $submissionFileId = (int) $args[4];
                    if (!Repo::publication()->exists($publicationId, $submissionId)) {
                        fwrite(STDERR, "Publication (submission version) with the ID {$publicationId} does not exist in the submission with the ID {$submissionId}." . PHP_EOL);
                        break;
                    }
                } else {
                    $representationId = (int) $args[1];
                    $submissionFileId = (int) $args[2];
                }

                $publicationFormatDao = DAORegistry::getDAO('PublicationFormatDAO');  /* @var $publicationFormatDao PublicationFormatDAO */
                $publicationFormat = $publicationFormatDao->getById($representationId, $publicationId);
                if (!$publicationFormat) {
                    fwrite(STDERR, "Publication format with the ID {$representationId} does not exist." . PHP_EOL);
                    break;
                }

                $submissionFile = Repo::submissionFile()->get($submissionFileId, $submissionId);
                if (!$submissionFile) {
                    fwrite(STDERR, "Submission file with the ID {$submissionFileId} does not exist in the submission with the ID {$submissionId}." . PHP_EOL);
                    break;
                }
                if ($submissionFile->getData('assocType') != Application::ASSOC_TYPE_PUBLICATION_FORMAT) {
                    fwrite(STDERR, "Submission file with the ID {$submissionFileId} does not belong to a publication format." . PHP_EOL);
                    break;
                }
                if ($representationId != $submissionFile->getData('assocId')) {
                    fwrite(STDERR, "Submission file with the ID {$submissionFileId} does not belong to the publication format with the ID {$representationId}." . PHP_EOL);
                    break;
                }

                // is this a full text or supp file
                $genreDao = DAORegistry::getDAO('GenreDAO');
                $genre = $genreDao->getById($submissionFile->getData('genreId'));
                if ($genre->getCategory() != Genre::GENRE_CATEGORY_DOCUMENT || $genre->getSupplementary() || $genre->getDependent()) {
                    $newEntry['assocType'] = Application::ASSOC_TYPE_SUBMISSION_FILE_COUNTER_OTHER;
                } else {
                    $newEntry['assocType'] = $assocType;
                }
                $newEntry['submissionId'] = $submissionId;
                $newEntry['representationId'] = $representationId;
                $newEntry['submissionFileId'] = $submissionFileId;
                $newEntry['fileType'] = StatisticsHelper::getDocumentType($submissionFile->getData('mimetype'));
                $newEntry['chapterId'] = $submissionFile->getData('chapterId');
                $newEntry['seriesId'] = null;
                break;

            case Application::ASSOC_TYPE_SERIES:
                if (!isset($args[0])) {
                    fwrite(STDERR, 'Missing series path URL parameter.' . PHP_EOL);
                    break;
                }
                $seriesPath = $args[0];
                $seriesDao = Application::getSectionDAO(); /* @var $seriesDao SeriesDAO */
                $series = $seriesDao->getByPath($seriesPath, $newEntry['contextId']);
                if (!$series) {
                    fwrite(STDERR, "Series with the path {$seriesPath} does not exist in the press with the ID {$newEntry['contextId']}." . PHP_EOL);
                    break;
                }
                $newEntry['submissionId'] = null;
                $newEntry['representationId'] = null;
                $newEntry['submissionFileId'] = null;
                $newEntry['fileType'] = null;
                $newEntry['chapterId'] = null;
                $newEntry['seriesId'] = $series->getId();
                $newEntry['assocType'] = $assocType;
                break;
        }
    }


    /**
     * Set assoc type and IDs from the passed page, operation and
     * arguments specific to OPS.
     */
    protected function setOPSAssoc(int $assocType, array $args, array &$newEntry): void
    {
        switch ($assocType) {
            case Application::getContextAssocType():
                // $newEntry['contextId'] has already been set
                $newEntry['assocType'] = $assocType;
                $newEntry['submissionId'] = null;
                $newEntry['representationId'] = null;
                $newEntry['submissionFileId'] = null;
                $newEntry['fileType'] = null;
                break;

            case Application::ASSOC_TYPE_SUBMISSION:
                if (!isset($args[0])) {
                    fwrite(STDERR, 'Missing submission ID URL parameter.' . PHP_EOL);
                    break;
                }
                $submissionId = (int) $args[0];
                if (!Repo::submission()->exists($submissionId, $newEntry['contextId'])) {
                    fwrite(STDERR, "Submission with the ID {$submissionId} does not exist in the server with the ID {$newEntry['contextId']}." . PHP_EOL);
                    break;
                }
                // If it is an older submission version, the arguments must be:
                // $submissionId/version/$publicationId.
                if (in_array('version', $args)) {
                    if ($args[1] !== 'version' || !isset($args[2])) {
                        fwrite(STDERR, 'The following arguments are expected and not found: <submissionId>/version/<publicationId>.' . PHP_EOL);
                        break;
                    }
                    $publicationId = (int) $args[2];
                    if (!Repo::publication()->exists($publicationId, $submissionId)) {
                        fwrite(STDERR, "Publication (submission version) with the ID {$publicationId} does not exist in the submission with the ID {$submissionId}." . PHP_EOL);
                        break;
                    }
                }
                $newEntry['submissionId'] = $submissionId;
                $newEntry['assocType'] = $assocType;
                $newEntry['representationId'] = null;
                $newEntry['submissionFileId'] = null;
                $newEntry['fileType'] = null;
                break;

            case Application::ASSOC_TYPE_SUBMISSION_FILE:
                if (!isset($args[0])) {
                    fwrite(STDERR, 'Missing submission ID URL parameter.' . PHP_EOL);
                    break;
                }
                if (!isset($args[1])) {
                    fwrite(STDERR, 'Missing galley ID URL parameter.' . PHP_EOL);
                    break;
                }
                if (!isset($args[2])) {
                    fwrite(STDERR, 'Missing file or publication ID URL parameter.' . PHP_EOL);
                    break;
                }

                $submissionId = (int) $args[0];
                $submissionExists = Repo::submission()->exists($submissionId, $newEntry['contextId']);
                if (!$submissionExists) {
                    fwrite(STDERR, "Submission with the ID {$submissionId} does not exist in the server with the ID {$newEntry['contextId']}." . PHP_EOL);
                    break;
                }

                // If it is an older submission version, the arguments must be:
                // $submissionId/version/$publicationId/$representationId/$submissionFileId.
                $publicationId = null;
                if (in_array('version', $args)) {
                    // This is a newer log file and it should contain submissionId in this case
                    if ($args[1] !== 'version' || !isset($args[2]) || !isset($args[3]) || !isset($args[4])) {
                        fwrite(STDERR, 'The following arguments are expected and not found: <submissionId>/version/<publicationId>/<galleyId>/<fileId>.' . PHP_EOL);
                        break;
                    }
                    $publicationId = (int) $args[2];
                    $representationId = (int) $args[3];
                    $submissionFileId = (int) $args[4];
                    if (!Repo::publication()->exists($publicationId, $submissionId)) {
                        fwrite(STDERR, "Publication (submission version) with the ID {$publicationId} does not exist in the submission with the ID {$submissionId}." . PHP_EOL);
                        break;
                    }
                } else {
                    $representationId = (int) $args[1];
                    $submissionFileId = (int) $args[2];
                }

                $galley = Repo::galley()->get($representationId, $publicationId);
                if (!$galley) {
                    fwrite(STDERR, "Galley with the ID {$representationId} does not exist." . PHP_EOL);
                    break;
                }
                $submissionFile = Repo::submissionFile()->get($submissionFileId, $submissionId);
                if (!$submissionFile) {
                    fwrite(STDERR, "Submission file with the ID {$submissionFileId} does not exist in the submission with the ID {$submissionId}." . PHP_EOL);
                    break;
                }
                if ($galley->getData('submissionFileId') != $submissionFileId) {
                    fwrite(STDERR, "Submission file with the ID {$submissionFileId} does not belong to the galley with the ID {$representationId}." . PHP_EOL);
                    break;
                }

                // is this a full text or supp file
                $genreDao = DAORegistry::getDAO('GenreDAO');
                $genre = $genreDao->getById($submissionFile->getData('genreId'));
                if ($genre->getCategory() != Genre::GENRE_CATEGORY_DOCUMENT || $genre->getSupplementary() || $genre->getDependent()) {
                    $newEntry['assocType'] = Application::ASSOC_TYPE_SUBMISSION_FILE_COUNTER_OTHER;
                } else {
                    $newEntry['assocType'] = $assocType;
                }
                $newEntry['submissionId'] = $submissionId;
                $newEntry['representationId'] = $representationId;
                $newEntry['submissionFileId'] = $submissionFileId;
                $newEntry['fileType'] = StatisticsHelper::getDocumentType($submissionFile->getData('mimetype'));
                break;
        }
    }
}
