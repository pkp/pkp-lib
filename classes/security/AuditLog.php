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
use Psr\Log\LogLevel;

class AuditLog
{
    /**
     * Write a security audit entry.
     *
     * @param string $event     Non localised audit log/event message
     * @param string $level     PSR-3 level log level
     * @param array  $context   Structured fields to pass as extra log context data which support
     *                          more details data for audit purpose
     */
    public static function log(string $event, string $level = LogLevel::INFO, array $context = []): void
    {
        $context = static::withRequestContext($context);
        $context['category'] ??= explode('.', $event, 2)[0];

        Log::log($level, $event, $context);
    }

    /**
     * Verify/Inject the "minimum log content" fields (Who / Where / When)
     * into every audit entry.
     */
    protected static function withRequestContext(array $context): array
    {
        if (!array_key_exists('occurredAt', $context)) {
            $context['occurredAt'] = now()->toIso8601String();
        }

        // Running in CLI mode has no reliable way to determine loggedInUserId, IP, user agent,
        // context id or request url unless passed explicitly. The user id especially must not
        // be counted: CommandLineTool silently defaults the registry user to the first admin,
        // which would falsely attribute cron/CLI actions. A null actor = system/automated event.
        if (app()->runningInConsole()) {
            $context['runtime'] = 'CLI';
            unset($context['loggedInUserId']);

            return $context;
        }

        $request = Application::get()->getRequest();

        // Who: the real human who authenticated. When impersonating, loggedInAs() returns the
        // original (impersonator) user, so loggedInUserId is always the acting human.
        $impersonatorId = Validation::loggedInAs();
        if (!array_key_exists('loggedInUserId', $context)) {
            $context['loggedInUserId'] = $impersonatorId ?? $request->getUser()?->getId();
        }

        // If the session is impersonating another user, record the account being operated as.
        // Present only while impersonating, so the key's presence itself flags an impersonated action.
        if ($impersonatorId !== null && !array_key_exists('impersonatedAsUserId', $context)) {
            $context['impersonatedAsUserId'] = $request->getUser()?->getId();
        }

        if (!array_key_exists('ip', $context)) {
            $context['ip'] = $request->getRemoteAddr();
        }

        if (!array_key_exists('userAgent', $context)) {
            $context['userAgent'] = $request->getUserAgent();
        }

        // Where: which context (journal/serve/press) and URL the event originated from.
        if (!array_key_exists('contextId', $context)) {
            $context['contextId'] = $request->getContext()?->getId();
        }

        // getRequestUrl() is scheme+host+path only (no query string / POST body),
        // so it cannot leak query-carried secrets such as the password-reset hash.
        if (!array_key_exists('requestUrl', $context)) {
            $context['requestUrl'] = $request->getRequestUrl();
        }

        return $context;
    }
}
