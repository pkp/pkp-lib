<?php

/**
 * @file tools/convertUsageStatsLogFile.php
 *
 * Copyright (c) 2022 Simon Fraser University
 * Copyright (c) 2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ConvertUsageStatsLogFile
 * @ingroup tools
 *
 * @brief CLI tool to convert old usage stats log file (used in releases < 3.4) into the new format.
 */

use APP\core\Application;
use APP\facades\Repo;
use APP\statistics\StatisticsHelper;
use PKP\config\Config;
use PKP\context\Context;
use PKP\core\Core;
use PKP\core\Registry;
use PKP\db\DAORegistry;
use PKP\file\FileManager;
use PKP\submission\Genre;
use PKP\task\FileLoader;

require(dirname(__FILE__, 4) . '/tools/bootstrap.php');

class ConvertUsageStatsLogFile extends \PKP\cliTool\CommandLineTool
{
    /**
     * Regular expression that is used for parsing the old log file entries that should be converted to the new format.
     *
     * The default regex can parse apache access log files in combined format and also the usageStats plugin's log files.
     * If the old log file that should be converted is in a different format the correct regex needs to be entered here.
     */
    public const PARSEREGEX = '/^(?P<ip>\S+) \S+ \S+ "(?P<date>.*?)" (?P<url>\S+) (?P<returnCode>\S+) "(?P<userAgent>.*?)"/';

    /**
     * Name of the log file that should be converted into the new format.
     *
     * The file needs to be in the folder usageStats/archive/
     */
    public string $fileName;

    /** List of contexts by their paths */
    public array $contextsByPath;

    /**
     * Constructor.
     *
     * @param array $argv command-line arguments (see usage)
     */
    public function __construct(array $argv = [])
    {
        parent::__construct($argv);
        if (count($this->argv) != 1) {
            $this->usage();
            exit(1);
        }
        $this->fileName = array_shift($this->argv);

        $contextDao = Application::getContextDAO(); /* @var $contextDao ContextDAO */
        $contextFactory = $contextDao->getAll(); /* @var $contextFactory DAOResultFactory */
        $this->contextsByPath = [];
        while ($context = $contextFactory->next()) { /* @var $context Context */
            $this->contextsByPath[$context->getPath()] = $context;
        }
    }

    /**
     * Print command usage information.
     */
    public function usage(): void
    {
        $archivePath = StatisticsHelper::getUsageStatsDirPath() . '/' . FileLoader::FILE_LOADER_PATH_ARCHIVE;
        echo "\nConvert an old usage stats log file.\nThe old usage stats log file needs to be in the folder {$archivePath}.\n\n"
            . "  Usage: php {$this->scriptName} [fileName]\n\n";
    }

    /**
     * Convert log file into the new format.
     *
     * The old log file will be renamed: _old is added at the end of the file name.
     */
    public function execute(): void
    {
        $archivePath = StatisticsHelper::getUsageStatsDirPath() . '/' . FileLoader::FILE_LOADER_PATH_ARCHIVE;
        $filePath = $archivePath . '/' . $this->fileName;

        $path_parts = pathinfo($filePath);
        $extension = $path_parts['extension'];

        $newFilePath = $archivePath . '/' . $path_parts['filename'] . '_new.log';
        if ($extension == 'gz') {
            $fileMgr = new FileManager();
            try {
                $filePath = $fileMgr->gzDecompressFile($filePath);
            } catch (Exception $e) {
                printf($e->getMessage() . "\n");
                exit(1);
            }
        }

        $fhandle = fopen($filePath, 'r');
        if (!$fhandle) {
            echo "Can not open file {$filePath}.\n";
            exit(1);
        }
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
                echo "Invalid log entry at line {$lineNumber}.\n";
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
            $ipNotHashed = preg_match('/^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$/', $ip);
            // shell IPv6 be considered ?
            if ($ipNotHashed === 1) {
                $saltFileName = StatisticsHelper::getSaltFileName();
                $salt = trim(file_get_contents($saltFileName));
                $hashedIp = StatisticsHelper::hashIp($ip, $salt);
                $newEntry['ip'] = $hashedIp;
            } else {
                // check if it is a string(64) i.e. sha256 ?
                $newEntry['ip'] = $ip;
            }

            $newEntry['userAgent'] = $entryData['userAgent'];
            $newEntry['canonicalUrl'] = $entryData['url'];

            [$assocType, $contextPaths, $page, $op, $args] = $this->getUrlMatches($entryData['url'], $lineNumber);
            if ($assocType && $contextPaths && $page && $op) {
                $foundContextPath = current($contextPaths);
                if (!array_key_exists($foundContextPath, $this->contextsByPath)) {
                    echo "Context with the path {$foundContextPath} does not exist.\n";
                    continue;
                }
                $context = $this->contextsByPath[$foundContextPath];
                $newEntry['contextId'] = $context->getId();

                $this->setAssoc($assocType, $op, $args, $newEntry);
                if (!array_key_exists('assocType', $newEntry)) {
                    echo "The URL {$entryData['url']} in the line number {$lineNumber} was not considered.\n";
                    continue;
                }
            } else {
                continue;
            }

            // Geo data
            $country = $region = $city = null;
            if ($ipNotHashed === 1) {
                $statisticsHelper = new StatisticsHelper();
                $site = Application::get()->getRequest()->getSite();
                [$country, $region, $city] = $statisticsHelper->getGeoData($site, $context, $ip, $hashedIp, false);
            }
            $newEntry['country'] = $country;
            $newEntry['region'] = $region;
            $newEntry['city'] = $city;

            // institutions IDs
            $institutionIds = [];
            if ($ipNotHashed === 1 && $context->isInstitutionStatsEnabled($site)) {
                $institutionIds = $statisticsHelper->getInstitutionIds($context->getId(), $ip, $hashedIp, false);
            }
            $newEntry['institutionIds'] = $institutionIds;

            $newEntry['version'] = Registry::get('appVersion');

            // write to a new file
            $newLogEntry = json_encode($newEntry) . PHP_EOL;
            file_put_contents($newFilePath, $newLogEntry, FILE_APPEND);
            $isSuccessful = true;
        }
        fclose($fhandle);

        if ($isSuccessful) {
            $renameFilePath = $archivePath . '/' . $path_parts['filename'] . '_old.log';
            if (!rename($filePath, $renameFilePath)) {
                echo "Cound not rename the file {$filePath} to {$renameFilePath}.\n";
                exit(1);
            } else {
                echo "Old file was renamed to {$renameFilePath}.\n";
            }
            if (!rename($newFilePath, $filePath)) {
                echo "Cound not rename the new file {$newFilePath} to {$filePath}.\n";
                exit(1);
            } else {
                echo "File {$filePath} is converted.\n";
            }
            if ($extension == 'gz') {
                try {
                    $renameFilePath = $fileMgr->gzCompressFile($renameFilePath);
                    $filePath = $fileMgr->gzCompressFile($filePath);
                } catch (Exception $e) {
                    printf($e->getMessage() . "\n");
                    exit(1);
                }
            }
        } else {
            echo "File {$filePath} could not be successfully converted.\n";
            exit(1);
        }
    }

    /**
     * Get data from the passed log entry.
     */
    private function getDataFromLogEntry(string $entry): array
    {
        $entryData = [];
        if (preg_match(self::PARSEREGEX, $entry, $m)) {
            $associative = count(array_filter(array_keys($m), 'is_string')) > 0;
            $entryData['ip'] = $associative ? $m['ip'] : $m[1];
            $entryData['date'] = strtotime($associative ? $m['date'] : $m[2]);
            $entryData['url'] = urldecode($associative ? $m['url'] : $m[3]);
            $entryData['returnCode'] = $associative ? $m['returnCode'] : $m[4];
            $entryData['userAgent'] = $associative ? $m['userAgent'] : $m[5];
        }
        return $entryData;
    }

    /**
     * Validate a access log entry.
     * This maybe does not have much sense, but because it was used till now, we will leave it.
     */
    private function isLogEntryValid(array $entry): bool
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
    private function getUrlMatches(string $url, int $lineNumber): array
    {
        $noMatchesReturner = [null, null, null, null, null];

        $expectedPageAndOp = $this->getExpectedPageAndOp();

        $pathInfoDisabled = Config::getVar('general', 'disable_path_info');

        // Apache and usage stats plugin log files come with complete or partial base url,
        // remove it so we can retrieve path, page, operation and args.
        $url = Core::removeBaseUrl($url);
        if ($url) {
            $contextPaths = Core::getContextPaths($url, !$pathInfoDisabled);
            $page = Core::getPage($url, !$pathInfoDisabled);
            $operation = Core::getOp($url, !$pathInfoDisabled);
            $args = Core::getArgs($url, !$pathInfoDisabled);
        } else {
            // Could not remove the base url, can't go on.
            // __('plugins.generic.usageStats.removeUrlError', array('file' => $filePath, 'lineNumber' => $lineNumber))
            echo "The line number {$lineNumber} contains an url that the system can't remove the base url from.\n";
            return $noMatchesReturner;
        }

        // See bug #8698#.
        if (is_array($contextPaths) && !$page && $operation == 'index') {
            $page = 'index';
        }

        if (empty($contextPaths) || !$page || !$operation) {
            echo "Either context paths, page or operation could not be parsed from the URL correctly.\n";
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
            return [$workingAssocType, $contextPaths, $page, $operation, $args];
        } else {
            echo "No matching page and operation found.\n";
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
        }
        return $pageAndOp;
    }

    /**
     * Set assoc type and IDs from the passed page, operation and arguments.
     */
    protected function setAssoc(int $assocType, string $op, array $args, array &$newEntry): void
    {
        switch ($assocType) {
            case Application::ASSOC_TYPE_SUBMISSION_FILE:
                if (!isset($args[0]) || !isset($args[1])) {
                    echo "Missing URL parameter.\n";
                    break;
                }
                $submissionId = (int) $args[0];
                $submissionExists = Repo::submission()->exists($submissionId, $newEntry['contextId']);
                if (!$submissionExists) {
                    echo "Submission with the ID {$submissionId} does not exist in the context (journal, press or server) with the ID {$newEntry['contextId']}.\n";
                    break;
                }

                $representationId = (int) $args[1];
                $galley = Repo::galley()->get($representationId);
                if (!$galley) {
                    echo "Represantation (galley or publication format) with the ID {$representationId} does not exist.\n";
                    break;
                }

                if (Application::get()->getName() == 'omp') {
                    if (!isset($args[2])) {
                        echo "Missing URL parameter.\n";
                        break;
                    } else {
                        $submissionFileId = (int) $args[2];
                    }
                } else {
                    // consider this issue: https://github.com/pkp/pkp-lib/issues/6573
                    // apache log files contain URL download/submissionId/galleyId, i.e. without third argument
                    $submissionFileId = $galley->getData('submissionFileId');
                    if (isset($args[2]) && $args[2] != $submissionFileId) {
                        echo "Submission file with the ID {$submissionFileId} does not belong to the galley with the ID {$representationId}.\n";
                        break;
                    }
                }
                $submissionFile = Repo::submissionFile()->get($submissionFileId, $submissionId);
                if (!$submissionFile) {
                    echo "Submission file with the ID {$submissionFileId} does not exist in the submission with the ID {$submissionId}.\n";
                    break;
                }
                if ($galley->getData('submissionFileId') != $submissionFileId) {
                    echo "Submission file with the ID {$submissionFileId} does not belong to the represantation (galley or publication format) with the ID {$representationId}.\n";
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
            case Application::ASSOC_TYPE_SUBMISSION:
                $publicationId = null;
                if (!isset($args[0])) {
                    echo "Missing URL parameter.\n";
                    break;
                }
                // If the operation is 'view' and the arguments count > 1
                // the arguments must be: $submissionId/version/$publicationId.
                // Else, it is not considered hier, as submission abstract count.
                if ($op == 'view' && count($args) > 1) {
                    if ($args[1] !== 'version') {
                        echo "Wrong URL parameter. Expected 'verison' found {$args[1]}.\n";
                        break;
                    } elseif (count($args) != 3) {
                        echo "Missing URL parameter.\n.\n";
                        break;
                    }
                    $publicationId = (int) $args[2];
                }
                $submissionId = (int) $args[0];
                $submissionExists = Repo::submission()->exists($submissionId, $newEntry['contextId']);
                if (!$submissionExists) {
                    echo "Submission with the ID {$submissionId} does not exist in the context (journal, press or server) with the ID {$newEntry['contextId']}.\n";
                    break;
                }
                if ($publicationId && !Repo::publication()->exists($publicationId, $submissionId)) {
                    echo "Publication (submission version) with the ID {$publicationId} does not exist in the submission with the ID {$submissionId}.\n";
                    break;
                }
                $newEntry['submissionId'] = $submissionId;
                $newEntry['assocType'] = $assocType;
                break;
            case Application::getContextAssocType():
                // $newEntry['contextId'] has already been set
                $newEntry['assocType'] = $assocType;
                break;
        }

        if (!array_key_exists('assocType', $newEntry)) {
            $application = Application::get();
            $applicationName = $application->getName();
            switch ($applicationName) {
                case 'ojs2':
                    $this->setOJSAssoc($assocType, $args, $newEntry);
                    break;
                case 'omp':
                    $this->setOMPAssoc($assocType, $args, $newEntry);
                    break;
            }
        }
    }

    /**
     * Set assoc type and IDs from the passed page, operation and
     * arguments specific to OJS.
     */
    protected function setOJSAssoc(int $assocType, array $args, array &$newEntry): void
    {
        switch ($assocType) {
            case Application::ASSOC_TYPE_ISSUE:
                if (!isset($args[0])) {
                    echo "Missing URL parameter.\n";
                    break;
                }
                // consider issue https://github.com/pkp/pkp-lib/issues/6611
                // apache log files contain both URLs for issue galley download:
                // issue/view/issueId/galleyId (that should not be considered here), as well as
                // issue/download/issueId/galleyId
                if (count($args) != 1) {
                    break;
                }
                $issueId = (int) $args[0];
                $issueExists = Repo::issue()->exists($issueId, $newEntry['contextId']);
                if (!$issueExists) {
                    echo "Issue with the ID {$issueId} does not exist in the journal with the ID {$newEntry['contextId']}.\n";
                    break;
                }
                $newEntry['issueId'] = $issueId;
                $newEntry['assocType'] = $assocType;
                break;
            case Application::ASSOC_TYPE_ISSUE_GALLEY:
                if (!isset($args[0]) || !isset($args[1])) {
                    echo "Missing URL parameter.\n";
                    break;
                }
                $issueId = (int) $args[0];
                $issueExists = Repo::issue()->exists($issueId, $newEntry['contextId']);
                if (!$issueExists) {
                    echo "Issue with the ID {$issueId} does not exist in the journal with the ID {$newEntry['contextId']}.\n";
                    break;
                }
                $issueGalleyId = (int) $args[1];
                $issueGalleyDao = DAORegistry::getDAO('IssueGalleyDAO');
                $issueGalley = $issueGalleyDao->getById($issueGalleyId, $issueId);
                if (!$issueGalley) {
                    echo "Issue galley with the ID {$issueGalleyId} does not exist in the issue with the ID {$issueId}.\n";
                    break;
                }
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
            case Application::ASSOC_TYPE_SERIES:
                if (!isset($args[0])) {
                    echo "Missing URL parameter.\n";
                    break;
                }
                $seriesPath = $args[0];
                $seriesDao = Application::getSectionDAO(); /* @var $seriesDao SeriesDAO */
                $series = $seriesDao->getByPath($seriesPath, $newEntry['contextId']);
                if (!$series) {
                    echo "Series with the path {$seriesPath} does not exist in the press with the ID {$newEntry['contextId']}.\n";
                    break;
                }
                $newEntry['seriesId'] = $series->getId();
                $newEntry['assocType'] = $assocType;
                break;
        }
    }
}

$tool = new ConvertUsageStatsLogFile($argv ?? []);
$tool->execute();
