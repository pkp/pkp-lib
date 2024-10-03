<?php

/**
 * @file jobs/orcid/DepositOrcidSubmission.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DepositOrcidSubmission
 *
 * @ingroup jobs
 *
 * @brief Job to deposit ORCID work information to an authorized user's ORCID profile
 */

namespace PKP\jobs\orcid;

use APP\author\Author;
use APP\core\Application;
use APP\facades\Repo;
use GuzzleHttp\Exception\ClientException;
use PKP\config\Config;
use PKP\context\Context;
use PKP\jobs\BaseJob;
use PKP\orcid\OrcidManager;

class DepositOrcidSubmission extends BaseJob
{
    public function __construct(
        private Author $author,
        private Context $context,
        private array $orcidWork,
        private string $authorOrcid
    ) {
        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    public function handle(): void
    {
        // If the application is set to sandbox mode, it will not reach out to external services
        if (Config::getVar('general', 'sandbox', false)) {
            $this->fail('Application is set to sandbox mode and will not interact with the ORCID service');
            return;
        }

        $uri = OrcidManager::getApiPath($this->context) . OrcidManager::ORCID_API_VERSION_URL . $this->authorOrcid . '/' . OrcidManager::ORCID_WORK_URL;
        $method = 'POST';

        if ($putCode = $this->author->getData('orcidWorkPutCode')) {
            // Submission has already been sent to ORCID. Use PUT to update meta data
            $uri .= '/' . $putCode;
            $method = 'PUT';
            $orcidWork['put-code'] = $putCode;
        } else {
            // Remove put-code from body because the work has not yet been sent
            unset($this->orcidWork['put-code']);
        }

        $headers = [
            'Content-type: application/vnd.orcid+json',
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $this->author->getData('orcidAccessToken')
        ];

        OrcidManager::logInfo("{$method} {$uri}");
        OrcidManager::logInfo('Header: ' . var_export($headers, true));

        $httpClient = Application::get()->getHttpClient();
        try {
            $response = $httpClient->request(
                $method,
                $uri,
                [
                    'headers' => $headers,
                    'json' => $this->orcidWork,
                ]
            );
        } catch (ClientException $exception) {
            $reason = $exception->getResponse()->getBody();
            OrcidManager::logError("Publication fail: {$reason}");

            $this->fail($exception);
        }
        $httpStatus = $response->getStatusCode();
        OrcidManager::logInfo("Response status: {$httpStatus}");
        $responseHeaders = $response->getHeaders();

        switch ($httpStatus) {
            case 200:
                // Work updated
                OrcidManager::logInfo("Work updated in profile, putCode: {$putCode}");
                break;
            case 201:
                $location = $responseHeaders['Location'][0];
                // Extract the ORCID work put code for updates/deletion.
                $putCode = intval(basename(parse_url($location, PHP_URL_PATH)));
                OrcidManager::logInfo("Work added to profile, putCode: {$putCode}");
                $this->author->setData('orcidWorkPutCode', $putCode);
                Repo::author()->dao->update($this->author);
                break;
            case 401:
                // invalid access token, token was revoked
                $error = json_decode($response->getBody(), true);
                if ($error['error'] === 'invalid_token') {
                    OrcidManager::logError($error['error_description'] . ', deleting orcidAccessToken from author');
                    OrcidManager::removeOrcidAccessToken($this->author);
                }
                break;
            case 403:
                OrcidManager::logError('Work update forbidden: ' . $response->getBody());
                break;
            case 404:
                // a work has been deleted from a ORCID record. putCode is no longer valid.
                if ($method === 'PUT') {
                    OrcidManager::logError('Work deleted from ORCID record, deleting putCode form author');
                    $this->author->setData('orcidWorkPutCode', null);
                    Repo::author()->dao->update($this->author);
                } else {
                    OrcidManager::logError("Unexpected status {$httpStatus} response, body: " . $response->getBody());
                }
                break;
            case 409:
                OrcidManager::logError('Work already added to profile, response body: ' . $response->getBody());
                break;
            default:
                OrcidManager::logError("Unexpected status {$httpStatus} response, body: " . $response->getBody());
        }
    }
}
