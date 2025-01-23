<?php

/**
 * @file classes/orcid/actions/VerifyIdentityWithOrcid.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2000-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class VerifyIdentityWithOrcid
 *
 * @brief Verify ORCID association triggered via an external stimulus (e.g. an email request)
 */

namespace PKP\orcid\actions;

use APP\author\Author;
use APP\core\Application;
use APP\core\Request;
use APP\facades\Repo;
use APP\jobs\orcid\DepositOrcidReview;
use APP\orcid\actions\SendReviewToOrcid;
use APP\orcid\actions\SendSubmissionToOrcid;
use APP\template\TemplateManager;
use Carbon\Carbon;
use GuzzleHttp\Exception\GuzzleException;
use PKP\context\Context;
use PKP\identity\Identity;
use PKP\orcid\enums\OrcidDepositType;
use PKP\orcid\OrcidManager;
use PKP\submission\PKPSubmission;
use PKP\user\User;

class VerifyIdentityWithOrcid
{
    private Context $context;
    private array $templateVarsToSet = [];

    public function __construct(
        private Identity $identity,
        private Request $request,
        private OrcidDepositType $depositType
    )
    {
        $this->context = $this->request->getContext();
    }

    /**
     * Execute the action.
     *
     * NB: This method returns itself so template variables can be added to a template via `updateTemplateMgrVars()` below.
     */
    public function execute(): self
    {
        if (!OrcidManager::isEnabled($this->context)) {
            return $this;
        }

        $response = $this->getHandshakeResponse();
        if ($response !== null) {
            $this->handleResponseErrors($response);
            $this->setIdentityData($response);
            $this->saveIdentityData();
            $this->depositOrcidItem();
        }

        // Does not indicate auth was a failure, but if verifySuccess was not set to true above,
        // the nature of the failure will be auth related . If verifySuccess is set to true above, an `else` branch
        // in the template that covers various failure/error messages will never be reached.
        $this->addTemplateVar('authFailure', true);

        return $this;
    }

    /**
     * Takes template variables for frontend display from OAuth process and assigns them to the TemplateManager.
     *
     * @param TemplateManager $templateMgr The template manager to which the variable should be set
     */
    public function updateTemplateMgrVars(TemplateManager &$templateMgr): void
    {
        foreach ($this->templateVarsToSet as $key => $value) {
            $templateMgr->assign($key, $value);
        }
    }

    /**
     * Returns the decoded JSON contents of the OAuth handshake response
     */
    private function getHandshakeResponse(): ?array
    {
        // Fetch access token
        $oauthTokenUrl = OrcidManager::getApiPath($this->context) . OrcidManager::OAUTH_TOKEN_URL;

        $httpClient = Application::get()->getHttpClient();
        $headers = ['Accept' => 'application/json'];
        $postData = [
             'code' => $this->request->getUserVar('code'),
            'grant_type' => 'authorization_code',
            'client_id' => OrcidManager::getClientId($this->context),
            'client_secret' => OrcidManager::getClientSecret($this->context)
        ];

        OrcidManager::logInfo('POST ' . $oauthTokenUrl);
        OrcidManager::logInfo('Request headers: ' . var_export($headers, true));
        OrcidManager::logInfo('Request body: ' . http_build_query($postData));

        try {
            $response = $httpClient->request(
                'POST',
                $oauthTokenUrl,
                [
                    'headers' => $headers,
                    'form_params' => $postData,
                    'allow_redirects' => ['strict' => true],
                ],
            );

            if ($response->getStatusCode() !== 200) {
                OrcidManager::logError('VerifyIdentityWithOrcid::getHandshakeResponse - unexpected response: ' . $response->getStatusCode());
                $this->addTemplateVar('authFailure', true);
            }

            $this->addTemplateVar('verifySuccess', true);
            $this->addTemplateVar('orcidIcon', OrcidManager::getIcon());

            return json_decode($response->getBody(), true);
        } catch (GuzzleException $exception) {
            OrcidManager::logError('Publication fail: ' . $exception->getMessage());
            $this->addTemplateVar('orcidAPIError', $exception->getMessage());
        }

        return null;
    }

    /**
     * Handles setting relevant ORCID data to user or author.
     *
     * @param array $response
     * @return void
     */
    private function setIdentityData(array $response): void
    {
        $orcidUri = OrcidManager::getOrcidUrl($this->context) . $response['orcid'];
        $this->addTemplateVar('orcid', $orcidUri);

        $this->identity->setOrcid($orcidUri);
        $this->identity->setOrcidVerified(true);

        // Save the access token
        $orcidAccessExpiresOn = Carbon::now();
        // expires_in field from the response contains the lifetime in seconds of the token
        // See https://members.orcid.org/api/get-oauthtoken
        $orcidAccessExpiresOn->addSeconds($response['expires_in']);
        // remove the access denied marker, because now the access was granted
        $this->identity->setData('orcidAccessDenied', null);
        $this->identity->setData('orcidAccessToken', $response['access_token']);
        $this->identity->setData('orcidAccessScope', $response['scope']);
        $this->identity->setData('orcidRefreshToken', $response['refresh_token']);
        $this->identity->setData('orcidAccessExpiresOn', $orcidAccessExpiresOn->toDateTimeString());


        if ($this->identity instanceof Author) {
            $this->identity->setData('orcidEmailToken', null);
        }
    }

    /**
     * Saves identity information using the correct DAO (`Author` or `User`).
     *
     * @throws \Exception
     */
    private function saveIdentityData(): void
    {
        if ($this->identity instanceof Author) {
            Repo::author()->dao->update($this->identity);
        } else if ($this->identity instanceof User) {
            Repo::user()->dao->update($this->identity);
        } else {
            throw new \Exception('Identity must be an instance of Author or User');
        }
    }

    /**
     * Stores key, value pair to be assigned to the TemplateManager for display in frontend UI.
     */
    protected function addTemplateVar(string $key, mixed $value): void
    {
        $this->templateVarsToSet[$key] = $value;
    }

    /**
     * Dispatches job to deposit either ORCID work or review
     */
    private function depositOrcidItem(): void
    {
        if (!OrcidManager::isMemberApiEnabled($this->context)) {
            return;
        }

        if ($this->depositType === OrcidDepositType::WORK) {
            $publicationId = $this->request->getUserVar('state');
            $publication = Repo::publication()->get($publicationId);

            if ($publication->getData('status') == PKPSubmission::STATUS_PUBLISHED) {
                (new SendSubmissionToOrcid($publication, $this->context))->execute();
                $this->addTemplateVar('sendSubmissionSuccess', true);
            } else {
                $this->addTemplateVar('submissionNotPublished', true);
            }
        } else if ($this->depositType === OrcidDepositType::REVIEW) {
            $reviewAssignmentId = $this->request->getUserVar('itemId');
            (new SendReviewToOrcid($reviewAssignmentId))->execute();
            $this->addTemplateVar('sendSubmissionSuccess', true);
        }
    }

    /**
     * Handles logging and template variable updates for OAuth response errors.
     */
    private function handleResponseErrors(array $response): void
    {
        OrcidManager::logInfo('Response body: ' . print_r($response, true));
        if (($response['error'] ?? null) === 'invalid_grant') {
            OrcidManager::logError('Authorization code invalid, maybe already used');
            $this->addTemplateVar('authFailure', true);
        }
        if (isset($response['error'])) {
            OrcidManager::logError('Invalid ORCID response: ' . $response['error']);
            $this->addTemplateVar('authFailure', true);
        }

        $orcidUri = OrcidManager::getOrcidUrl($this->context) . $response['orcid'];
        if (!empty($this->identity->getOrcid()) && $orcidUri !== $this->identity->getOrcid()) {
            $this->addTemplateVar('duplicateOrcid', true);
        }
    }
}
