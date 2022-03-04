<?php

/**
 * @file classes/core/PKPRequest.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPRequest
 * @ingroup core
 *
 * @brief Class providing operations associated with HTTP requests.
 */

namespace PKP\core;

use APP\core\Application;
use APP\facades\Repo;
use PKP\config\Config;
use PKP\context\Context;
use PKP\db\DAORegistry;
use PKP\plugins\HookRegistry;
use PKP\security\Validation;
use PKP\session\Session;
use PKP\session\SessionManager;
use PKP\site\Site;
use PKP\user\User;

class PKPRequest
{
    //
    // Internal state - please do not reference directly
    //
    /** @var PKPRouter router instance used to route this request */
    public $_router = null;

    /** @var Dispatcher dispatcher instance used to dispatch this request */
    public $_dispatcher = null;

    /** @var array the request variables cache (GET/POST) */
    public $_requestVars = null;

    /** @var string request base path */
    public $_basePath;

    /** @var string request path */
    public $_requestPath;

    /** @var bool true if restful URLs are enabled in the config */
    public $_isRestfulUrlsEnabled;

    /** @var bool true if path info is enabled for this server */
    public $_isPathInfoEnabled;

    /** @var string server host */
    public $_serverHost;

    /** @var string request protocol */
    public $_protocol;

    /** @var bool bot flag */
    public $_isBot;

    /** @var string user agent */
    public $_userAgent;


    /**
     * get the router instance
     *
     * @return PKPRouter
     */
    public function &getRouter()
    {
        return $this->_router;
    }

    /**
     * set the router instance
     *
     * @param PKPRouter $router
     */
    public function setRouter($router)
    {
        $this->_router = $router;
    }

    /**
     * Set the dispatcher
     *
     * @param Dispatcher $dispatcher
     */
    public function setDispatcher($dispatcher)
    {
        $this->_dispatcher = $dispatcher;
    }

    /**
     * Get the dispatcher
     *
     * @return Dispatcher
     */
    public function &getDispatcher()
    {
        return $this->_dispatcher;
    }


    /**
     * Perform an HTTP redirect to an absolute or relative (to base system URL) URL.
     *
     * @param string $url (exclude protocol for local redirects)
     */
    public function redirectUrl($url)
    {
        if (HookRegistry::call('Request::redirect', [&$url])) {
            return;
        }

        header("Location: ${url}");
        exit;
    }

    /**
     * Request an HTTP redirect via JSON to be used from components.
     *
     * @param string $url
     *
     * @return JSONMessage
     */
    public function redirectUrlJson($url)
    {
        $json = new JSONMessage(true);
        $json->setEvent('redirectRequested', $url);
        return $json;
    }

    /**
     * Redirect to the current URL, forcing the HTTPS protocol to be used.
     */
    public function redirectSSL()
    {
        // Note that we are intentionally skipping PKP processing of REQUEST_URI and QUERY_STRING for a protocol redirect
        // This processing is deferred to the redirected (target) URI
        $url = 'https://' . $this->getServerHost() . $_SERVER['REQUEST_URI'];
        $queryString = $_SERVER['QUERY_STRING'];
        if (!empty($queryString)) {
            $url .= "?${queryString}";
        }
        $this->redirectUrl($url);
    }

    /**
     * Redirect to the current URL, forcing the HTTP protocol to be used.
     */
    public function redirectNonSSL()
    {
        // Note that we are intentionally skipping PKP processing of REQUEST_URI and QUERY_STRING for a protocol redirect
        // This processing is deferred to the redirected (target) URI
        $url = 'http://' . $this->getServerHost() . $_SERVER['REQUEST_URI'];
        $queryString = $_SERVER['QUERY_STRING'];
        if (!empty($queryString)) {
            $url .= "?${queryString}";
        }
        $this->redirectUrl($url);
    }

    /**
     * Get the IF_MODIFIED_SINCE date (as a numerical timestamp) if available
     *
     * @return int
     */
    public function getIfModifiedSince()
    {
        if (!isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
            return null;
        }
        return strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']);
    }

    /**
     * Get the base URL of the request (excluding script).
     *
     * @param bool $allowProtocolRelative True iff protocol-relative URLs are allowed
     *
     * @return string
     */
    public function getBaseUrl($allowProtocolRelative = false)
    {
        $serverHost = $this->getServerHost(false);
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
        HookRegistry::call('Request::getBaseUrl', [&$baseUrl]);
        return $baseUrl;
    }

    /**
     * Get the base path of the request (excluding trailing slash).
     *
     * @return string
     */
    public function getBasePath()
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
                $pieces = array_map([$this, 'encodeBasePathFragment'], str_split($part));
                $parts[$i] = implode('', $pieces);
            }
            $this->_basePath = implode('/', $parts);

            if ($this->_basePath == '/' || $this->_basePath == '\\') {
                $this->_basePath = '';
            }
            HookRegistry::call('Request::getBasePath', [&$this->_basePath]);
        }

        return $this->_basePath;
    }

    /**
     * Callback function for getBasePath() to correctly encode (or not encode)
     * a basepath fragment.
     *
     * @param string $fragment
     *
     * @return string
     */
    public function encodeBasePathFragment($fragment)
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
     */
    public function getIndexUrl()
    {
        static $indexUrl;

        if (!isset($indexUrl)) {
            $indexUrl = $this->_delegateToRouter('getIndexUrl');

            // Call legacy hook
            HookRegistry::call('Request::getIndexUrl', [&$indexUrl]);
        }

        return $indexUrl;
    }

    /**
     * Get the complete URL to this page, including parameters.
     *
     * @return string
     */
    public function getCompleteUrl()
    {
        static $completeUrl;

        if (!isset($completeUrl)) {
            $completeUrl = $this->getRequestUrl();
            $queryString = $this->getQueryString();
            if (!empty($queryString)) {
                $completeUrl .= "?${queryString}";
            }
            HookRegistry::call('Request::getCompleteUrl', [&$completeUrl]);
        }

        return $completeUrl;
    }

    /**
     * Get the complete URL of the request.
     *
     * @return string
     */
    public function getRequestUrl()
    {
        static $requestUrl;

        if (!isset($requestUrl)) {
            $requestUrl = $this->getProtocol() . '://' . $this->getServerHost() . $this->getRequestPath();
            HookRegistry::call('Request::getRequestUrl', [&$requestUrl]);
        }

        return $requestUrl;
    }

    /**
     * Get the complete set of URL parameters to the current request.
     *
     * @return string
     */
    public function getQueryString()
    {
        static $queryString;

        if (!isset($queryString)) {
            $queryString = $_SERVER['QUERY_STRING'] ?? '';
            HookRegistry::call('Request::getQueryString', [&$queryString]);
        }

        return $queryString;
    }

    /**
     * Get the complete set of URL parameters to the current request as an
     * associative array. (Excludes reserved parameters, such as "path",
     * which are used by disable_path_info mode.)
     *
     * @return array
     */
    public function getQueryArray()
    {
        $queryString = $this->getQueryString();
        $queryArray = [];

        if (isset($queryString)) {
            parse_str($queryString, $queryArray);
        }

        // Filter out disable_path_info reserved parameters
        foreach (array_merge(Application::get()->getContextList(), ['path', 'page', 'op']) as $varName) {
            if (isset($queryArray[$varName])) {
                unset($queryArray[$varName]);
            }
        }

        return $queryArray;
    }

    /**
     * Get the completed path of the request.
     *
     * @return string
     */
    public function getRequestPath()
    {
        if (!isset($this->_requestPath)) {
            if ($this->isRestfulUrlsEnabled()) {
                $this->_requestPath = $this->getBasePath();
            } else {
                $this->_requestPath = $_SERVER['SCRIPT_NAME'] ?? '';
            }

            if ($this->isPathInfoEnabled()) {
                $this->_requestPath .= $_SERVER['PATH_INFO'] ?? '';
            }
            HookRegistry::call('Request::getRequestPath', [&$this->_requestPath]);
        }
        return $this->_requestPath;
    }

    /**
     * Get the server hostname in the request.
     *
     * @param string $default Default hostname (defaults to localhost)
     * @param bool $includePort Whether to include non-standard port number; default true
     *
     * @return string
     */
    public function getServerHost($default = null, $includePort = true)
    {
        if ($default === null) {
            $default = 'localhost';
        }

        if (!isset($this->_serverHost)) {
            $this->_serverHost = $_SERVER['HTTP_X_FORWARDED_HOST']
                ?? ($_SERVER['HTTP_HOST']
                ?? ($_SERVER['SERVER_NAME']
                ?? $default));
            // in case of multiple host entries in the header (e.g. multiple reverse proxies) take the first entry
            $this->_serverHost = strtok($this->_serverHost, ',');
            HookRegistry::call('Request::getServerHost', [&$this->_serverHost, &$default, &$includePort]);
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
     * @return string
     */
    public function getProtocol()
    {
        if (!isset($this->_protocol)) {
            $this->_protocol = (!isset($_SERVER['HTTPS']) || strtolower_codesafe($_SERVER['HTTPS']) != 'on') ? 'http' : 'https';
            HookRegistry::call('Request::getProtocol', [&$this->_protocol]);
        }
        return $this->_protocol;
    }

    /**
     * Get the request method
     *
     * @return string
     */
    public function getRequestMethod()
    {
        return ($_SERVER['REQUEST_METHOD'] ?? '');
    }

    /**
     * Determine whether the request is a POST request
     *
     * @return bool
     */
    public function isPost()
    {
        return ($this->getRequestMethod() == 'POST');
    }

    /**
     * Determine whether the request is a GET request
     *
     * @return bool
     */
    public function isGet()
    {
        return ($this->getRequestMethod() == 'GET');
    }

    /**
     * Determine whether a CSRF token is present and correct.
     *
     * @return bool
     */
    public function checkCSRF()
    {
        $session = $this->getSession();
        return $this->getUserVar('csrfToken') == $session->getCSRFToken();
    }

    /**
     * Get the remote IP address of the current request.
     *
     * @return string
     */
    public function getRemoteAddr()
    {
        $ipaddr = & Registry::get('remoteIpAddr'); // Reference required.
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
            HookRegistry::call('Request::getRemoteAddr', [&$ipaddr]);
        }
        return $ipaddr;
    }

    /**
     * Get the remote domain of the current request
     *
     * @return string
     */
    public function getRemoteDomain()
    {
        static $remoteDomain;
        if (!isset($remoteDomain)) {
            $remoteDomain = null;
            $remoteDomain = @getHostByAddr($this->getRemoteAddr());
            HookRegistry::call('Request::getRemoteDomain', [&$remoteDomain]);
        }
        return $remoteDomain;
    }

    /**
     * Get the user agent of the current request.
     *
     * @return string
     */
    public function getUserAgent()
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
            HookRegistry::call('Request::getUserAgent', [&$this->_userAgent]);
        }
        return $this->_userAgent;
    }

    /**
     * Determine whether the user agent is a bot or not.
     *
     * @return bool
     */
    public function isBot()
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
    public function isDNTSet(): bool
    {
        return (array_key_exists('HTTP_DNT', $_SERVER) && ((int) $_SERVER['HTTP_DNT'] === 1));
    }

    /**
     * Return true if PATH_INFO is enabled.
     */
    public function isPathInfoEnabled()
    {
        if (!isset($this->_isPathInfoEnabled)) {
            $this->_isPathInfoEnabled = Config::getVar('general', 'disable_path_info') ? false : true;
        }
        return $this->_isPathInfoEnabled;
    }

    /**
     * Return true if RESTFUL_URLS is enabled.
     */
    public function isRestfulUrlsEnabled()
    {
        if (!isset($this->_isRestfulUrlsEnabled)) {
            $this->_isRestfulUrlsEnabled = Config::getVar('general', 'restful_urls') ? true : false;
        }
        return $this->_isRestfulUrlsEnabled;
    }

    /**
     * Get site data.
     *
     */
    public function getSite(): ?Site
    {
        $site = & Registry::get('site', true, null);
        return $site ??= DAORegistry::getDAO('SiteDAO')->getSite();
    }

    /**
     * Get the user session associated with the current request.
     */
    public function getSession(): Session
    {
        $session = & Registry::get('session', true, null);
        return $session ??= SessionManager::getManager()->getUserSession();
    }

    /**
     * Get the user associated with the current request.
     */
    public function getUser(): ?User
    {
        $user = & Registry::get('user', true, null);
        if ($user) {
            return $user;
        }

        // Attempt to load user from API token
        if (($handler = $this->getRouter()->getHandler())
            && ($token = $handler->getApiToken())
            && ($apiUser = Repo::user()->getByApiKey($token))
            && $apiUser->getData('apiKeyEnabled')
        ) {
            return $user = $apiUser;
        }

        // Attempts to retrieve a logged user
        if (Validation::isLoggedIn()) {
            $user = SessionManager::getManager()->getUserSession()->getUser();
        }

        return $user;
    }

    /**
     * Get the value of a GET/POST variable.
     */
    public function getUserVar($key)
    {
        // special treatment for APIRouter. APIHandler gets to fetch parameter first
        $router = $this->getRouter();
        if ($router instanceof \PKP\core\APIRouter && (!is_null($handler = $router->getHandler()))) {
            $handler = $router->getHandler();
            $value = $handler->getParameter($key);
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
     *
     * @return array
     */
    public function &getUserVars()
    {
        $this->_requestVars ??= array_map(fn ($s) => is_string($s) ? trim($s) : $s, array_merge($_GET, $_POST));
        return $this->_requestVars;
    }

    /**
     * Get the value of a GET/POST variable generated using the Smarty
     * html_select_date and/or html_select_time function.
     *
     * @param string $prefix
     * @param int $defaultDay
     * @param int $defaultMonth
     * @param int $defaultYear
     * @param int $defaultHour
     * @param int $defaultMinute
     * @param int $defaultSecond
     *
     * @return Date
     */
    public function getUserDateVar($prefix, $defaultDay = null, $defaultMonth = null, $defaultYear = null, $defaultHour = 0, $defaultMinute = 0, $defaultSecond = 0)
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
    public function getCookieVar($key)
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
     * @param string $key
     * @param int $expire (optional)
     */
    public function setCookieVar($key, $value, $expire = 0)
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
     * @param array $context The optional contextual paths
     * @param string $page The name of the op to redirect to.
     * @param string $op optional The name of the op to redirect to.
     * @param mixed $path string or array containing path info for redirect.
     * @param array $params Map of name => value pairs for additional parameters
     * @param string $anchor Name of desired anchor on the target page
     */
    public function redirect($context = null, $page = null, $op = null, $path = null, $params = null, $anchor = null)
    {
        $dispatcher = $this->getDispatcher();
        $this->redirectUrl($dispatcher->url($this, PKPApplication::ROUTE_PAGE, $context, $page, $op, $path, $params, $anchor));
    }

    /**
     * Get the current "context" (press/journal/etc) object.
     *
     * @see PKPPageRouter::getContext()
     */
    public function &getContext(): ?Context
    {
        return $this->_delegateToRouter('getContext');
    }

    /**
     * Deprecated
     *
     * @see PKPPageRouter::getRequestedContextPath()
     *
     * @param null|mixed $contextLevel
     */
    public function getRequestedContextPath($contextLevel = null)
    {
        // Emulate the old behavior of getRequestedContextPath for
        // backwards compatibility.
        if (is_null($contextLevel)) {
            return $this->_delegateToRouter('getRequestedContextPaths');
        } else {
            return [$this->_delegateToRouter('getRequestedContextPath', $contextLevel)];
        }
    }

    /**
     * Deprecated
     *
     * @see PKPPageRouter::getRequestedPage()
     */
    public function getRequestedPage()
    {
        return $this->_delegateToRouter('getRequestedPage');
    }

    /**
     * Deprecated
     *
     * @see PKPPageRouter::getRequestedOp()
     */
    public function getRequestedOp()
    {
        return $this->_delegateToRouter('getRequestedOp');
    }

    /**
     * Deprecated
     *
     * @see PKPPageRouter::getRequestedArgs()
     */
    public function getRequestedArgs()
    {
        return $this->_delegateToRouter('getRequestedArgs');
    }

    /**
     * Deprecated
     *
     * @see PKPPageRouter::url()
     *
     * @param null|mixed $context
     * @param null|mixed $page
     * @param null|mixed $op
     * @param null|mixed $path
     * @param null|mixed $params
     * @param null|mixed $anchor
     */
    public function url(
        $context = null,
        $page = null,
        $op = null,
        $path = null,
        $params = null,
        $anchor = null,
        $escape = false
    ) {
        return $this->_delegateToRouter(
            'url',
            $context,
            $page,
            $op,
            $path,
            $params,
            $anchor,
            $escape
        );
    }

    /**
     * This method exists to maintain backwards compatibility
     * with calls to methods that have been factored into the
     * Router implementations.
     *
     * It delegates the call to the router and returns the result.
     *
     * NB: This method is protected and may not be used by
     * external classes. It should also only be used in legacy
     * methods.
     *
     * @return mixed depends on the called method
     */
    public function &_delegateToRouter($method)
    {
        // This call is deprecated. We don't trigger a
        // deprecation error, though, as there are so
        // many instances of this error that it has a
        // performance impact and renders the error
        // log virtually useless when deprecation
        // warnings are switched on.
        // FIXME: Fix enough instances of this error so that
        // we can put a deprecation warning in here.
        $router = $this->getRouter();

        if (is_null($router)) {
            assert(false);
            $nullValue = null;
            return $nullValue;
        }

        // Construct the method call
        $callable = [$router, $method];

        // Get additional parameters but replace
        // the first parameter (currently the
        // method to be called) with the request
        // as all router methods required the request
        // as their first parameter.
        $parameters = func_get_args();
        $parameters[0] = & $this;

        $returner = call_user_func_array($callable, $parameters);
        return $returner;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\core\PKPRequest', '\PKPRequest');
}
