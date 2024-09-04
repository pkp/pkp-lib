<?php

/**
 * @file classes/core/PKPRequest.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPRequest
 *
 * @ingroup core
 *
 * @brief Class providing operations associated with HTTP requests.
 */

namespace PKP\core;

use APP\core\Application;
use APP\facades\Repo;
use APP\file\PublicFileManager;
use Illuminate\Contracts\Session\Session;
use PKP\config\Config;
use PKP\context\Context;
use PKP\db\DAORegistry;
use PKP\handler\APIHandler;
use PKP\plugins\Hook;
use PKP\security\Validation;
use PKP\site\Site;
use PKP\site\SiteDAO;
use PKP\user\User;

class PKPRequest
{
    //
    // Internal state - please do not reference directly
    //
    /** @var ?PKPRouter router instance used to route this request */
    public ?PKPRouter $_router = null;

    /** @var ?Dispatcher dispatcher instance used to dispatch this request */
    public ?Dispatcher $_dispatcher = null;

    /** @var ?array the request variables cache (GET/POST) */
    public ?array $_requestVars = null;

    /** @var string request base path */
    public string $_basePath;

    /** @var string request path */
    public string $_requestPath;

    /** @var bool true if restful URLs are enabled in the config */
    public bool $_isRestfulUrlsEnabled;

    /** @var mixed server host */
    public mixed $_serverHost;

    /** @var string request protocol */
    public string $_protocol;

    /** @var bool bot flag */
    public bool $_isBot;

    /** @var string user agent */
    public string $_userAgent;


    /**
     * get the router instance
     */
    public function getRouter(): ?PKPRouter
    {
        return $this->_router;
    }

    /**
     * set the router instance
     */
    public function setRouter(PKPRouter $router): void
    {
        $this->_router = $router;
    }

    /**
     * Set the dispatcher
     */
    public function setDispatcher(Dispatcher $dispatcher): void
    {
        $this->_dispatcher = $dispatcher;
    }

    /**
     * Get the dispatcher
     */
    public function getDispatcher(): Dispatcher
    {
        if (!$this->_dispatcher) {
            $application = Application::get();

            $this->setDispatcher($application->getDispatcher());
        }

        return $this->_dispatcher;
    }


    /**
     * Perform an HTTP redirect to an absolute or relative (to base system URL) URL and exit.
     *
     * @param string $url URL; Exclude protocol for local redirects
     *
     * @hook Request::redirect [[&$url]]
     */
    public function redirectUrl(string $url): void
    {
        if (Hook::call('Request::redirect', [&$url])) {
            return;
        }

        // sent out the cookie as header
        Application::get()->getRequest()->getSessionGuard()->sendCookies();

        header("Location: {$url}");

        exit;
    }

    /**
     * Request an HTTP redirect via JSON to be used from components.
     */
    public function redirectUrlJson(string $url): JSONMessage
    {
        $json = new JSONMessage(true);
        $json->setEvent('redirectRequested', $url);
        return $json;
    }

    /**
     * Redirect to the current URL, forcing the HTTPS protocol to be used, and exit.
     */
    public function redirectSSL(): void
    {
        // Note that we are intentionally skipping PKP processing of REQUEST_URI and QUERY_STRING for a protocol redirect
        // This processing is deferred to the redirected (target) URI
        $url = 'https://' . $this->getServerHost() . $_SERVER['REQUEST_URI'];
        $queryString = $_SERVER['QUERY_STRING'];
        if (!empty($queryString)) {
            $url .= "?{$queryString}";
        }
        $this->redirectUrl($url);
    }

    /**
     * Redirect to the current URL, forcing the HTTP protocol to be used, and exit.
     */
    public function redirectNonSSL(): void
    {
        // Note that we are intentionally skipping PKP processing of REQUEST_URI and QUERY_STRING for a protocol redirect
        // This processing is deferred to the redirected (target) URI
        $url = 'http://' . $this->getServerHost() . $_SERVER['REQUEST_URI'];
        $queryString = $_SERVER['QUERY_STRING'];
        if (!empty($queryString)) {
            $url .= "?{$queryString}";
        }
        $this->redirectUrl($url);
    }

    /**
     * Get the IF_MODIFIED_SINCE date (as a numerical timestamp) if available
     */
    public function getIfModifiedSince(): ?int
    {
        if (!isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
            return null;
        }
        return strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']);
    }

    /**
     * Get the base URL of the request (excluding script).
     *
     * @param $allowProtocolRelative True iff protocol-relative URLs are allowed
     *
     * @hook Request::getBaseUrl [[&$baseUrl]]
     */
    public function getBaseUrl(bool $allowProtocolRelative = false): string
    {
        $serverHost = $this->getServerHost(false); // False passed so that we can detect if a default was used
        if ($serverHost !== false) {
            // Auto-detection worked.
            if ($allowProtocolRelative) {
                $baseUrl = '//' . $this->getServerHost() . $this->getBasePath();
            } else {
                $baseUrl = $this->getProtocol() . '://' . $this->getServerHost() . $this->getBasePath();
            }
        } else {
            // Auto-detection didn't work (e.g. this is a command-line call); use configuration param
            $baseUrl = Config::getVar('general', 'base_url');
        }
        Hook::call('Request::getBaseUrl', [&$baseUrl]);
        return $baseUrl;
    }

    /**
     * Get the base path of the request (excluding trailing slash).
     *
     * @hook Request::getBasePath [[&$this->_basePath]]
     */
    public function getBasePath(): string
    {
        if (!isset($this->_basePath)) {
            // Strip the PHP filename off of the script's executed path
            // We expect the SCRIPT_NAME to look like /path/to/file.php
            // If the SCRIPT_NAME ends in /, assume this is the directory and the script's actual name
            // is masked as the DirectoryIndex
            // If the SCRIPT_NAME ends in neither / or .php, assume the the script's actual name is masked
            // and we need to avoid stripping the terminal directory
            $path = preg_replace('#/[^/]*$#', '', $_SERVER['SCRIPT_NAME'] . (substr($_SERVER['SCRIPT_NAME'], -1) == '/' || preg_match('#.php$#i', $_SERVER['SCRIPT_NAME']) ? '' : '/'));

            // Encode characters which need to be encoded in a URL.
            // Simply using rawurlencode() doesn't work because it
            // also encodes characters which are valid in a URL (i.e. @, $).
            $parts = explode('/', $path);
            foreach ($parts as $i => $part) {
                $pieces = array_map($this->encodeBasePathFragment(...), str_split($part));
                $parts[$i] = implode('', $pieces);
            }
            $this->_basePath = implode('/', $parts);

            if ($this->_basePath == '/' || $this->_basePath == '\\') {
                $this->_basePath = '';
            }
            Hook::call('Request::getBasePath', [&$this->_basePath]);
        }

        return $this->_basePath;
    }

    /**
     * Callback function for getBasePath() to correctly encode (or not encode)
     * a basepath fragment.
     */
    public function encodeBasePathFragment(string $fragment): string
    {
        if (!preg_match('/[A-Za-z0-9-._~!$&\'()*+,;=:@]/', $fragment)) {
            return rawurlencode($fragment);
        }
        return $fragment;
    }

    /**
     * Deprecated
     *
     * @see PKPPageRouter::getIndexUrl()
     *
     * @hook Request::getIndexUrl [[&$indexUrl]]
     */
    public function getIndexUrl(): string
    {
        static $indexUrl;

        if (!isset($indexUrl)) {
            $indexUrl = $this->getRouter()->getIndexUrl($this);

            // Call legacy hook
            Hook::call('Request::getIndexUrl', [&$indexUrl]);
        }

        return $indexUrl;
    }

    /**
     * Get the complete URL to this page, including parameters.
     *
     * @hook Request::getCompleteUrl [[&$completeUrl]]
     */
    public function getCompleteUrl(): string
    {
        static $completeUrl;

        if (!isset($completeUrl)) {
            $completeUrl = $this->getRequestUrl();
            $queryString = $this->getQueryString();
            if (!empty($queryString)) {
                $completeUrl .= "?{$queryString}";
            }
            Hook::call('Request::getCompleteUrl', [&$completeUrl]);
        }

        return $completeUrl;
    }

    /**
     * Get the complete URL of the request.
     *
     * @hook Request::getRequestUrl [[&$requestUrl]]
     */
    public function getRequestUrl(): string
    {
        static $requestUrl;

        if (!isset($requestUrl)) {
            $requestUrl = $this->getProtocol() . '://' . $this->getServerHost() . $this->getRequestPath();
            Hook::call('Request::getRequestUrl', [&$requestUrl]);
        }

        return $requestUrl;
    }

    /**
     * Get the complete set of URL parameters to the current request.
     *
     * @hook Request::getQueryString [[&$queryString]]
     */
    public function getQueryString(): string
    {
        static $queryString;

        if (!isset($queryString)) {
            $queryString = $_SERVER['QUERY_STRING'] ?? '';
            Hook::call('Request::getQueryString', [&$queryString]);
        }

        return $queryString;
    }

    /**
     * Get the complete set of URL parameters to the current request as an
     * associative array.
     */
    public function getQueryArray(): array
    {
        $queryString = $this->getQueryString();
        $queryArray = [];

        if (isset($queryString)) {
            parse_str($queryString, $queryArray);
        }

        return $queryArray;
    }

    /**
     * Get the completed path of the request.
     *
     * @hook Request::getRequestPath [[&$this->_requestPath]]
     */
    public function getRequestPath(): string
    {
        if (!isset($this->_requestPath)) {
            if ($this->isRestfulUrlsEnabled()) {
                $this->_requestPath = $this->getBasePath();
            } else {
                $this->_requestPath = $_SERVER['SCRIPT_NAME'] ?? '';
            }

            $this->_requestPath .= $_SERVER['PATH_INFO'] ?? '';
            Hook::call('Request::getRequestPath', [&$this->_requestPath]);
        }
        return $this->_requestPath;
    }

    /**
     * Get the server hostname in the request.
     *
     * @param $default Default hostname (defaults to localhost if null)
     * @param $includePort Whether to include non-standard port number; default true
     *
     * @hook Request::getServerHost [[&$this->_serverHost, &$default, &$includePort]]
     */
    public function getServerHost(mixed $default = null, bool $includePort = true): mixed
    {
        if (!isset($this->_serverHost)) {
            $this->_serverHost = $_SERVER['HTTP_X_FORWARDED_HOST']
                ?? $_SERVER['HTTP_HOST']
                ?? $_SERVER['SERVER_NAME']
                ?? $default
                ?? 'localhost';
            // in case of multiple host entries in the header (e.g. multiple reverse proxies) take the first entry
            $this->_serverHost = strtok($this->_serverHost, ',');
            Hook::call('Request::getServerHost', [&$this->_serverHost, &$default, &$includePort]);
        }
        if (!$includePort) {
            // Strip the port number, if one is included. (#3912)
            return preg_replace("/:\d*$/", '', $this->_serverHost);
        }
        return $this->_serverHost;
    }

    /**
     * Get the protocol used for the request (HTTP or HTTPS).
     *
     * @hook Request::getProtocol [[&$this->_protocol]]
     */
    public function getProtocol(): string
    {
        if (!isset($this->_protocol)) {
            $this->_protocol = (!isset($_SERVER['HTTPS']) || strtolower($_SERVER['HTTPS']) != 'on') ? 'http' : 'https';
            Hook::call('Request::getProtocol', [&$this->_protocol]);
        }
        return $this->_protocol;
    }

    /**
     * Get the request method
     */
    public function getRequestMethod(): string
    {
        return ($_SERVER['REQUEST_METHOD'] ?? '');
    }

    /**
     * Determine whether the request is a POST request
     */
    public function isPost(): bool
    {
        return ($this->getRequestMethod() == 'POST');
    }

    /**
     * Determine whether the request is a GET request
     */
    public function isGet(): bool
    {
        return ($this->getRequestMethod() == 'GET');
    }

    /**
     * Determine whether a CSRF token is present and correct.
     */
    public function checkCSRF(): bool
    {
        return $this->getUserVar('csrfToken') == $this->getSession()->token();
    }

    /**
     * Get the remote IP address of the current request.
     *
     * @hook Request::getRemoteAddr [[&$ipaddr]]
     */
    public function getRemoteAddr(): string
    {
        $ipaddr = &Registry::get('remoteIpAddr'); // Reference required.
        if (is_null($ipaddr)) {
            if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) &&
                Config::getVar('general', 'trust_x_forwarded_for', true) &&
                preg_match_all('/([0-9.a-fA-F:]+)/', $_SERVER['HTTP_X_FORWARDED_FOR'], $matches)) {
            } elseif (isset($_SERVER['REMOTE_ADDR']) &&
                preg_match_all('/([0-9.a-fA-F:]+)/', $_SERVER['REMOTE_ADDR'], $matches)) {
            } elseif (preg_match_all('/([0-9.a-fA-F:]+)/', getenv('REMOTE_ADDR'), $matches)) {
            } else {
                $ipaddr = '';
            }

            if (!isset($ipaddr)) {
                // If multiple addresses are listed, take the last. (Supports ipv6.)
                $ipaddr = $matches[0][count($matches[0]) - 1];
            }
            Hook::call('Request::getRemoteAddr', [&$ipaddr]);
        }
        return $ipaddr;
    }

    /**
     * Get the remote domain of the current request
     *
     * @hook Request::getRemoteDomain [[&$remoteDomain]]
     */
    public function getRemoteDomain(): string
    {
        static $remoteDomain;
        if (!isset($remoteDomain)) {
            $remoteDomain = null;
            $remoteDomain = @getHostByAddr($this->getRemoteAddr());
            Hook::call('Request::getRemoteDomain', [&$remoteDomain]);
        }
        return $remoteDomain;
    }

    /**
     * Get the user agent of the current request.
     *
     * @hook Request::getUserAgent [[&$this->_userAgent]]
     */
    public function getUserAgent(): string
    {
        if (!isset($this->_userAgent)) {
            if (isset($_SERVER['HTTP_USER_AGENT'])) {
                $this->_userAgent = $_SERVER['HTTP_USER_AGENT'];
            }
            if (!isset($this->_userAgent) || empty($this->_userAgent)) {
                $this->_userAgent = getenv('HTTP_USER_AGENT');
            }
            if (!isset($this->_userAgent) || $this->_userAgent == false) {
                $this->_userAgent = '';
            }
            Hook::call('Request::getUserAgent', [&$this->_userAgent]);
        }
        return $this->_userAgent;
    }

    /**
     * Determine whether the user agent is a bot or not.
     */
    public function isBot(): bool
    {
        if (!isset($this->_isBot)) {
            $userAgent = $this->getUserAgent();
            $this->_isBot = Core::isUserAgentBot($userAgent);
        }
        return $this->_isBot;
    }

    /**
     * Check if the HTTP_DNT (Do Not Track) is set
     */
    public function getDoNotTrack(): bool
    {
        return (array_key_exists('HTTP_DNT', $_SERVER) && ((int) $_SERVER['HTTP_DNT'] === 1));
    }

    /**
     * Return true if RESTFUL_URLS is enabled.
     */
    public function isRestfulUrlsEnabled(): bool
    {
        if (!isset($this->_isRestfulUrlsEnabled)) {
            $this->_isRestfulUrlsEnabled = Config::getVar('general', 'restful_urls') ? true : false;
        }
        return $this->_isRestfulUrlsEnabled;
    }

    /**
     * Get site data.
     */
    public function getSite(): ?Site
    {
        $site = &Registry::get('site', true, null);
        /** @var SiteDAO */
        $siteDao = DAORegistry::getDAO('SiteDAO');
        return $site ??= $siteDao->getSite();
    }

    /**
     * Get the session guard resposible for managing session
     */
    public function getSessionGuard(): PKPSessionGuard
    {
        $sessionGuard = app()->get('auth.driver'); /** @var \PKP\core\PKPSessionGuard $sessionGuard */
        return $sessionGuard;
    }

    /**
     * Get the user session associated with the current request.
     */
    public function getSession(): Session
    {
        return $this->getSessionGuard()->getSession();
    }

    /**
     * Get the user associated with the current request.
     */
    public function getUser(): ?User
    {
        $user = &Registry::get('user', true, null);
        if ($user) {
            return $user;
        }

        // Attempt to load user from API token
        if (($handler = $this->getRouter()?->getHandler())
            && ($token = $handler->getApiToken())
            && ($apiUser = Repo::user()->getByApiKey($token))
            && $apiUser->getData('apiKeyEnabled')
        ) {
            return $user = $apiUser;
        }

        // Attempts to retrieve a logged user
        if (Validation::isLoggedIn()) {
            $user = Repo::user()->get($this->getSessionGuard()->getUserId());
        }

        return $user;
    }

    /**
     * Get the value of a GET/POST variable.
     */
    public function getUserVar(string $key): mixed
    {
        // special treatment for APIRouter. APIHandler gets to fetch parameter first
        $router = $this->getRouter();
        
        if ($router instanceof \PKP\core\APIRouter && (!is_null($handler = $router->getHandler()))) {
            $handler = $router->getHandler(); /** @var \PKP\handler\APIHandler $handler */
            $value = $handler->getApiController()->getParameter($key);
            if (!is_null($value)) {
                return $value;
            }
        }

        // Get all vars (already cleaned)
        $vars = $this->getUserVars();
        return $vars[$key] ?? null;
    }

    /**
     * Get all GET/POST variables as an array
     */
    public function &getUserVars(): array
    {
        $this->_requestVars ??= array_map(fn ($s) => is_string($s) ? trim($s) : $s, array_merge($_GET, $_POST));
        return $this->_requestVars;
    }

    /**
     * Get the value of a GET/POST variable generated using the Smarty
     * html_select_date and/or html_select_time function.
     *
     * @return ?int Linux timestamp
     */
    public function getUserDateVar(string $prefix, ?int $defaultDay = null, ?int $defaultMonth = null, ?int $defaultYear = null, int $defaultHour = 0, int $defaultMinute = 0, int $defaultSecond = 0): ?int
    {
        $monthPart = $this->getUserVar($prefix . 'Month');
        $dayPart = $this->getUserVar($prefix . 'Day');
        $yearPart = $this->getUserVar($prefix . 'Year');
        $hourPart = $this->getUserVar($prefix . 'Hour');
        $minutePart = $this->getUserVar($prefix . 'Minute');
        $secondPart = $this->getUserVar($prefix . 'Second');

        switch ($this->getUserVar($prefix . 'Meridian')) {
            case 'pm':
                if (is_numeric($hourPart) && $hourPart != 12) {
                    $hourPart += 12;
                }
                break;
            case 'am':
            default:
                // Do nothing.
                break;
        }

        if (empty($dayPart)) {
            $dayPart = $defaultDay;
        }
        if (empty($monthPart)) {
            $monthPart = $defaultMonth;
        }
        if (empty($yearPart)) {
            $yearPart = $defaultYear;
        }
        if (empty($hourPart)) {
            $hourPart = $defaultHour;
        }
        if (empty($minutePart)) {
            $minutePart = $defaultMinute;
        }
        if (empty($secondPart)) {
            $secondPart = $defaultSecond;
        }

        if (empty($monthPart) || empty($dayPart) || empty($yearPart)) {
            return null;
        }
        return mktime($hourPart, $minutePart, $secondPart, $monthPart, $dayPart, $yearPart);
    }

    /**
     * Get the value of a cookie variable.
     */
    public function getCookieVar(string $key): ?string
    {
        if (isset($_COOKIE[$key])) {
            return $_COOKIE[$key];
        } else {
            return null;
        }
    }

    /**
     * Set a cookie variable.
     *
     * @param $expire (optional)
     */
    public function setCookieVar(string $key, string $value, int $expire = 0)
    {
        $basePath = $this->getBasePath();
        if (!$basePath) {
            $basePath = '/';
        }

        setcookie($key, $value, $expire, $basePath);
        $_COOKIE[$key] = $value;
    }

    /**
     * Redirect to the specified page within a PKP Application.
     * Shorthand for a common call to $request->redirect($dispatcher->url($request, PKPApplication::ROUTE_PAGE, ...)).
     *
     * @param $context The optional contextual paths
     * @param $page The name of the op to redirect to.
     * @param $op optional The name of the op to redirect to.
     * @param $path string or array containing path info for redirect.
     * @param $params Map of name => value pairs for additional parameters
     * @param $anchor Name of desired anchor on the target page
     * @param $urlLocaleForPage Whether or not to override locale for this URL; Use '' to exclude.
     */
    public function redirect(?string $context = null, ?string $page = null, ?string $op = null, ?array $path = null, ?array $params = null, ?string $anchor = null, ?string $urlLocaleForPage = null)
    {
        $dispatcher = $this->getDispatcher();
        $this->redirectUrl($dispatcher->url($this, PKPApplication::ROUTE_PAGE, $context, $page, $op, $path, $params, $anchor, false, $urlLocaleForPage));
    }

    /**
     * Get the current "context" (press/journal/etc) object.
     *
     * @see PKPPageRouter::getContext()
     */
    public function getContext(): ?Context
    {
        return $this->getRouter()->getContext($this);
    }

    /**
     * Deprecated
     *
     * @see PKPPageRouter::getRequestedPage()
     */
    public function getRequestedPage(): string
    {
        return $this->getRouter()->getRequestedPage($this);
    }

    /**
     * Deprecated
     *
     * @see PKPPageRouter::getRequestedOp()
     */
    public function getRequestedOp(): string
    {
        return $this->getRouter()->getRequestedOp($this);
    }

    /**
     * Deprecated
     *
     * @see PKPPageRouter::getRequestedArgs()
     */
    public function getRequestedArgs(): array
    {
        return $this->getRouter()->getRequestedArgs($this);
    }

    /**
     * Deprecated
     *
     * @see PKPPageRouter::url()
     */
    public function url(
        ?string $context = null,
        ?string $page = null,
        ?string $op = null,
        ?array $path = null,
        ?array $params = null,
        ?string $anchor = null,
        bool $escape = false
    ) {
        return $this->getRouter()->url($this, ...func_get_args());
    }

    /**
     * Get the URL to the public file uploads directory
     */
    public function getPublicFilesUrl(?Context $context = null): string
    {
        $publicFileManager = new PublicFileManager();

        return join('/', [
            $this->getBaseUrl(),
            $context
                ? $publicFileManager->getContextFilesPath($context->getId())
                : $publicFileManager->getSiteFilesPath()
        ]);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\core\PKPRequest', '\PKPRequest');
}
