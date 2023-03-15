<?php

/**
 * @file tools/convertApacheAccessLogFile.php
 *
 * Copyright (c) 2022 Simon Fraser University
 * Copyright (c) 2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ConvertApacheAccessLogFile
 * @ingroup tools
 *
 * @brief CLI tool to copy, prepare and convert apache access log file into the new format needed for stats reprocessing.
 *
 * The file will be copied to the {files_dir}/usageStats/tmp/ folder,
 * only entries related to the current instalation will be filtered,
 * the file will be spit by day,
 * renamed into apache_usage_events_YYYYMMDD.log,
 * converted to the new format,
 * and copied into the {files_dir}/usageStats/archive/ folder.
 *
 * Special cases from the release 2.x are handled as following:
 *
 * Issue Galley:
 * with PDF viewer:
 * issue/viewIssue/issueId/galleyId followed by issue/viewFile/issueId/galleyId
 * -> only issue/viewFile/issueId/galleyId will be considered.
 * There is also only issue/download/issueId/galleyId (when download link is used).
 * without PDF viewer:
 * issue/viewIssue/issueId/galleyId will not be considered because the file is actually not downloaded.
 * But issue/download/issueId/galleyId will be considered.
 *
 * PDF Galley:
 * article/view/articleId/galleyId followed by article/viewFile/articleId/galleyId
 * -> only article/viewFile/articleId/galleyId will be considered.
 * There is also only article/donwload/articleId/galleyId (when download link is used.
 * without PDF viewer:
 * article/view/articleId/galleyId will not be considered because the file is actually not downloaded.
 * But article/download/articleId/galleyId will be considered.
 *
 * HMTL Galley:
 * article/view/articleId/galleyId followed by article/viewFile/articleId/galleyId
 * -> only article/viewFile/articleId/galleyId will be considered.
 *
 * Other and Remote Galley:
 * article/view/articleId/galleyId
 *
 * Supp File:
 * article/downloadSuppFile/articleId/galleyId
 */

require(dirname(__FILE__, 4) . '/tools/bootstrap.php');

use APP\core\Application;
use APP\facades\Repo;
use APP\statistics\StatisticsHelper;
use PKP\cliTool\ConvertLogFile;
use PKP\context\Context;
use PKP\db\DAORegistry;
use PKP\file\FileManager;
use PKP\statistics\PKPStatisticsHelper;
use PKP\submission\Genre;
use PKP\task\FileLoader;

class ConvertApacheAccessLogFile extends ConvertLogFile
{
    /**
     * Path to the egrep program, required for this tool to work, e.g. '/bin/egrep'
     */
    public const EGREP_PATH = '/bin/egrep';

    /**
     * Weather the URL parameters are used instead of CGI PATH_INFO.
     * This is the former variable 'disable_path_info' in the config.inc.php
     *
     * This needs to be set to true if the URLs in the old log file contain the paramteres as URL query string.
     */
    public const PATH_INFO_DISABLED = false;

    /**
     * Regular expression that is used for parsing the apache access log file.
     *
     * The default regex can parse apache access log file in combined format
     * ("%h %l %u %t \"%r\" %>s %b \"%{Referer}i\" \"%{User-agent}i\"").
     *
     * If the apache log file is in different format the correct regex needs to be entered here, so
     * that ip, date, url, returnCode, and userAgent can be extracted,
     * s. also PHP subpatterns naming: https://www.php.net/manual/en/regexp.reference.subpatterns.php
     */
    public const PARSEREGEX = '/^(?P<ip>\S+) \S+ \S+ \[(?P<date>.*?)\] "\S+ (?P<url>\S+).*?" (?P<returnCode>\S+) \S+ ".*?" "(?P<userAgent>.*?)"/';

    /**
     * PHP format of the time in the log file.
     * S. https://www.php.net/manual/en/datetime.format.php
     *
     * The default format can parse the apache access log file combined format ([day/month/year:hour:minute:second zone]).
     *
     * If the time in the apache log file is in a different format the correct PHP format needs to be entered here.
     */
    // TO-DO: ask how to deal with timezone, do we need it?
    public const PHP_DATETIME_FORMAT = 'd/M/Y:H:i:s O';

    /**
     * PHP format of the date (without time and timezone)
     */
    public const PHP_DATE_FORMAT = 'd/M/Y';

    /**
     * Constructor.
     *
     * @param array $argv command-line arguments
     */
    public function __construct(array $argv = [])
    {
        parent::__construct($argv);
        if (count($this->argv) < 1 || count($this->argv) > 2) {
            $this->usage();
            exit(8);
        }

        // This tool needs egrep path configured.
        if (file_exists(self::EGREP_PATH)) {
            fwrite(STDERR, 'Error: This tool needs egrep program. Please define the constatn EGREP_PATH in this script, enter there the path to egrep command on your machine.' . PHP_EOL);
            exit(9);
        }
    }

    public function getLogFileDir(): string
    {
        return PKPStatisticsHelper::getUsageStatsDirPath() . '/tmp';
    }

    public function getParseRegex(): string
    {
        return self::PARSEREGEX;
    }

    public function getPhpDateTimeFormat(): string
    {
        return self::PHP_DATETIME_FORMAT;
    }

    public function isPathInfoDisabled(): bool
    {
        return self::PATH_INFO_DISABLED;
    }

    public function isApacheAccessLogFile(): bool
    {
        return true;
    }

    /**
     * Print command usage information.
     */
    public function usage()
    {
        echo "\nConvert the passed apache access log file into the new usage stats log file format.
This will copy the apache access file to the usageStats/tmp/ folder in the files directory,
filter entries related to this installation, split the file by day, rename the result file(s)
into apache_usage_events_YYYYMMDD.log, convert them into the new JSON format, and
copy them to usageStats/archive/ folder.
Must run under user with enough privilegies to read access apache log files.\n"
. "  Usage: php {$this->scriptName} [path/to/apache/log/file.log]\n\n";
    }

    /**
     * Create the temporary processing folder and call the function to process the log file.
     */
    public function execute(): void
    {
        $fileMgr = new FileManager();
        $filePath = current($this->argv);

        if ($fileMgr->fileExists($this->getLogFileDir(), 'dir')) {
            $fileMgr->rmtree($this->getLogFileDir());
        }

        if (!$fileMgr->mkdir($this->getLogFileDir())) {
            fwrite(STDERR, "Error: Can't create folder " . $this->getLogFileDir() . PHP_EOL);
            exit(10);
        }

        if ($fileMgr->fileExists($filePath)) {
            $this->processAccessLogFile($filePath);
        } else {
            fwrite(STDERR, "Error: File {$filePath} don't exist or can't be accessed." . PHP_EOL);
            exit(11);
        }

        // Do not remove tmp/ folder here -- it could be used by admins for checking and debugging
    }

    /**
     * Process the access log file:
     * copy it to the usageStats/tmp/ folder,
     * filter entries related to this installation,
     * split by day,
     * convert into the new JSON format,
     * copy to usageStats/archive/ folder.
     */
    public function processAccessLogFile(string $filePath)
    {
        $copiedFilePath = $this->copyFile($filePath);
        $filteredFilePath = $this->filterFile($copiedFilePath);
        $dailyFiles = $this->splitFileByDay($filteredFilePath);
        $fileMgr = new FileManager();
        foreach ($dailyFiles as $dailyFile) {
            $this->convert($dailyFile);
            $this->archive($dailyFile);
            if (pathinfo($filePath, PATHINFO_EXTENSION) == 'gz') {
                $archiveFilePath = StatisticsHelper::getUsageStatsDirPath() . '/' . FileLoader::FILE_LOADER_PATH_ARCHIVE . '/' . $dailyFile;
                $archiveFilePath = $fileMgr->gzCompressFile($archiveFilePath);
            }
        }
    }

    /**
     * Copy acess log file to the folder usageStats/tmp/
     */
    public function copyFile(string $filePath): string
    {
        $fileName = pathinfo($filePath, PATHINFO_BASENAME);
        $tmpFilePath = "{$this->getLogFileDir()}/{$fileName}";
        $fileMgr = new FileManager();
        if (!$fileMgr->copyFile($filePath, $tmpFilePath)) {
            fwrite(STDERR, "Could not copy file from {$filePath} to {$tmpFilePath}." . PHP_EOL);
            exit(12);
        }
        echo "File {$filePath} copied to {$tmpFilePath}.\n";
        return $tmpFilePath;
    }

    /**
     * Filtering accell log file entries related to this installation, i.e.
     * that contain existing context paths.
     * Save the filtered entries into a new file with the ending _tmp.
     */
    public function filterFile(string $filePath): string
    {
        $fileMgr = new FileManager();
        if (pathinfo($filePath, PATHINFO_EXTENSION) == 'gz') {
            try {
                $filePath = $fileMgr->gzDecompressFile($filePath);
            } catch (Exception $e) {
                fwrite(STDERR, $e->getMessage() . PHP_EOL);
                exit(13);
            }
        }

        $filteredFilePath = $filePath . '_tmp';
        $callback = fn (Context $context): string => $context->getPath();
        $escapedContextPaths = implode('/|/', array_map('escapeshellarg', array_map($callback, $this->contextsByPath)));
        $output = null;
        $returnValue = 0;
        exec(escapeshellarg(self::EGREP_PATH) . " -i '" . $escapedContextPaths . "' " . escapeshellarg($filePath) . ' > ' . escapeshellarg($filteredFilePath), $output, $returnValue);
        if ($returnValue > 1) {
            fwrite(STDERR, 'Error: the execution of ' . self::EGREP_PATH . ' is not possible.' . PHP_EOL);
            exit(14);
        }
        clearstatcache();
        if (filesize($filePath) == 0) {
            fwrite(STDERR, 'Error: No entries found related to this installation.' . PHP_EOL);
            exit(15);
        }
        return $filteredFilePath;
    }

    /**
     * Split access log file by day. The new, daily files will be named to apache_usage_events_YYYYMMDD.log
     *
     * @return array List of daily access log files.
     */
    public function splitFileByDay(string $filePath): array
    {
        // Get the first and the last date in the log file
        $firstDate = $lastDate = null;
        $splFileObject = new SplFileObject($filePath, 'r');
        while (!$splFileObject->eof()) {
            $line = $splFileObject->fgets();
            if (preg_match(self::PARSEREGEX, $line, $m)) {
                $firstDate = DateTime::createFromFormat(self::PHP_DATETIME_FORMAT, $m[2]);
                break;
            }
        }
        $splFileObject->seek(PHP_INT_MAX);
        $lastLineNo = $splFileObject->key() + 1;
        do {
            $splFileObject->seek($lastLineNo);
            $line = $splFileObject->current();
            if (preg_match(self::PARSEREGEX, $line, $m)) {
                $lastDate = DateTime::createFromFormat(self::PHP_DATETIME_FORMAT, $m[2]);
                break;
            }
            $lastLineNo = $splFileObject->key() - 1;
        } while ($lastLineNo > 0);
        //explicitly assign null, so that the file can be deleted
        $splFileObject = null;

        if (is_null($firstDate) || is_null($lastDate)) {
            fwrite(STDERR, 'Error: First or last date not found.' . PHP_EOL);
            exit(16);
        }

        // Get all days between the first and the last date, including the last date
        $period = new DatePeriod(
            $firstDate,
            new DateInterval('P1D'),
            $lastDate
        );

        $dailyFiles = [];
        foreach ($period as $key => $value) {
            $day = $value->format('Ymd');
            // Check if a converted apache file with the same day already exists in any of usageStats/ folders.
            $existingApacheUsageEventsFiles = glob(PKPStatisticsHelper::getUsageStatsDirPath() . '/*/apache_usage_events_' . $day . '*');
            $existingApacheUsageEventsFilesCount = count($existingApacheUsageEventsFiles) ? count($existingApacheUsageEventsFiles) : 0;
            $countPartOfFileName = '';
            if ($existingApacheUsageEventsFilesCount) {
                $countPartOfFileName = "_{$existingApacheUsageEventsFilesCount}_";
                fwrite(STDERR, "Warning: One or more files apache_usage_events_{$day}.log already exist. You will need to clean or merge them into one before reprocessing the statistics." . PHP_EOL);
            }
            $dailyFileName = 'apache_usage_events_' . $day . $countPartOfFileName . '.log';
            $dayFilePath = $this->getLogFileDir() . '/' . $dailyFileName;
            $output = null;
            $returnValue = 0;
            exec(escapeshellarg(self::EGREP_PATH) . " -i '" . preg_quote($value->format(self::PHP_DATE_FORMAT)) . "' " . escapeshellarg($filePath) . ' > ' . escapeshellarg($dayFilePath), $output, $returnValue);
            if ($returnValue > 1) {
                fwrite(STDERR, 'Error: Could not split file by day.' . PHP_EOL);
                exit(17);
            }
            $dailyFiles[] = $dailyFileName;
            echo "File {$dayFilePath} created.\n";
        }

        return $dailyFiles;
    }

    /**
     * Copy the file from the folder usageStats/tmp/ into usageStats/archive/.
     */
    public function archive(string $fileName): void
    {
        $tmpFilePath = "{$this->getLogFileDir()}/{$fileName}";
        $archiveFilePath = StatisticsHelper::getUsageStatsDirPath() . '/' . FileLoader::FILE_LOADER_PATH_ARCHIVE . '/' . $fileName;
        $fileMgr = new FileManager();
        if (!$fileMgr->copyFile($tmpFilePath, $archiveFilePath)) {
            fwrite(STDERR, "Error: Could not copy file from {$tmpFilePath} to {$archiveFilePath}." . PHP_EOL);
            exit(18);
        }
        echo "File {$tmpFilePath} successfully archived to {$archiveFilePath}.\n";
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
                        'article/download', 'article/viewFile'],
                    Application::ASSOC_TYPE_SUBMISSION => [
                        'article/view', 'article/viewArticle'],
                    Application::ASSOC_TYPE_ISSUE => [
                        'issue/view'],
                    Application::ASSOC_TYPE_ISSUE_GALLEY => [
                        'issue/download', 'issue/viewFile']
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
            default:
                throw new Exception('Unrecognized application name.');
        }
        return $pageAndOp;
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
                break;

            case Application::ASSOC_TYPE_SUBMISSION:
                if (!isset($args[0])) {
                    fwrite(STDERR, 'Missing submission ID URL parameter.' . PHP_EOL);
                    break;
                }

                $submission = Repo::submission()->getByBestId($args[0], $newEntry['contextId']);
                if (!$submission) {
                    fwrite(STDERR, "Submission with the URL path or ID {$args[0]} does not exist in the journal with the ID {$newEntry['contextId']}." . PHP_EOL);
                    break;
                }
                $submissionId = $submission->getId();

                // If it is an older submission version, the arguments must be:
                // $submissionId/version/$publicationId.
                // Consider also releases 2.x where log files can contain URL
                // view/$submissionId/$representationId i.e. without $submissionFileId argument
                // for other and remote galleys.
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
                    // Consider usage stats log files from releases 2.x:
                    // The URL article/view/{$articleId}/{$galleyId} was used for assoc type galley.
                    // Only Other galleys will be considered here (s. file description above).
                    // Those should then be assoc type = submission file.
                    $representationUrlPath = $args[1];
                    $galley = $representationId = null;
                    if (is_int($representationUrlPath) || ctype_digit($representationUrlPath)) {
                        // assume it is ID and not the URL path
                        $representationId = (int) $representationUrlPath;
                        $galley = Repo::galley()->get($representationId);
                    } else {
                        // We need to get the publication in order to be able to get galley by URL path
                        // We cannot assume that this is the current publication,
                        // because the log entry can be long time ago, and
                        // since then there could be new submission versions created,
                        // so take the first publication and galley found with the given representationUrlPath.
                        // (Different publications can contain the same galley URL path.)
                        // It is not accurate but only possible.
                        $publications = $submission->getData('publications');
                        foreach ($publications as $publication) {
                            foreach ($publication->getData('galleys') as $publicationGalley) {
                                if ($publicationGalley->getBestGalleyId() == $representationUrlPath) {
                                    $galley = $publicationGalley;
                                    $representationId = $publicationGalley->getId();
                                    break 2;
                                }
                            }
                        }
                    }
                    if (!isset($galley)) {
                        fwrite(STDERR, "Galley with the URL path {$representationUrlPath} does not exist in the submission with the ID {$submissionId}." . PHP_EOL);
                        break;
                    }
                    $submissionFileId = $galley->getData('submissionFileId');
                    if (!isset($submissionFileId)) {
                        break;
                    }
                    $submissionFile = Repo::submissionFile()->get($submissionFileId, $submissionId);
                    if (!isset($submissionFile)) {
                        fwrite(STDERR, "Submission file with the ID {$submissionFileId} does not exist in the submission with the ID {$submissionId}." . PHP_EOL);
                        break;
                    }
                    $fileType = StatisticsHelper::getDocumentType($submissionFile->getData('mimetype'));
                    if ($fileType == StatisticsHelper::STATISTICS_FILE_TYPE_PDF || $fileType == StatisticsHelper::STATISTICS_FILE_TYPE_HTML) {
                        // Do not consider PDF and HTML file, the download URL will follow
                        break;
                    }
                    $newEntry['assocType'] = Application::ASSOC_TYPE_SUBMISSION_FILE;
                    $newEntry['submissionId'] = $submissionId;
                    $newEntry['representationId'] = $representationId;
                    $newEntry['submissionFileId'] = $submissionFileId;
                    $newEntry['fileType'] = $fileType;
                    break;
                }
                $newEntry['submissionId'] = $submissionId;
                $newEntry['assocType'] = $assocType;
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

                $submission = Repo::submission()->getByBestId($args[0], $newEntry['contextId']);
                if (!$submission) {
                    fwrite(STDERR, "Submission with the URL path or ID {$args[0]} does not exist in the journal with the ID {$newEntry['contextId']}." . PHP_EOL);
                    break;
                }
                $submissionId = $submission->getId();

                // If it is an older submission version, the arguments must be:
                // $submissionId/version/$publicationId/$representationId/$submissionFileId.
                // Consider also this issue: https://github.com/pkp/pkp-lib/issues/6573
                // where apache log files can contain URL download/$submissionId/$representationId,
                // i.e. without $submissionFileId argument.
                // Also the URLs from releases 2.x will not have submissionFileId.
                $publicationId = $submissionFileId = null; // do not necessarily exist
                if (in_array('version', $args)) {
                    if ($args[1] !== 'version' || !isset($args[2]) || !isset($args[3])) {
                        // if version is there, there must be $publicationId and $representationId arguments
                        fwrite(STDERR, 'The following arguments are expected and not found: <submissionId>/version/<publicationId>/<galleyId>/<fileId>.' . PHP_EOL);
                        break;
                    }
                    $publicationId = (int) $args[2];
                    $representationUrlPath = $args[3];
                    if (isset($args[4])) {
                        $submissionFileId = (int) $args[4];
                    }
                } else {
                    $representationUrlPath = $args[1];
                    if (isset($args[2])) {
                        $submissionFileId = (int) $args[2];
                    }
                }

                // Find the galley and representation ID
                $representationId = $galley = null;
                if (is_int($representationUrlPath) || ctype_digit($representationUrlPath)) {
                    // assume it is ID and not the URL path
                    $representationId = (int) $representationUrlPath;
                    $galley = Repo::galley()->get($representationId);
                    if (!$galley) {
                        fwrite(STDERR, "Galley with the ID {$representationUrlPath} does not exist." . PHP_EOL);
                        break;
                    }
                } else {
                    // We need to get the publication in order to be able to get galley by URL path
                    $publications = $submission->getData('publications');
                    if (isset($publicationId)) {
                        $publication = $publications->first(function ($value, $key) use ($publicationId) {
                            return $value->getId() == $publicationId;
                        });
                        if (!$publication) {
                            fwrite(STDERR, "Publication (submission version) with the ID {$publicationId} does not exist in the submission with the ID {$submissionId}." . PHP_EOL);
                            break;
                        }
                        $galley = Repo::galley()->getByUrlPath($representationUrlPath, $publication);
                        if (!$galley) {
                            fwrite(STDERR, "Galley with the URL path {$representationUrlPath} does not exist in the publication (submission version) with the ID {$publicationId}." . PHP_EOL);
                            break;
                        }
                        $representationId = $galley->getId();
                    } else {
                        // We cannot assume that this is the current publication,
                        // because the log entry can be long time ago, and
                        // since then there could be new submission versions created,
                        // so take the first publication and galley found with the given representationUrlPath.
                        // (Different publications can contain the same galley URL path.)
                        $possibleGalleys = [];
                        foreach ($publications as $publication) {
                            foreach ($publication->getData('galleys') as $publicationGalley) {
                                if ($publicationGalley->getBestGalleyId() == $representationUrlPath) {
                                    $possibleGalleys[] = $publicationGalley;
                                    if (isset($submissionFileId) && $publicationGalley->getData('submissionFileId') == $submissionFileId) {
                                        $galley = $publicationGalley;
                                        $representationId = $publicationGalley->getId();
                                        break 2;
                                    }
                                }
                            }
                        }
                        if (empty($possibleGalleys)) {
                            fwrite(STDERR, "Galley with the URL path {$representationUrlPath} does not exist in the submission with the ID {$submissionId}." . PHP_EOL);
                            break;
                        }
                        // if no matching galley has been found yet, take the first possible
                        if (!isset($representationId)) {
                            $galley = $possibleGalleys[0];
                            $representationId = $galley->getId();
                        }
                    }
                }
                if (!$submissionFileId) {
                    $submissionFileId = $galley->getData('submissionFileId');
                }
                $submissionFile = Repo::submissionFile()->get($submissionFileId, $submissionId);
                if (!$submissionFile) {
                    fwrite(STDERR, "Submission file with the ID {$submissionFileId} does not exist in the submission with the ID {$submissionId}." . PHP_EOL);
                    break;
                }
                if ($galley->getData('submissionFileId') != $submissionFileId) {
                    // This check is e.g. when representation ID (and not URL path) and submissionFileId are given as arguments
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

            case Application::ASSOC_TYPE_SUBMISSION_FILE_COUNTER_OTHER:
                // This is the URL article/downloadSuppFile/articleId/suppFileId from a 2.x log file
                if (!isset($args[0])) {
                    fwrite(STDERR, 'Missing submission ID URL parameter.' . PHP_EOL);
                    break;
                }
                if (!isset($args[1])) {
                    fwrite(STDERR, 'Missing supp file ID URL parameter.' . PHP_EOL);
                    break;
                }

                $submission = Repo::submission()->getByBestId($args[0], $newEntry['contextId']);
                if (!$submission) {
                    fwrite(STDERR, "Submission with the URL path or ID {$args[0]} does not exist in the journal with the ID {$newEntry['contextId']}." . PHP_EOL);
                    break;
                }
                $submissionId = $submission->getId();

                $galley = $submissionFile = null;
                $publications = $submission->getData('publications');
                foreach ($publications as $publication) {
                    foreach ($publication->getData('galleys') as $possibleGalley) {
                        $possibleSubmissionFileId = $possibleGalley->getData('submissionFileId');
                        if ($possibleSubmissionFileId) { // it is not a remote supp file
                            $possibleSubmissionFile = Repo::submissionFile()->get($possibleSubmissionFileId, $submissionId);
                            if ($possibleSubmissionFile) {
                                if (is_int($args[1]) || ctype_digit($args[1])) { // supp file ID
                                    if ($possibleSubmissionFile->getData('old-supp-id') == $args[1]) {
                                        // Galley and file found
                                        $galley = $possibleGalley;
                                        $submissionFile = $possibleSubmissionFile;
                                        break 2;
                                    }
                                } else { // supp file URL path
                                    if ($possibleGalley->getData('urlPath') == $args[1]) {
                                        // Galley and file found
                                        $galley = $possibleGalley;
                                        $submissionFile = $possibleSubmissionFile;
                                        break 2;
                                    }
                                }
                            }
                        }
                    }
                }
                if ($galley && $submissionFile) {
                    $newEntry['assocType'] = $assocType;
                    $newEntry['submissionId'] = $submissionId;
                    $newEntry['representationId'] = $galley->getId();
                    $newEntry['submissionFileId'] = $submissionFile->getId();
                    $newEntry['fileType'] = StatisticsHelper::getDocumentType($submissionFile->getData('mimetype'));
                } else {
                    fwrite(STDERR, 'Supp file could not be found.' . PHP_EOL);
                }
                break;

            case Application::ASSOC_TYPE_ISSUE:
                if (!isset($args[0])) {
                    fwrite(STDERR, 'Missing issue ID URL parameter.' . PHP_EOL);
                    break;
                }
                // Consider issue https://github.com/pkp/pkp-lib/issues/6611
                // where apache log files contain both URLs for issue galley download:
                // issue/view/issueId/galleyId (that should not be considered here), as well as
                // issue/download/issueId/galleyId (that is considered below)
                if (count($args) != 1) {
                    break;
                }
                $issue = Repo::issue()->getByBestId($args[0], $newEntry['contextId']);
                if (!$issue) {
                    fwrite(STDERR, "Issue with the URL path or ID {$args[0]} does not exist in the journal with the ID {$newEntry['contextId']}." . PHP_EOL);
                    break;
                }
                $issueId = $issue->getId();
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

                $issue = Repo::issue()->getByBestId($args[0], $newEntry['contextId']);
                if (!$issue) {
                    fwrite(STDERR, "Issue with the URL path or ID {$args[0]} does not exist in the journal with the ID {$newEntry['contextId']}." . PHP_EOL);
                    break;
                }
                $issueId = $issue->getId();
                $issueGalleyDao = DAORegistry::getDAO('IssueGalleyDAO');
                $issueGalley = $issueGalleyDao->getByBestId($args[1], $issueId);
                if (!$issueGalley) {
                    fwrite(STDERR, "Issue galley with the URL path or ID {$args[1]} does not exist in the issue with the ID {$issueId}." . PHP_EOL);
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
            case Application::getContextAssocType():
                // $newEntry['contextId'] has already been set
                $newEntry['assocType'] = $assocType;
                break;

            case Application::ASSOC_TYPE_SUBMISSION:
                if (!isset($args[0])) {
                    fwrite(STDERR, 'Missing submission ID URL parameter.' . PHP_EOL);
                    break;
                }

                $submission = Repo::submission()->getByBestId($args[0], $newEntry['contextId']);
                if (!$submission) {
                    fwrite(STDERR, "Submission with the URL path or ID {$args[0]} does not exist in the press with the ID {$newEntry['contextId']}." . PHP_EOL);
                    break;
                }
                $submissionId = $submission->getId();

                // If it is an older submission version, the arguments must be:
                // $submissionId/version/$publicationId.
                $publicationId = null;
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

                // Is it a chapter landing page
                $chapter = null;
                if (in_array('chapter', $args)) {
                    if (isset($publicationId)) {
                        // The URL is $submissionId/version/$publicationId/chapter/$chapterId
                        if ($args[3] !== 'chapter' || !isset($args[4])) {
                            fwrite(STDERR, 'The following arguments are expected and not found: <submissionId>/version/<publicationId>/chapter/<chapterId>.' . PHP_EOL);
                            break;
                        }
                        $chapterId = (int) $args[4];
                    } else {
                        // The URL is $submissionId/chapter/$chapterId
                        if ($args[1] !== 'chapter' || !isset($args[2])) {
                            fwrite(STDERR, 'The following arguments are expected and not found: <submissionId>/chapter/<chapterId>.' . PHP_EOL);
                            break;
                        }
                        $chapterId = (int) $args[2];
                    }
                    $chapterDao = DAORegistry::getDAO('ChapterDAO'); /** @var ChapterDAO $chapterDao */
                    $chapter = $chapterDao->getChapter($chapterId);
                    if (!$chapter) {
                        fwrite(STDERR, "Chapter with the ID {$chapterId} does not exist." . PHP_EOL);
                        break;
                    }
                }

                $newEntry['submissionId'] = $submissionId;
                $newEntry['assocType'] = isset($chapter) ? Application::ASSOC_TYPE_CHAPTER : $assocType;
                $newEntry['chpaterId'] = isset($chapter) ? $chapter->getId() : null;
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

                $submission = Repo::submission()->getByBestId($args[0], $newEntry['contextId']);
                if (!$submission) {
                    fwrite(STDERR, "Submission with the URL path or ID {$args[0]} does not exist in the press with the ID {$newEntry['contextId']}." . PHP_EOL);
                    break;
                }
                $submissionId = $submission->getId();

                // If it is an older submission version, the arguments must be:
                // $submissionId/version/$publicationId/$representationId/$submissionFileId.
                $publicationId = null;
                if (in_array('version', $args)) {
                    if ($args[1] !== 'version' || !isset($args[2]) || !isset($args[3])) {
                        fwrite(STDERR, 'The following arguments are expected and not found: <submissionId>/version/<publicationId>/<publicationFormatId>/<fileId>.' . PHP_EOL);
                        break;
                    }
                    $publicationId = (int) $args[2];
                    $representationUrlPath = $args[3];
                    $submissionFileId = (int) $args[4];
                } else {
                    $representationUrlPath = $args[1];
                    $submissionFileId = (int) $args[2];
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
                $representationId = $submissionFile->getData('assocId');
                $publicationFormatDao = DAORegistry::getDAO('PublicationFormatDAO'); /** @var PublicationFormatDAO $publicationFormatDao */
                if (is_int($representationUrlPath) || ctype_digit($representationUrlPath)) {
                    // assume it is ID and not the URL path
                    if ($representationUrlPath != $representationId) {
                        fwrite(STDERR, "Submission file with the ID {$submissionFileId} does not belong to the publication format with ID {$representationUrlPath}." . PHP_EOL);
                        break;
                    }
                    $publicationFormat = $publicationFormatDao->getById($representationId, $publicationId);
                    if (!$publicationFormat) {
                        fwrite(STDERR, "Publication format with the ID {$representationUrlPath} does not exist in the submission with the ID {$submissionId}." . PHP_EOL);
                        break;
                    }
                } else {
                    // We need to get the publication in order to be able to get publication format by URL path
                    $publications = $submission->getData('publications');
                    if (isset($publicationId)) {
                        $publication = $publications->first(function ($value, $key) use ($publicationId) {
                            return $value->getId() == $publicationId;
                        });
                        if (!$publication) {
                            fwrite(STDERR, "Publication (submission version) with the ID {$publicationId} does not exist in the submission with the ID {$submissionId}." . PHP_EOL);
                            break;
                        }
                        $publicationFormat = $publicationFormatDao->getByBestId($representationUrlPath, $publication->getId());
                        if (!$publicationFormat) {
                            fwrite(STDERR, "Publication format with the URL path {$representationUrlPath} does not exist in the publication (submission version) with the ID {$publicationId}." . PHP_EOL);
                            break;
                        }
                        if ($representationId != $publicationFormat->getId()) {
                            fwrite(STDERR, "Submission file with the ID {$submissionFileId} does not exist in the publication (submission version) with the ID {$publicationId}." . PHP_EOL);
                            break;
                        }
                    } else {
                        // We cannot assume that this is the current publication,
                        // because the log entry can be long time ago, and
                        // since then there could be new submission versions created,
                        // so take the first publication found with
                        // publication format with the given representationUrlPath
                        // that contains the given submission file.
                        // (Different publications can contain the same publication format URL path.)
                        $publicationFormat = null;
                        foreach ($publications as $publication) {
                            foreach ($publication->getData('publicationFormats') as $possiblePublicationFormat) {
                                if ($possiblePublicationFormat->getBestId() == $representationUrlPath) {
                                    if ($representationId == $possiblePublicationFormat->getId()) {
                                        $publicationFormat = $possiblePublicationFormat;
                                        break 2;
                                    }
                                }
                            }
                        }
                        if (!$publicationFormat) {
                            fwrite(STDERR, "Publication format with the URL path {$representationUrlPath} does not exist in the submission with the ID {$submissionId}." . PHP_EOL);
                            break;
                        }
                    }
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
                break;

            case Application::ASSOC_TYPE_SERIES:
                if (!isset($args[0])) {
                    fwrite(STDERR, 'Missing series path URL parameter.' . PHP_EOL);
                    break;
                }
                $seriesPath = $args[0];
                $series = Repo::section()->getByPath($seriesPath, $newEntry['contextId']);
                if (!$series) {
                    fwrite(STDERR, "Series with the path {$seriesPath} does not exist in the press with the ID {$newEntry['contextId']}." . PHP_EOL);
                    break;
                }
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
                break;

            case Application::ASSOC_TYPE_SUBMISSION:
                if (!isset($args[0])) {
                    fwrite(STDERR, 'Missing submission ID URL parameter.' . PHP_EOL);
                    break;
                }

                $submission = Repo::submission()->getByBestId($args[0], $newEntry['contextId']);
                if (!$submission) {
                    fwrite(STDERR, "Submission with the URL path or ID {$args[0]} does not exist in the server with the ID {$newEntry['contextId']}." . PHP_EOL);
                    break;
                }
                $submissionId = $submission->getId();

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

                $submission = Repo::submission()->getByBestId($args[0], $newEntry['contextId']);
                if (!$submission) {
                    fwrite(STDERR, "Submission with the URL path or ID {$args[0]} does not exist in the server with the ID {$newEntry['contextId']}." . PHP_EOL);
                    break;
                }
                $submissionId = $submission->getId();

                // If it is an older submission version, the arguments must be:
                // $submissionId/version/$publicationId/$representationId/$submissionFileId.
                $publicationId = null;
                if (in_array('version', $args)) {
                    if ($args[1] !== 'version' || !isset($args[2]) || !isset($args[3])) {
                        fwrite(STDERR, 'The following arguments are expected and not found: <submissionId>/version/<publicationId>/<galleyId>/<fileId>.' . PHP_EOL);
                        break;
                    }
                    $publicationId = (int) $args[2];
                    $representationUrlPath = $args[3];
                    $submissionFileId = (int) $args[4];
                } else {
                    $representationUrlPath = $args[1];
                    $submissionFileId = (int) $args[2];
                }

                $submissionFile = Repo::submissionFile()->get($submissionFileId, $submissionId);
                if (!$submissionFile) {
                    fwrite(STDERR, "Submission file with the ID {$submissionFileId} does not exist in the submission with the ID {$submissionId}." . PHP_EOL);
                    break;
                }

                // Find the galley and representation ID
                $representationId = $galley = null;
                if (is_int($representationUrlPath) || ctype_digit($representationUrlPath)) {
                    // assume it is ID and not the URL path
                    $representationId = (int) $representationUrlPath;
                    $galley = Repo::galley()->get($representationId);
                    if (!$galley) {
                        fwrite(STDERR, "Galley with the ID {$representationUrlPath} does not exist." . PHP_EOL);
                        break;
                    }
                } else {
                    // We need to get the publication in order to be able to get galley by URL path
                    $publications = $submission->getData('publications');
                    if (isset($publicationId)) {
                        $publication = $publications->first(function ($value, $key) use ($publicationId) {
                            return $value->getId() == $publicationId;
                        });
                        if (!$publication) {
                            fwrite(STDERR, "Publication (submission version) with the ID {$publicationId} does not exist in the submission with the ID {$submissionId}." . PHP_EOL);
                            break;
                        }
                        $galley = Repo::galley()->getByUrlPath($representationUrlPath, $publication);
                        if (!$galley) {
                            fwrite(STDERR, "Galley with the URL path {$representationUrlPath} does not exist in the publication (submission version) with the ID {$publicationId}." . PHP_EOL);
                            break;
                        }
                        $representationId = $galley->getId();
                    } else {
                        // We cannot assume that this is the current publication,
                        // because the log entry can be long time ago, and
                        // since then there could be new submission versions created,
                        // so take the first publication found with
                        // galley with the given representationUrlPath
                        // that contain the given submission file.
                        // (Different publications can contain the same galley URL path.)
                        foreach ($publications as $publication) {
                            foreach ($publication->getData('galleys') as $publicationGalley) {
                                if ($publicationGalley->getBestGalleyId() == $representationUrlPath) {
                                    if ($publicationGalley->getData('submissionFileId') == $submissionFileId) {
                                        $galley = $publicationGalley;
                                        $representationId = $publicationGalley->getId();
                                        break 2;
                                    }
                                }
                            }
                        }
                        if (!$representationId) {
                            fwrite(STDERR, "Galley with the URL path {$representationUrlPath} does not exist in the submission with the ID {$submissionId}." . PHP_EOL);
                            break;
                        }
                    }
                }
                if ($galley->getData('submissionFileId') != $submissionFileId) {
                    // This check is e.g. when representation ID (and not URL path) and submissionFileId are given as arguments
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

$tool = new ConvertApacheAccessLogFile($argv ?? []);
$tool->execute();
