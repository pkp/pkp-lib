<?php

/**
 * @file classes/orcid/OrcidManager.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class OrcidManager
 *
 * @brief Manages using ORCID settings at site and journal level
 */

namespace PKP\orcid;

use APP\author\Author;
use APP\core\Application;
use APP\facades\Repo;
use PKP\config\Config;
use PKP\context\Context;
use PKP\core\Core;
use PKP\jobs\orcid\RevokeOrcidToken;
use PKP\user\User;

class OrcidManager
{
    public const ORCID_URL = 'https://orcid.org/';
    public const ORCID_URL_SANDBOX = 'https://sandbox.orcid.org/';
    public const ORCID_API_URL_PUBLIC = 'https://pub.orcid.org/';
    public const ORCID_API_URL_PUBLIC_SANDBOX = 'https://pub.sandbox.orcid.org/';
    public const ORCID_API_URL_MEMBER = 'https://api.orcid.org/';
    public const ORCID_API_URL_MEMBER_SANDBOX = 'https://api.sandbox.orcid.org/';
    public const ORCID_API_VERSION_URL = 'v3.0/';

    public const OAUTH_TOKEN_URL = 'oauth/token';

    public const ORCID_API_SCOPE_PUBLIC = '/authenticate';
    public const ORCID_API_SCOPE_MEMBER = '/activities/update';
    public const ORCID_EMPLOYMENTS_URL = 'employments';
    public const ORCID_PROFILE_URL = 'person';
    public const ORCID_WORK_URL = 'work';
    public const ORCID_REVIEW_URL = 'peer-review';

    // Setting names and values used in ORCID settings forms
    public const ENABLED = 'orcidEnabled';
    public const CLIENT_ID = 'orcidClientId';
    public const CLIENT_SECRET = 'orcidClientSecret';
    public const SEND_MAIL_TO_AUTHORS_ON_PUBLICATION = 'orcidSendMailToAuthorsOnPublication';
    public const LOG_LEVEL = 'orcidLogLevel';
    public const CITY = 'orcidCity';
    public const API_TYPE = 'orcidApiType';
    public const API_PUBLIC_PRODUCTION = 'publicProduction';
    public const API_PUBLIC_SANDBOX = 'publicSandbox';
    public const API_MEMBER_PRODUCTION = 'memberProduction';
    public const API_MEMBER_SANDBOX = 'memberSandbox';
    public const LOG_LEVEL_ERROR = 'ERROR';
    public const LOG_LEVEL_INFO = 'INFO';

    /**
     * Check if ORCID is configured at the site-level of the application.
     *
     * @return boolean True, if the ORCID settings at the site level are enabled, meaning the API type, client ID,
     *                 and client secret are set because they are required fields.
     */
    public static function isGloballyConfigured(): bool
    {
        $site = Application::get()->getRequest()->getSite();
        return (bool) $site->getData(self::ENABLED);
    }

    /**
     * Return a string of the ORCiD SVG icon
     *
     */
    public static function getIcon(): string
    {
        $path = Core::getBaseDir() . '/' . PKP_LIB_PATH . '/templates/images/orcid.svg';
        return file_exists($path) ? file_get_contents($path) : '';
    }

    /**
     * Checks if ORCID functionality is enabled. Works at the context-level.
     */
    public static function isEnabled(?Context $context = null): bool
    {
        if (self::isGloballyConfigured()) {
            return true;
        }
        if ($context === null) {
            $context = Application::get()->getRequest()->getContext();
        }

        return (bool) $context?->getData(self::ENABLED);
    }

    /**
     * Gets the main ORCID URL, either production or sandbox.
     *
     * Will first check if globally configured and prioritize site-level settings over context-level setting.
     */
    public static function getOrcidUrl(?Context $context = null): string
    {
        $apiType = self::getApiType($context);
        return in_array($apiType, [self::API_PUBLIC_PRODUCTION, self::API_MEMBER_PRODUCTION]) ? self::ORCID_URL : self::ORCID_URL_SANDBOX;
    }

    /**
     * Gets the ORCID API URL, one of sandbox/production, member/public API URLs.
     *
     * Will first check if globally configured and prioritize site-level settings over context-level setting.
     */
    public static function getApiPath(?Context $context = null): string
    {
        $apiType = self::getApiType($context);

        return match ($apiType) {
            self::API_PUBLIC_SANDBOX => self::ORCID_API_URL_PUBLIC_SANDBOX,
            self::API_MEMBER_PRODUCTION => self::ORCID_API_URL_MEMBER,
            self::API_MEMBER_SANDBOX => self::ORCID_API_URL_MEMBER_SANDBOX,
            default => self::ORCID_API_URL_PUBLIC,
        };
    }

    /**
     * Returns whether the ORCID integration is set to use the sandbox domain.
     *
     * Will first check if globally configured and prioritize site-level settings over context-level setting.
     */
    public static function isSandbox(?Context $context = null): bool
    {
        $apiType = self::getApiType($context);
        return in_array($apiType, [self::API_PUBLIC_SANDBOX, self::API_MEMBER_SANDBOX]);
    }

    /**
     * Constructs an ORCID OAuth URL with correct scope/API based on configured settings
     *
     * @param string $handlerMethod Previously: containting a valid method of the OrcidProfileHandler
     * @param array $redirectParams Additional request parameters for the redirect URL
     *
     * @throws \Exception
     */
    public static function buildOAuthUrl(string $handlerMethod, array $redirectParams): string
    {
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        if ($context === null) {
            throw new \Exception('OAuth URLs can only be made in a Context, not site wide.');
        }

        $scope = self::isMemberApiEnabled() ? self::ORCID_API_SCOPE_MEMBER : self::ORCID_API_SCOPE_PUBLIC;

        // We need to construct a page url, but the request is using the component router.
        // Use the Dispatcher to construct the url and set the page router.
        $redirectUrl = $request->getDispatcher()->url(
            $request,
            Application::ROUTE_PAGE,
            null,
            'orcid',
            $handlerMethod,
            null,
            $redirectParams,
            urlLocaleForPage: '',
        );

        return self::getOauthPath() . 'authorize?' . http_build_query(
            [
                'client_id' => self::getClientId($context),
                'response_type' => 'code',
                'scope' => $scope,
                'redirect_uri' => $redirectUrl]
        );
    }

    /**
     * Gets the configured city for use with review contributions.
     *
     * Will first check if globally configured and prioritize site-level settings over context-level setting.
     */
    public static function getCity(?Context $context = null): string
    {
        if ($context === null) {
            $context = Application::get()->getRequest()->getContext();
        }

        return $context->getData(self::CITY) ?? '';
    }

    /**
     * Gets the configured country for use with review contributions.
     *
     * Will first check if globally configured and prioritize site-level settings over context-level setting.
     */
    public static function getCountry(?Context $context = null): string
    {
        if ($context === null) {
            $context = Application::get()->getRequest()->getContext();
        }
        return $context->getData('country');
    }


    /**
     * Returns true if member API (as opposed to public API) is in use.
     *
     * Will first check if globally configured and prioritize site-level settings over context-level setting.
     */
    public static function isMemberApiEnabled(?Context $context = null): bool
    {
        $apiType = self::getApiType($context);
        return in_array($apiType, [self::API_MEMBER_PRODUCTION, self::API_MEMBER_SANDBOX]);
    }

    /**
     * Gets the currently configured log level. Can only bet set at the context-level, not the site-level.
     */
    public static function getLogLevel(?Context $context = null): string
    {
        if ($context === null) {
            $context = Application::get()->getRequest()->getContext();
        }

        return $context->getData(self::LOG_LEVEL) ?? self::LOG_LEVEL_ERROR;
    }


    /**
     * Checks whether option to email authors for verification/authorization has been configured (context-level only).
     */
    public static function shouldSendMailToAuthors(?Context $context = null): bool
    {
        if ($context === null) {
            $context = Application::get()->getRequest()->getContext();
        }

        return $context->getData(self::SEND_MAIL_TO_AUTHORS_ON_PUBLICATION) ?? false;
    }

    /**
     * Helper method that gets OAuth endpoint for configured ORCID URL (production or sandbox)
     */
    public static function getOauthPath(): string
    {
        return self::getOrcidUrl() . 'oauth/';
    }

    /**
     * Gets the configured client ID. Used to connect to the ORCID API.
     *
     * Will first check if globally configured and prioritize site-level settings over context-level setting.
     */
    public static function getClientId(?Context $context = null): string
    {
        if (self::isGloballyConfigured()) {
            return Application::get()->getRequest()->getSite()->getData(self::CLIENT_ID);
        }

        if ($context === null) {
            $context = Application::get()->getRequest()->getContext();
        }

        return $context->getData(self::CLIENT_ID) ?? '';
    }

    /**
     * Gets the configured client secret. Used to connect to the ORCID API.
     *
     * Will first check if globally configured and prioritize site-level settings over context-level setting.
     */
    public static function getClientSecret(?Context $context = null): string
    {
        if (self::isGloballyConfigured()) {
            return Application::get()->getRequest()->getSite()->getData(self::CLIENT_SECRET);
        }

        if ($context === null) {
            $context = Application::get()->getRequest()->getContext();
        }

        return $context->getData(self::CLIENT_SECRET) ?? '';
    }

    /**
     * Remove all data fields, which belong to an ORCID access token from the
     * given Author or User object. Also updates fields in the db.
     *
     * @param bool $updateDb If true, update the underlying fields in the database.
     *      Use only if not called from a function, which will already update the object.
     */
    public static function removeOrcidAccessToken(Author|User $identity, bool $updateDb = false): void
    {
        dispatch(new RevokeOrcidToken(Application::get()->getRequest()->getContext(), $identity));

        $identity->setData('orcidAccessToken', null);
        $identity->setData('orcidAccessScope', null);
        $identity->setData('orcidRefreshToken', null);
        $identity->setData('orcidAccessExpiresOn', null);

        if ($updateDb) {
            if ($identity instanceof User) {
                Repo::user()->edit($identity);
            } else {
                Repo::author()->edit($identity);
            }
        }
    }

    /**
     * Write out log message at the INFO level.
     */
    public static function logInfo(string $message): void
    {
        if (self::getLogLevel() !== self::LOG_LEVEL_INFO) {
            return;
        }
        self::writeLog($message, self::LOG_LEVEL_INFO);
    }

    /**
     * Write out log message at the ERROR level.
     */
    public static function logError(string $message): void
    {
        if (self::getLogLevel() !== self::LOG_LEVEL_ERROR) {
            return;
        }
        self::writeLog($message, self::LOG_LEVEL_ERROR);
    }

    /**
     * Helper method to write log message out to the configured log file.
     */
    private static function writeLog(string $message, string $level): void
    {
        $fineStamp = date('Y-m-d H:i:s') . substr(microtime(), 1, 4);
        $logFilePath = Config::getVar('files', 'files_dir') . '/orcid.log';
        error_log("{$fineStamp} {$level} {$message}\n", 3, $logFilePath);
    }

    /**
     * Gets the ORCID API endpoint to revoke an access token
     */
    public static function getTokenRevocationUrl(): string
    {
        return self::getOauthPath() . 'revoke';
    }

    /**
     * Helper method get the ORCID Api Type.
     */
    private static function getApiType(?Context $context = null): string
    {
        if (self::isGloballyConfigured()) {
            $apiType = Application::get()->getRequest()->getSite()->getData(self::API_TYPE);
        } else {
            if ($context === null) {
                $context = Application::get()->getRequest()->getContext();
            }
            $apiType = $context->getData(self::API_TYPE);
        }

        return $apiType;
    }
}
