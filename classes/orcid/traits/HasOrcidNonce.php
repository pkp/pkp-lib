<?php


/**
 * @file classes/orcid/traits/HasOrcidNonce.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class HasOrcidNonce
 *
 * @brief Groups common ORCID authentication nonce operations
 */

namespace PKP\orcid\traits;

use Illuminate\Support\Facades\Cache;

trait HasOrcidNonce
{
    /**
     * Gets cache key used for handling nonce:invitationId key, value pair.
     */
    private function invitationNonceKey(string $nonce): string
    {
        return "orcidInvitationNonce.{$nonce}";
    }

    /**
     * Gets cache key used for handling invitationId:nonce key, value pair.
     */
    private function invitationNonceForKey(string $invitationId): string
    {
        return "orcidInvitationNonceFor.{$invitationId}";
    }

    /**
     * Retrieve invitation ID by nonce value from the cache
     */
    protected function getCachedInvitationId(string $nonce): ?string
    {
        if (empty($nonce)) {
            return null;
        }
        return Cache::get($this->invitationNonceKey($nonce));
    }

    /**
     * Retrieve stored nonce by invitation value from the cache
     */
    protected function getCachedNonce(string $invitationId): ?string
    {
        if (empty($invitationId)) {
            return null;
        }
        return Cache::get($this->invitationNonceForKey($invitationId));
    }

    /**
     * Writes ORCID nonce and invitation ID to the cache for later verification
     */
    protected function setCachedValues(string $nonce, string $invitationId): void
    {
        Cache::set($this->invitationNonceKey($nonce), $invitationId, now()->addHour());
        Cache::set($this->invitationNonceForKey($invitationId), $nonce, now()->addHour());
    }

    /**
     * Removes nonce and invitation ID values from the cache.
     */
    protected function forgetCachedValues(string $nonce, string $invitationId): void
    {
        Cache::forget($this->invitationNonceKey($nonce));
        Cache::forget($this->invitationNonceForKey($invitationId));
    }
}
