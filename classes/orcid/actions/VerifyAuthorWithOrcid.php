<?php

/**
 * @file classes/orcid/actions/VerifyAuthorWithOrcid.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class VerifyAuthorWithOrcid
 *
 * @brief Verify ORCID association triggered via an external stimulus (e.g. an email request)
 */

namespace PKP\orcid\actions;

use APP\author\Author;
use APP\core\Application;
use APP\core\Request;
use APP\facades\Repo;
use APP\orcid\actions\SendSubmissionToOrcid;
use APP\template\TemplateManager;
use Carbon\Carbon;
use GuzzleHttp\Exception\GuzzleException;
use PKP\orcid\OrcidManager;
use PKP\submission\PKPSubmission;

class VerifyAuthorWithOrcid
{
    public function __construct(
        private Author $author,
        private Request $request,
        private array $templateVarsToSet = []
    ) {
    }

    /**
     * Completes ORCID OAuth flow and retrieves the verified ORCID.
     *
     * Also sets template variables for frontend display purposes.
     */
    public function execute(): self
    {
        $context = $this->request->getContext();

        // Fetch the access token
        $oauthTokenUrl = OrcidManager::getApiPath($context) . OrcidManager::OAUTH_TOKEN_URL;

        $httpClient = Application::get()->getHttpClient();
        $headers = ['Accept' => 'application/json'];
        $postData = [
            'code' => $this->request->getUserVar('code'),
            'grant_type' => 'authorization_code',
            'client_id' => OrcidManager::getClientId($context),
            'client_secret' => OrcidManager::getClientSecret($context)
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
                OrcidManager::logError('VerifyAuthorWithOrcid::execute - unexpected response: ' . $response->getStatusCode());
                $this->addTemplateVar('authFailure', true);
            }
            $results = json_decode($response->getBody(), true);

            // Check for errors
            OrcidManager::logInfo('Response body: ' . print_r($results, true));
            if (($results['error'] ?? null) === 'invalid_grant') {
                OrcidManager::logError('Authorization code invalid, maybe already used');
                $this->addTemplateVar('authFailure', true);
            }
            if (isset($results['error'])) {
                OrcidManager::logError('Invalid ORCID response: ' . $results['error']);
                $this->addTemplateVar('authFailure', true);
            }

            // Check for duplicate ORCID for author
            $orcidUri = OrcidManager::getOrcidUrl($context) . $results['orcid'];
            if (!empty($this->author->getOrcid()) && $orcidUri !== $this->author->getOrcid()) {
                $this->addTemplateVar('duplicateOrcid', true);
            }
            $this->addTemplateVar('orcid', $orcidUri);

            $this->author->setOrcid($orcidUri);
            $this->author->setOrcidVerified(true);
            $this->author->setData('orcidVerificationRequested', null);
            if (OrcidManager::isSandbox($context)) {
                $this->author->setData('orcidEmailToken', null);
            }
            $this->setOrcidAccessData($orcidUri, $results);
            Repo::author()->dao->update($this->author);

            // Send member submissions to ORCID
            if (OrcidManager::isMemberApiEnabled($context)) {
                $publicationId = $this->request->getUserVar('state');
                $publication = Repo::publication()->get($publicationId);

                if ($publication->getData('status') == PKPSubmission::STATUS_PUBLISHED) {
                    (new SendSubmissionToOrcid($publication, $context))->execute();
                    $this->addTemplateVar('sendSubmissionSuccess', true);
                } else {
                    $this->addTemplateVar('submissionNotPublished', true);
                }
            }

            $this->addTemplateVar('verifySuccess', true);
            $this->addTemplateVar('orcidIcon', OrcidManager::getIcon());
        } catch (GuzzleException $exception) {
            OrcidManager::logError('Publication fail: ' . $exception->getMessage());
            $this->addTemplateVar('orcidAPIError', $exception->getMessage());
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
     * Helper to set ORCID and OAuth values to the author. NB: Does not save updated Author instance to the database.
     *
     * @param string $orcidUri Complete ORCID URL
     */
    private function setOrcidAccessData(string $orcidUri, array $results): void
    {
        // Save the access token
        $orcidAccessExpiresOn = Carbon::now();
        // expires_in field from the response contains the lifetime in seconds of the token
        // See https://members.orcid.org/api/get-oauthtoken
        $orcidAccessExpiresOn->addSeconds($results['expires_in']);
        $this->author->setOrcid($orcidUri);
        // remove the access denied marker, because now the access was granted
        $this->author->setData('orcidAccessDenied', null);
        $this->author->setData('orcidAccessToken', $results['access_token']);
        $this->author->setData('orcidAccessScope', $results['scope']);
        $this->author->setData('orcidRefreshToken', $results['refresh_token']);
        $this->author->setData('orcidAccessExpiresOn', $orcidAccessExpiresOn->toDateTimeString());

    }

    /**
     * Stores key, value pair to be assigned to the TemplateManager for display in frontend UI.
     */
    private function addTemplateVar(string $key, mixed $value): void
    {
        $this->templateVarsToSet[$key] = $value;
    }
}
