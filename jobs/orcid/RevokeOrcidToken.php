<?php

/**
 * @file jobs/orcid/RevokeOrcidToken.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DepositOrcidSubmission
 *
 * @ingroup jobs
 *
 * @brief Job to revoke a user's ORCID access token for the application.
 */

namespace pkp\jobs\orcid;

use APP\core\Application;
use GuzzleHttp\Exception\ClientException;
use PKP\context\Context;
use PKP\identity\Identity;
use PKP\jobs\BaseJob;
use PKP\orcid\OrcidManager;
use PKP\user\User;

class RevokeOrcidToken extends BaseJob
{
    public function __construct(
        private readonly Context  $context,
        private readonly Identity $identity
    ) {
        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    public function handle(): void
    {
        $token = $this->identity->getData('orcidAccessToken');
        $httpClient = Application::get()->getHttpClient();
        $headers = ['Accept' => 'application/json'];

        $postData = [
            'token' => $token,
            'client_id' => OrcidManager::getClientId($this->context),
            'client_secret' => OrcidManager::getClientSecret($this->context)
        ];

        try {
            $httpClient->request(
                'POST',
                OrcidManager::getTokenRevocationUrl(),
                [
                    'headers' => $headers,
                    'form_params' => $postData,
                ],
            );

            $identityTypeName = $this->identity instanceof User ? 'User' : 'Author';
            OrcidManager::logInfo("Token revoked for {$identityTypeName}, with ID: " . $this->identity->getId());
        } catch (ClientException $exception) {
            $httpStatus = $exception->getCode();
            $error = json_decode($exception->getResponse()->getBody(), true);
            OrcidManager::logError("ORCID token revocation failed with status {$httpStatus}. Error: " . $error['error_description']);

            $this->fail($exception);

        }
    }
}
