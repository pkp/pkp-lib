<?php

/**
 * @file classes/observers/events/PKPUsageEvent.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPUsageEvent
 * @ingroup observers_events
 *
 * @brief Usage event.
 *
 */

namespace PKP\observers\events;

use APP\core\Application;
use APP\statistics\StatisticsHelper;
use Illuminate\Foundation\Events\Dispatchable;
use PKP\config\Config;
use PKP\core\Core;
use PKP\db\DAORegistry;

class PKPUsageEvent
{
    use Dispatchable;

    /** Current time */
    public string $time;
    /** User IP adress */
    public string $ip;
    /** Canonical URL for the pub object */
    public string $canonicalUrl;
    /** Context ID */
    public int $contextId;
    /** Submission ID */
    public ?int $submissionId;
    /** Representation (galley or publication format) ID */
    public ?int $representationId;
    /** Viewed/downloaded object, one of the Application::ASSOC_TYPE_... constants */
    public int $assocType;
    /** Viewed/downloaded object ID */
    public int $assocId;
    /** COUNTER file type, one of the StatisticsHelper::STATISTICS_FILE_TYPE_... constants*/
    public ?int $fileType;
    /** User's UserAgent */
    public string $userAgent;
    /** Application's complete version string */
    public string $version;

    /**
     * Create a new usage event instance.
     */
    public function __construct(int $assocType, int $assocId, int $contextId, int $submissionId = null, int $representationId = null, string $mimetype = null)
    {
        $application = Application::get();
        $request = $application->getRequest();

        $time = Core::getCurrentDate();

        $ip = $request->getRemoteAddr();

        $canonicalUrlPage = $canonicalUrlOp = null;
        $canonicalUrlParams = [];

        switch ($assocType) {
            case Application::ASSOC_TYPE_SUBMISSION_FILE_COUNTER_OTHER:
            case Application::ASSOC_TYPE_SUBMISSION_FILE:
                $canonicalUrlOp = 'download';
                $canonicalUrlParams = [$submissionId];
                $router = $request->getRouter(); /** @var PageRouter $router */
                $op = $router->getRequestedOp($request);
                $args = $router->getRequestedArgs($request);
                if ($op == 'download' && count($args) > 1) {
                    if ($args[1] == 'version' && count($args) == 5) {
                        $publicationId = (int) $args[2];
                        $canonicalUrlParams[] = 'version';
                        $canonicalUrlParams[] = $publicationId;
                    }
                }
                $canonicalUrlParams[] = $representationId;
                $canonicalUrlParams[] = $assocId;
                break;
            case Application::ASSOC_TYPE_SUBMISSION:
                $canonicalUrlOp = 'view';
                if ($application->getName() == 'omp') {
                    $canonicalUrlOp = 'book';
                }
                $canonicalUrlParams = [$submissionId];
                $router = $request->getRouter(); /** @var PageRouter $router */
                $op = $router->getRequestedOp($request);
                $args = $router->getRequestedArgs($request);
                if ($op == $canonicalUrlOp && count($args) > 1) {
                    if ($args[1] == 'version' && count($args) == 3) {
                        $publicationId = (int) $args[2];
                        $canonicalUrlParams[] = 'version';
                        $canonicalUrlParams[] = $publicationId;
                    }
                }
                break;
            case Application::getContextAssocType():
                $canonicalUrlOp = '';
                $canonicalUrlPage = 'index';
                break;
        }
        $canonicalUrl = $this->getCanonicalUrl($request, $canonicalUrlPage, $canonicalUrlOp, $canonicalUrlParams);

        $fileType = null;
        if (isset($mimetype)) {
            $fileType = $this->getDocumentType($mimetype);
        }

        $userAgent = $request->getUserAgent();

        // Retrieve the currently installed version
        $versionDao = DAORegistry::getDAO('VersionDAO'); /** @var VersionDAO $versionDao */
        $version = $versionDao->getCurrentVersion();
        $versionString = $version->getVersionString();

        $this->time = $time;
        $this->ip = $ip;
        $this->canonicalUrl = $canonicalUrl;
        $this->contextId = $contextId;
        $this->submissionId = $submissionId;
        $this->representationId = $representationId;
        $this->assocType = $assocType;
        $this->assocId = $assocId;
        $this->fileType = $fileType;
        $this->userAgent = $userAgent;
        $this->version = $versionString;
    }

    /**
     * Get the canonical URL for the usage object
     */
    protected function getCanonicalUrl(\APP\core\Request $request, string $canonicalUrlPage = null, string $canonicalUrlOp = null, array $canonicalUrlParams = null): string
    {
        $router = $request->getRouter(); /** @var PageRouter $router */
        $context = $router->getContext($request);

        if (!isset($canonicalUrlPage)) {
            $canonicalUrlPage = $router->getRequestedPage($request);
        }
        if (!isset($canonicalUrlOp)) {
            $canonicalUrlOp = $router->getRequestedOp($request);
        }
        if (!isset($canonicalUrlParams)) {
            $canonicalUrlParams = $router->getRequestedArgs($request);
        }

        $canonicalUrl = $router->url(
            $request,
            null,
            $canonicalUrlPage,
            $canonicalUrlOp,
            $canonicalUrlParams
        );

        // Make sure we log the server name and not aliases.
        $configBaseUrl = Config::getVar('general', 'base_url');
        $requestBaseUrl = $request->getBaseUrl();
        if ($requestBaseUrl !== $configBaseUrl) {
            // Make sure it's not an url override (no alias on that case).
            if (!in_array($requestBaseUrl, Config::getContextBaseUrls()) &&
                    $requestBaseUrl !== Config::getVar('general', 'base_url[index]')) {
                // Alias found, replace it by base_url from config file.
                // Make sure we use the correct base url override value for the context, if any.
                $baseUrlReplacement = Config::getVar('general', 'base_url[' . $context->getPath() . ']');
                if (!$baseUrlReplacement) {
                    $baseUrlReplacement = $configBaseUrl;
                }
                $canonicalUrl = str_replace($requestBaseUrl, $baseUrlReplacement, $canonicalUrl);
            }
        }
        return $canonicalUrl;
    }

    /**
    * Get document type based on the mimetype
    * The mimetypes considered here are subset of those used in PKPFileService::getDocumentType()
    *
    * @return int One of the StatisticsHelper::STATISTICS_FILE_TYPE_ constants
    */
    private function getDocumentType(string $mimetype): int
    {
        switch ($mimetype) {
           case 'application/pdf':
           case 'application/x-pdf':
           case 'text/pdf':
           case 'text/x-pdf':
               return StatisticsHelper::STATISTICS_FILE_TYPE_PDF;
           case 'application/msword':
           case 'application/word':
           case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
               return StatisticsHelper::STATISTICS_FILE_TYPE_DOC;
           case 'text/html':
               return StatisticsHelper::STATISTICS_FILE_TYPE_HTML;
           default:
               return StatisticsHelper::STATISTICS_FILE_TYPE_OTHER;
       }
    }
}
