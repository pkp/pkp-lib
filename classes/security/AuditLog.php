<?php

/**
 * @file classes/security/AuditLog.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AuditLog
 *
 * @brief Helper class to log audit logs to log file
 *
 */

namespace PKP\security;

use APP\core\Application;
use Illuminate\Support\Facades\Log;
use PKP\config\Config;
use Psr\Log\LogLevel;

class AuditLog
{
    /**
     * Write a security audit entry.
     *
     * @param AuditEvent $event     The security audit event being recorded.
     * @param string     $level     PSR-3 log level (see \Psr\Log\LogLevel).
     * @param array      $details   Event-specific fields to record alongside the entry which contains
     *                              the who/where/when envelope — userId, impersonatedAsUserId, ip, userAgent,
     *                              contextId, requestUrl and occurredAt which auto-filled value (e.g. pass userId => null
     *                              to record a system/automated action). The `event`, `message` and `status` fields
     *                              are always derived from $event and cannot be overridden. should be only
     *                              passed event-specific details and never pass sensitive information.
     */
    public static function log(AuditEvent $event, string $level = LogLevel::INFO, array $details = []): void
    {
        // Never audit logging when running unit tests
        if (app()->runningUnitTests()) {
            return;
        }

        // Security audit logging can be enabled per-installation (disabled by default).
        if (!Config::getVar('logs', 'log_audit', false)) {
            return;
        }

        $details = static::withRequestDetails($details);
        $details['event'] = $event->value;
        $details['status'] = $event->status();

        Log::log($level, $event->message(), $details);
    }

    /**
     * Verify/Inject the "minimum log content" fields (Who / Where / When)
     * into every audit entry.
     */
    protected static function withRequestDetails(array $details): array
    {
        // When: time at when the event happened
        $details['occurredAt'] ??= now()->toIso8601String();

        // Running in CLI mode has no reliable way to determine IP, user agent, context id or
        // request url unless passed explicitly. And can not have logged in user in CLI.
        if (app()->runningInConsole()) {
            $details['runtime'] = 'CLI';
            unset($details['userId']);

            return $details;
        }

        $request = Application::get()->getRequest();

        // Who: the user performing the action. When impersonating, loggedInAs() returns the
        // original (impersonator) user, so userId is always the acting user.
        $impersonatorId = Validation::loggedInAs();
        if (!array_key_exists('userId', $details)) {
            $details['userId'] = $impersonatorId ?? $request->getUser()?->getId();
        }

        // If the session is impersonating another user, record the account being operated as.
        // Present only while impersonating, so the key's presence itself flags an impersonated action.
        if ($impersonatorId !== null && !array_key_exists('impersonatedAsUserId', $details)) {
            $details['impersonatedAsUserId'] = $request->getUser()?->getId();
        }

        if (!array_key_exists('ip', $details)) {
            $details['ip'] = $request->getRemoteAddr();
        }

        if (!array_key_exists('userAgent', $details)) {
            $details['userAgent'] = $request->getUserAgent();
        }

        // Where: which context (journal/server/press) and URL the event originated from.
        if (!array_key_exists('contextId', $details)) {
            $details['contextId'] = $request->getContext()?->getId();
        }

        // getRequestUrl() is scheme+host+path only (no query string / POST body),
        // so it cannot leak query-carried secrets such as the password-reset hash.
        if (!array_key_exists('requestUrl', $details)) {
            $details['requestUrl'] = $request->getRequestUrl();
        }

        return $details;
    }
}
