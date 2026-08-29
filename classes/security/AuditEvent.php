<?php

/**
 * @file classes/security/AuditEvent.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AuditEvent
 *
 * @brief List of security audit events written through AuditLog.
 *
 * Mostly taken from OWASP Logging Vocabulary Cheat Sheet at
 * (https://cheatsheetseries.owasp.org/cheatsheets/Logging_Vocabulary_Cheat_Sheet.html):
 * a verbatim OWASP token where one closely matches, otherwise a token coined in the same
 * "prefix_object_action" grammar.
 */

namespace PKP\security;

enum AuditEvent: string
{
    // A. Authentication & session
    case AUTH_LOGIN = 'authn_login_success';
    case AUTH_LOGIN_FAILED = 'authn_login_fail';
    case AUTH_LOGIN_DISABLED = 'authn_login_lock';
    case AUTH_LOGOUT = 'session_logout';
    case AUTH_LOGOUT_IP_CHANGE = 'session_expired';
    case AUTH_RATE_LIMIT = 'excess_rate_limit_exceeded';
    case AUTH_PASSWORD_RESET_REQUEST = 'authn_password_reset_request';
    case AUTH_PASSWORD_RESET_COMPLETE = 'authn_password_reset';
    case AUTH_PASSWORD_RESET_INVALID = 'authn_password_reset_fail';
    case AUTH_API_TOKEN_INVALID = 'authn_token_invalid';

    // B. Impersonation & re-authentication
    case SESSION_IMPERSONATE_START = 'authz_impersonate_start';
    case SESSION_IMPERSONATE_END = 'authz_impersonate_end';
    case SESSION_IMPERSONATE_DENIED = 'authz_impersonate_fail';
    case SESSION_REAUTH_SUCCESS = 'authn_reauth_success';
    case SESSION_REAUTH_FAILED = 'authn_reauth_fail';

    // C. Admin maintenance
    case ADMIN_CACHE_TEMPLATE_CLEAR = 'sys_cache_template_clear';
    case ADMIN_CACHE_DATA_CLEAR = 'sys_cache_data_clear';
    case ADMIN_SCHEDULED_LOGS_CLEAR = 'sys_scheduled_logs_clear';
    case ADMIN_SESSIONS_EXPIRE = 'session_expire_all';

    // D. User access management
    case USER_CREATED = 'user_created';
    case USER_ENABLED = 'user_enabled';
    case USER_DISABLED = 'user_disabled';
    case USER_MERGED = 'user_merged';
    case USER_DELETED = 'user_deleted';
    case USER_PASSWORD_RESET = 'user_password_reset';
    case USER_EMAIL_CHANGED = 'user_email_change';
    case USER_ROLE_ASSIGNED = 'authz_role_assign';
    case USER_ROLE_REMOVED = 'authz_role_remove';
    case USER_CONTEXT_REMOVED = 'authz_context_remove';
    case USER_GROUP_CREATED = 'authz_group_create';
    case USER_GROUP_UPDATED = 'authz_group_update';
    case USER_GROUP_DELETED = 'authz_group_delete';
    case USER_GROUP_STAGE_ASSIGNED = 'authz_group_stage_assign';
    case USER_GROUP_STAGE_UNASSIGNED = 'authz_group_stage_unassign';
    case USER_INVITATION_SENT = 'user_invitation_sent';
    case USER_INVITATION_ACCEPTED = 'user_invitation_accept';
    case USER_INVITATION_DECLINED = 'user_invitation_decline';
    case USER_INVITATION_CANCELLED = 'user_invitation_cancel';

    // E. Profile self-service
    case PROFILE_EMAIL_CHANGE_REQUEST = 'user_email_change_request';
    case PROFILE_PASSWORD_CHANGE = 'authn_password_change';
    case PROFILE_APIKEY_CREATE = 'authn_token_created';
    case PROFILE_APIKEY_REGENERATE = 'authn_token_regenerate';
    case PROFILE_APIKEY_DELETE = 'authn_token_delete';

    // F. Site security settings
    case SITE_SECURITY_UPDATE = 'sys_security_config_change';

    // G. Plugin lifecycle
    case PLUGIN_INSTALL = 'sys_plugin_install';
    case PLUGIN_UPGRADE = 'sys_plugin_upgrade';
    case PLUGIN_UNINSTALL = 'sys_plugin_uninstall';
    case PLUGIN_ENABLE = 'sys_plugin_enable';
    case PLUGIN_DISABLE = 'sys_plugin_disable';

    /**
     * Non-localised, human-readable summary of the event.
     */
    public function message(): string
    {
        return match ($this) {
            static::AUTH_LOGIN => 'User logged in successfully',
            static::AUTH_LOGIN_FAILED => 'User login attempt failed',
            static::AUTH_LOGIN_DISABLED => 'Login blocked for a disabled account',
            static::AUTH_LOGOUT => 'User logged out',
            static::AUTH_LOGOUT_IP_CHANGE => 'User forcibly logged out after a session IP address change',
            static::AUTH_RATE_LIMIT => 'Request rate limit exceeded',
            static::AUTH_PASSWORD_RESET_REQUEST => 'Password reset requested',
            static::AUTH_PASSWORD_RESET_COMPLETE => 'Password reset completed',
            static::AUTH_PASSWORD_RESET_INVALID => 'Invalid password reset token presented',
            static::AUTH_API_TOKEN_INVALID => 'Invalid API authentication token presented',

            static::SESSION_IMPERSONATE_START => 'User started impersonating another account',
            static::SESSION_IMPERSONATE_END => 'User stopped impersonating another account',
            static::SESSION_IMPERSONATE_DENIED => 'Impersonation attempt denied',
            static::SESSION_REAUTH_SUCCESS => 'Re-authentication for a sensitive area succeeded',
            static::SESSION_REAUTH_FAILED => 'Re-authentication for a sensitive area failed',

            static::ADMIN_CACHE_TEMPLATE_CLEAR => 'Template cache cleared',
            static::ADMIN_CACHE_DATA_CLEAR => 'Data cache cleared',
            static::ADMIN_SCHEDULED_LOGS_CLEAR => 'Scheduled task logs cleared',
            static::ADMIN_SESSIONS_EXPIRE => 'All user sessions expired',

            static::USER_CREATED => 'User account created',
            static::USER_ENABLED => 'User account enabled',
            static::USER_DISABLED => 'User account disabled',
            static::USER_MERGED => 'User accounts merged',
            static::USER_DELETED => 'User account deleted',
            static::USER_PASSWORD_RESET => 'User password reset by an administrator',
            static::USER_EMAIL_CHANGED => 'User email address changed by an administrator',
            static::USER_ROLE_ASSIGNED => 'Role assigned to a user',
            static::USER_ROLE_REMOVED => 'Role removed from a user',
            static::USER_CONTEXT_REMOVED => 'User removed from a context',
            static::USER_GROUP_CREATED => 'User group created',
            static::USER_GROUP_UPDATED => 'User group updated',
            static::USER_GROUP_DELETED => 'User group deleted',
            static::USER_GROUP_STAGE_ASSIGNED => 'Workflow stage assigned to a user group',
            static::USER_GROUP_STAGE_UNASSIGNED => 'Workflow stage unassigned from a user group',
            static::USER_INVITATION_SENT => 'Invitation sent',
            static::USER_INVITATION_ACCEPTED => 'Invitation accepted',
            static::USER_INVITATION_DECLINED => 'Invitation declined',
            static::USER_INVITATION_CANCELLED => 'Invitation cancelled',

            static::PROFILE_EMAIL_CHANGE_REQUEST => 'Email address change requested',
            static::PROFILE_PASSWORD_CHANGE => 'Password changed',
            static::PROFILE_APIKEY_CREATE => 'API key created',
            static::PROFILE_APIKEY_REGENERATE => 'API key regenerated',
            static::PROFILE_APIKEY_DELETE => 'API key deleted',

            static::SITE_SECURITY_UPDATE => 'Site security settings changed',

            static::PLUGIN_INSTALL => 'Plugin installed',
            static::PLUGIN_UPGRADE => 'Plugin upgraded',
            static::PLUGIN_UNINSTALL => 'Plugin uninstalled',
            static::PLUGIN_ENABLE => 'Plugin enabled',
            static::PLUGIN_DISABLE => 'Plugin disabled',

            default => $this->value,
        };
    }

    /**
     * Event outcome
     *
     * - success = the action completed;
     * - failure = it was attempted and failed (bad login, invalid token, failed re-authentication)
     * - denied = a control blocked it (disabled account, rate limit, forced-IP logout, impersonation refused).
     */
    public function status(): string
    {
        return match ($this) {
            static::AUTH_LOGIN_FAILED,
            static::AUTH_PASSWORD_RESET_INVALID,
            static::AUTH_API_TOKEN_INVALID,
            static::SESSION_REAUTH_FAILED => 'failure',

            static::AUTH_LOGIN_DISABLED,
            static::AUTH_RATE_LIMIT,
            static::AUTH_LOGOUT_IP_CHANGE,
            static::SESSION_IMPERSONATE_DENIED => 'denied',

            default => 'success',
        };
    }
}
