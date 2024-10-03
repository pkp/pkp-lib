<?php

/**
 * @file classes/orcid/actions/AuthorizeUserData.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AuthorizeUserData
 *
 * @brief Authorize ORCID identifier for already logged-in OxS user.
 */

namespace PKP\orcid\actions;

use APP\core\Application;
use APP\core\Request;
use APP\facades\Repo;
use Carbon\Carbon;
use GuzzleHttp\Exception\ClientException;
use PKP\identity\Identity;
use PKP\orcid\OrcidManager;

class AuthorizeUserData
{
    public function __construct(
        private Request $request
    ) {
    }

    /**
     * Runs action and inserts data or reloads opening OJS tab when complete
     */
    public function execute(): void
    {
        $context = $this->request->getContext();
        $httpClient = Application::get()->getHttpClient();

        $errorMessages = [];

        // API Request: GetOAuth token and ORCID
        try {
            $tokenResponse = $httpClient->request(
                'POST',
                $url = OrcidManager::getApiPath($context) . OrcidManager::OAUTH_TOKEN_URL,
                [
                    'form_params' => [
                        'code' => $this->request->getUserVar('code'),
                        'grant_type' => 'authorization_code',
                        'client_id' => OrcidManager::getClientId($context),
                        'client_secret' => OrcidManager::getClientSecret($context),
                    ],
                    'headers' => ['Accept' => 'application/json'],
                ]
            );

            if ($tokenResponse->getStatusCode() !== 200) {
                error_log('ORCID token URL error: ' . $tokenResponse->getStatusCode() . ' (' . __FILE__ . ' line ' . __LINE__ . ', URL ' . $url . ')');
                $orcid = null;
                $orcidUri = null;
                $accessToken = null;
                $tokenData = [];
                $errorMessages[] = 'ORCID authorization failed: ORCID token URL error: ' . $tokenResponse->getStatusCode();
            } else {
                $tokenData = json_decode($tokenResponse->getBody(), true);
                $orcid = $tokenData['orcid'];
                $orcidUri = (OrcidManager::isSandbox($context) ? OrcidManager::ORCID_URL_SANDBOX : OrcidManager::ORCID_URL) . $orcid;
                $accessToken = $tokenData['access_token'];
            }
        } catch (ClientException $exception) {
            $reason = $exception->getResponse()->getBody();
            $message = "AuthorizeUserData::execute failed: {$reason}";
            OrcidManager::logError($message);
            $errorMessages[] = 'ORCID authorization failed: ' . $message;
        }

        switch ($this->request->getUserVar('targetOp')) {
            case 'register':
                // API request: get user profile (for names; email; etc)
                try {
                    $profileResponse = $httpClient->request(
                        'GET',
                        $url = OrcidManager::getApiPath($context) . OrcidManager::ORCID_API_VERSION_URL . urlencode($orcid) . '/' . OrcidManager::ORCID_PROFILE_URL,
                        [
                            'headers' => [
                                'Accept' => 'application/json',
                                'Authorization' => 'Bearer ' . $accessToken,
                            ],
                        ]
                    );
                    if ($profileResponse->getStatusCode() != 200) {
                        error_log('ORCID profile URL error: ' . $profileResponse->getStatusCode() . ' (' . __FILE__ . ' line ' . __LINE__ . ', URL ' . $url . ')');
                        $errorMessages[] = 'Failed to fetch ORCID profile data.';
                        $profileJson = null;
                    } else {
                        $profileJson = json_decode($profileResponse->getBody(), true);
                    }
                } catch (ClientException $exception) {
                    $errorMessages[] = 'Failed to fetch ORCID profile data.';
                    $profileJson = null;
                }


                // API request: get employments (for affiliation field)
                try {
                    $employmentsResponse = $httpClient->request(
                        'GET',
                        $url = OrcidManager::getApiPath($context) . OrcidManager::ORCID_API_VERSION_URL . urlencode($orcid) . '/' . OrcidManager::ORCID_EMPLOYMENTS_URL,
                        [
                            'headers' => [
                                'Accept' => 'application/json',
                                'Authorization' => 'Bearer ' . $accessToken,
                            ],
                        ]
                    );
                    if ($employmentsResponse->getStatusCode() != 200) {
                        $errorMessages[] = 'Failed to fetch ORCID employment data';
                        error_log('ORCID deployments URL error: ' . $employmentsResponse->getStatusCode() . ' (' . __FILE__ . ' line ' . __LINE__ . ', URL ' . $url . ')');
                        $employmentJson = null;
                    } else {
                        $employmentJson = json_decode($employmentsResponse->getBody(), true);
                    }
                } catch (ClientException $exception) {
                    $errorMessages[] = 'Failed to fetch ORCID employment data';
                    $employmentJson = null;
                }

                // Suppress errors for nonexistent array indexes
                echo '
                    <html><body><script type="text/javascript">' .
                    $this->renderFrontendErrorNotification($errorMessages) .
                    'opener.document.getElementById("givenName").value = ' . json_encode(@$profileJson['name']['given-names']['value']) . ';
                    opener.document.getElementById("familyName").value = ' . json_encode(@$profileJson['name']['family-name']['value']) . ';
                    opener.document.getElementById("email").value = ' . json_encode(@$profileJson['emails']['email'][0]['email']) . ';
                    opener.document.getElementById("country").value = ' . json_encode(@$profileJson['addresses']['address'][0]['country']['value']) . ';
                    opener.document.getElementById("affiliation").value = ' . json_encode(@$employmentJson['affiliation-group'][0]['summaries'][0]['employment-summary']['organization']['name']) . ';
                    opener.document.getElementById("orcid").value = ' . json_encode($orcidUri) . ';
                    opener.document.getElementById("connect-orcid-button").style.display = "none";
                    window.close();
                    </script></body></html>
                ';
                break;
            case 'profile':
                $user = $this->request->getUser();
                // Store the access token and other data for the user
                $user = $this->setOrcidData($user, $orcidUri, $tokenData);
                Repo::user()->edit($user, ['orcidAccessDenied', 'orcidAccessToken', 'orcidAccessScope', 'orcidRefreshToken', 'orcidAccessExpiresOn']);

                // Reload the public profile tab (incl. form)
                echo '
                    <html><body><script type="text/javascript">' .
                        $this->renderFrontendErrorNotification($errorMessages) .
                        'opener.$("#profileTabs").tabs("load", 0);
                        window.close();
                    </script></body></html>
                ';
                break;
            default:
                throw new \Exception('Invalid targetOp');
        }
    }

    /**
     * Display frontend UI notification with contents provided errors
     */
    private function renderFrontendErrorNotification(array $errorMessages): string
    {
        $returner = '';

        foreach ($errorMessages as $errorMessage) {
            $returner .= 'opener.pkp.eventBus.$emit("notify", "' . $errorMessage . '", "warning");';
        }

        return $returner;
    }

    /**
     * Sets ORCID token access data on the provided user or author
     */
    private function setOrcidData(Identity $userOrAuthor, string $orcidUri, array $orcidResponse): Identity
    {
        // Save the access token
        $orcidAccessExpiresOn = Carbon::now();
        // expires_in field from the response contains the lifetime in seconds of the token
        // See https://members.orcid.org/api/get-oauthtoken
        $orcidAccessExpiresOn->addSeconds($orcidResponse['expires_in']);
        $userOrAuthor->setOrcid($orcidUri);
        $userOrAuthor->setOrcidVerified(true);
        // remove the access denied marker, because now the access was granted
        $userOrAuthor->setData('orcidAccessDenied', null);
        $userOrAuthor->setData('orcidAccessToken', $orcidResponse['access_token']);
        $userOrAuthor->setData('orcidAccessScope', $orcidResponse['scope']);
        $userOrAuthor->setData('orcidRefreshToken', $orcidResponse['refresh_token']);
        $userOrAuthor->setData('orcidAccessExpiresOn', $orcidAccessExpiresOn->toDateTimeString());
        return $userOrAuthor;
    }
}
