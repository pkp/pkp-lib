<?php

/**
 * @file classes/citation/externalServices/orcid/Inbound.php
 *
 * Copyright (c) 2025-2026 Simon Fraser University
 * Copyright (c) 2025-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Inbound
 *
 * @ingroup citation
 *
 * @brief Inbound class for Orcid
 */

namespace PKP\citation\externalServices\orcid;

use APP\core\Application;
use DateInterval;
use Illuminate\Support\Facades\Cache;
use PKP\citation\externalServices\ExternalServicesHelper;
use PKP\context\Context;
use PKP\orcid\OrcidManager;
use PKP\pid\Orcid;
use Throwable;

class Inbound
{
    /**
     * Maximum time (in seconds) to cache an ORCID access token.
     */
    protected const MAX_ACCESS_TOKEN_CACHE_SECONDS = 60 * 60 * 24;

    /** @var string The base URL for API requests. */
    public string $url = OrcidManager::ORCID_API_URL_PUBLIC . OrcidManager::ORCID_API_VERSION_URL;

    /** @var int Status code of external service response. */
    public int $statusCode = 200;

    /** @var int|null Retry-After value (in seconds) sent by the external service, if any. */
    public ?int $retryAfter = null;

    /** @var string Email address of journal contact. */
    public string $contactEmail = '';

    /** @var Context|null The journal, if any, whose ORCID integration settings (if configured) should be used for authenticated lookups. */
    protected ?Context $context;

    public function __construct(string $contactEmail, ?Context $context = null)
    {
        $this->contactEmail = $contactEmail;
        $this->context = $context;
    }

    /**
     * Convert to Author with mappings
     */
    public function getAuthor(array $author): ?array
    {
        $this->statusCode = 200;
        $this->retryAfter = null;

        $orcidId = urlencode(Orcid::removePrefix($author['orcid']));
        $url = $this->url . $orcidId;
        $options = ['headers' => ['mailto' => $this->contactEmail]];

        if ($this->context !== null && OrcidManager::isEnabled($this->context)) {
            $accessToken = $this->getAccessToken();
            if ($accessToken !== null) {
                $url = OrcidManager::getApiPath($this->context) . OrcidManager::ORCID_API_VERSION_URL . $orcidId;
                $options = ['headers' => ['Authorization' => 'Bearer ' . $accessToken]];
            }
        }

        $response = ExternalServicesHelper::apiRequest($url, $options, $this->retryAfter);

        if (is_int($response)) {
            $this->statusCode = $response;
            return null;
        }

        if (empty($response)) {
            return null;
        }

        foreach (Mapping::getAuthor() as $key => $mappedKey) {
            if (is_array($mappedKey)) {
                $newValue = ExternalServicesHelper::getValueFromArrayPath($response, $mappedKey);
            } else {
                $newValue = $response[$key];
            }
            $author[$key] = $newValue;
        }

        return $author;
    }

    /**
     * Clear the cached access token, e.g. after a 401 response indicates it's no longer valid.
     */
    public function clearCachedAccessToken(): void
    {
        if ($this->context !== null) {
            Cache::forget($this->getAccessTokenCacheKey());
        }
    }

    /**
     * Get (and cache) a client-credentials OAuth access token for reading public ORCID
     * records, using the journal's configured ORCID integration credentials. This grants
     * a better rate/quota tier (Public registered or Member) than anonymous access.
     */
    protected function getAccessToken(): ?string
    {
        $cacheKey = $this->getAccessTokenCacheKey();

        $cachedToken = Cache::get($cacheKey);
        if ($cachedToken !== null) {
            return $cachedToken;
        }

        try {
            $response = Application::get()->getHttpClient()->request(
                'POST',
                OrcidManager::getApiPath($this->context) . OrcidManager::OAUTH_TOKEN_URL,
                [
                    'form_params' => [
                        'grant_type' => 'client_credentials',
                        'client_id' => OrcidManager::getClientId($this->context),
                        'client_secret' => OrcidManager::getClientSecret($this->context),
                        'scope' => '/read-public',
                    ],
                    'headers' => ['Accept' => 'application/json'],
                ]
            );
        } catch (Throwable $e) {
            error_log(__METHOD__ . ' ' . $e->getMessage());
            return null;
        }

        $data = json_decode($response->getBody(), true);
        if (empty($data['access_token'])) {
            return null;
        }

        // MAX_ACCESS_TOKEN_CACHE_SECONDS always wins in practice — ORCID's real token expiry is on the order of decades.
        $ttlSeconds = min(self::MAX_ACCESS_TOKEN_CACHE_SECONDS, max(60, (int) ($data['expires_in'] ?? 0) - 60));
        Cache::put($cacheKey, $data['access_token'], DateInterval::createFromDateString("{$ttlSeconds} seconds"));

        return $data['access_token'];
    }

    /**
     * Cache key for this journal's ORCID client-credentials access token.
     */
    protected function getAccessTokenCacheKey(): string
    {
        return 'orcid-read-public-token-' . $this->context->getId();
    }
}
