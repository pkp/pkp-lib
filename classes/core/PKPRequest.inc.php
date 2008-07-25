<?php

/**
 * @file classes/core/PKPRequest.inc.php
 *
 * Copyright (c) 2000-2008 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPRequest
 * @ingroup core
 *
 * @brief Class providing operations associated with HTTP requests.
 */

// $Id$


// The base script through which all requests are routed
define('INDEX_SCRIPTNAME', 'index.php');

class PKPRequest {
	/**
	 * Perform an HTTP redirect to an absolute or relative (to base system URL) URL.
	 * @param $url string (exclude protocol for local redirects) 
	 */
	function redirectUrl($url) {
		if (HookRegistry::call('Request::redirect', array(&$url))) {
			return;
		}

		header("Location: $url");
		exit();
	}

	/**
	 * Redirect to the current URL, forcing the HTTPS protocol to be used.
	 */
	function redirectSSL() {
		$url = 'https://' . PKPRequest::getServerHost() . PKPRequest::getRequestPath();
		$queryString = PKPRequest::getQueryString();
		if (!empty($queryString)) $url .= "?$queryString";
		PKPRequest::redirectUrl($url);
	}

	/**
	 * Redirect to the current URL, forcing the HTTP protocol to be used.
	 */
	function redirectNonSSL() {
		$url = 'http://' . PKPRequest::getServerHost() . PKPRequest::getRequestPath();
		$queryString = PKPRequest::getQueryString();
		if (!empty($queryString)) $url .= "?$queryString";
		PKPRequest::redirectUrl($url);
	}	

	/**
	 * Get the base URL of the request (excluding script).
	 * @return string
	 */
	function getBaseUrl() {
		static $baseUrl;

		if (!isset($baseUrl)) {
			$serverHost = PKPRequest::getServerHost(null);
			if ($serverHost !== null) {
				// Auto-detection worked.
				$baseUrl = PKPRequest::getProtocol() . '://' . PKPRequest::getServerHost() . PKPRequest::getBasePath();
			} else {
				// Auto-detection didn't work (e.g. this is a command-line call); use configuration param
				$baseUrl = Config::getVar('general', 'base_url');
			}
			HookRegistry::call('Request::getBaseUrl', array(&$baseUrl));
		}

		return $baseUrl;
	}

	/**
	 * Get the base path of the request (excluding trailing slash).
	 * @return string
	 */
	function getBasePath() {
		static $basePath;

		if (!isset($basePath)) {
			$basePath = dirname($_SERVER['SCRIPT_NAME']);
			if ($basePath == '/' || $basePath == '\\') {
				$basePath = '';
			}
			HookRegistry::call('Request::getBasePath', array(&$basePath));
		}

		return $basePath;
	}

	/**
	 * Get the URL to the index script.
	 * @return string
	 */
	function getIndexUrl() {
		static $indexUrl;

		if (!isset($indexUrl)) {
			$indexUrl = PKPRequest::getBaseUrl() . '/' . INDEX_SCRIPTNAME;
			HookRegistry::call('Request::getIndexUrl', array(&$indexUrl));
		}

		return $indexUrl;
	}

	/**
	 * Get the complete URL to this page, including parameters.
	 * @return string
	 */
	function getCompleteUrl() {
		static $completeUrl;

		if (!isset($completeUrl)) {
			$completeUrl = PKPRequest::getRequestUrl();
			$queryString = PKPRequest::getQueryString();
			if (!empty($queryString)) $completeUrl .= "?$queryString";
			HookRegistry::call('Request::getCompleteUrl', array(&$completeUrl));
		}

		return $completeUrl;
	}

	/**
	 * Get the complete URL of the request.
	 * @return string
	 */
	function getRequestUrl() {
		static $requestUrl;

		if (!isset($requestUrl)) {
			$requestUrl = PKPRequest::getProtocol() . '://' . PKPRequest::getServerHost() . PKPRequest::getRequestPath();
			HookRegistry::call('Request::getRequestUrl', array(&$requestUrl));
		}

		return $requestUrl;
	}

	/**
	 * Get the complete set of URL parameters to the current request.
	 * @return string
	 */
	function getQueryString() {
		static $queryString;

		if (!isset($queryString)) {
			$queryString = isset($_SERVER['QUERY_STRING'])?$_SERVER['QUERY_STRING']:'';
			HookRegistry::call('Request::getQueryString', array(&$queryString));
		}

		return $queryString;
	}

	/**
	 * Get the completed path of the request.
	 * @return string
	 */
	function getRequestPath() {
		static $requestPath;
		if (!isset($requestPath)) {
			$requestPath = $_SERVER['SCRIPT_NAME'];
			if (PKPRequest::isPathInfoEnabled()) {
				$requestPath .= isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
			}
			HookRegistry::call('Request::getRequestPath', array(&$requestPath));
		}
		return $requestPath;
	}

	/**
	 * Get the server hostname in the request.
	 * @return string
	 */
	function getServerHost($default = 'localhost') {
		static $serverHost;
		if (!isset($serverHost)) {
			$serverHost = isset($_SERVER['HTTP_X_FORWARDED_HOST']) ? $_SERVER['HTTP_X_FORWARDED_HOST']
				: (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST']
				: (isset($_SERVER['HOSTNAME']) ? $_SERVER['HOSTNAME']
				: $default));
			HookRegistry::call('Request::getServerHost', array(&$serverHost));
		}
		return $serverHost;
	}

	/**
	 * Get the protocol used for the request (HTTP or HTTPS).
	 * @return string
	 */
	function getProtocol() {
		static $protocol;
		if (!isset($protocol)) {
			$protocol = (!isset($_SERVER['HTTPS']) || strtolower($_SERVER['HTTPS']) != 'on') ? 'http' : 'https';
			HookRegistry::call('Request::getProtocol', array(&$protocol));
		}
		return $protocol;
	}

	/**
	 * Get the request method
	 * @return string
	 */
	function getRequestMethod() {
		return $_SERVER['REQUEST_METHOD'];
	}

	/**
	 * Determine whether the request is a POST request
	 * @return boolean
	 */
	function isPost() {
		return (PKPRequest::getRequestMethod() == 'POST');
	}

	/**
	 * Determine whether the request is a GET request
	 * @return boolean
	 */
	function isGet() {
		return (PKPRequest::getRequestMethod() == 'GET');
	}

	/**
	 * Get the remote IP address of the current request.
	 * @return string
	 */
	function getRemoteAddr() {
		static $ipaddr;
		if (!isset($ipaddr)) {
			if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
				$ipaddr = $_SERVER['HTTP_X_FORWARDED_FOR'];
			} else if (isset($_SERVER['REMOTE_ADDR'])) {
				$ipaddr = $_SERVER['REMOTE_ADDR'];
			}
			if (!isset($ipaddr) || empty($ipaddr)) {
				$ipaddr = getenv('REMOTE_ADDR');
			}
			if (!isset($ipaddr) || $ipaddr == false) {
				$ipaddr = '';
			}

			// If multiple addresses are listed, take the first. (Supports ipv6.)
			if (preg_match('/^([0-9.a-fA-F:]+)/', $ipaddr, $matches)) {
				$ipaddr = $matches[1];
			}
			HookRegistry::call('Request::getRemoteAddr', array(&$ipaddr));
		}
		return $ipaddr;
	}

	/**
	 * Get the remote domain of the current request
	 * @return string
	 */
	function getRemoteDomain() {
		static $remoteDomain;
		if (!isset($remoteDomain)) {
			$remoteDomain = null;
			$remoteDomain = @getHostByAddr(PKPRequest::getRemoteAddr());
			HookRegistry::call('Request::getRemoteDomain', array(&$remoteDomain));
		}
		return $remoteDomain;
	}

	/**
	 * Get the user agent of the current request.
	 * @return string
	 */
	function getUserAgent() {
		static $userAgent;
		if (!isset($userAgent)) {
			if (isset($_SERVER['HTTP_USER_AGENT'])) {
				$userAgent = $_SERVER['HTTP_USER_AGENT'];
			}
			if (!isset($userAgent) || empty($userAgent)) {
				$userAgent = getenv('HTTP_USER_AGENT');
			}
			if (!isset($userAgent) || $userAgent == false) {
				$userAgent = '';
			}
			HookRegistry::call('Request::getUserAgent', array(&$userAgent));
		}
		return $userAgent;
	}

	/**
	 * Determine whether a user agent is a bot or not using an external
	 * list of regular expressions.
	 */
	function isBot() {
		static $isBot;
		if (!isset($isBot)) {
			$userAgent = PKPRequest::getUserAgent();
			$isBot = false;
			$userAgentsFile = Config::getVar('general', 'registry_dir') . DIRECTORY_SEPARATOR . 'botAgents.txt';
			$regexps = array_filter(file($userAgentsFile), create_function('&$a', 'return ($a = trim($a)) && !empty($a) && $a[0] != \'#\';'));
			foreach ($regexps as $regexp) {
				if (String::regexp_match($regexp, $userAgent)) {
					$isBot = true;
					return $isBot;
				}
			}
		}
		return $isBot;
	}

	/**
	 * Return true iff PATH_INFO is enabled.
	 */
	function isPathInfoEnabled() {
		static $isPathInfoEnabled;
		if (!isset($isPathInfoEnabled)) {
			$isPathInfoEnabled = Config::getVar('general', 'disable_path_info')?false:true;
		}
		return $isPathInfoEnabled;
	}

	/**
	 * Get site data.
	 * @return Site
	 */
	function &getSite() {
		static $site;

		if (!isset($site)) {
			$siteDao = &DAORegistry::getDAO('SiteDAO');
			$site = $siteDao->getSite();
		}

		return $site;
	}

	/**
	 * Get the user session associated with the current request.
	 * @return Session
	 */
	function &getSession() {
		static $session;

		if (!isset($session)) {
			$sessionManager = &SessionManager::getManager();
			$session = $sessionManager->getUserSession();
		}

		return $session;
	}

	/**
	 * Get the user associated with the current request.
	 * @return User
	 */
	function &getUser() {
		static $user;

		if (!isset($user)) {
			$sessionManager = &SessionManager::getManager();
			$session = &$sessionManager->getUserSession();
			$user = $session->getUser();
		}

		return $user;
	}

	/**
	 * Get the page requested in the URL.
	 * @return String the page path (under the "pages" directory)
	 */
	function getRequestedPage() {
		static $page;

		if (!isset($page)) {
			if (PKPRequest::isPathInfoEnabled()) {
				$application = PKPApplication::getApplication();
				$contextDepth = $application->getContextDepth();
				$page = '';
				if (isset($_SERVER['PATH_INFO'])) {
					$vars = explode('/', $_SERVER['PATH_INFO']);
					if (count($vars) > $contextDepth+1) {
						$page = Core::cleanFileVar($vars[$contextDepth+1]);
					}
				}
			} else {
				$page = PKPRequest::getUserVar('page');
			}
		}

		return $page;
	}

	/**
	 * Get the operation requested in the URL (assumed to exist in the requested page handler).
	 * @return string
	 */
	function getRequestedOp() {
		static $op;

		if (!isset($op)) {
			if (PKPRequest::isPathInfoEnabled()) {
				$application = PKPApplication::getApplication();
				$contextDepth = $application->getContextDepth();
				$op = '';
				if (isset($_SERVER['PATH_INFO'])) {
					$vars = explode('/', $_SERVER['PATH_INFO']);
					if (count($vars) > $contextDepth+2) {
						$op = Core::cleanFileVar($vars[$contextDepth+2]);
					}
				}
			} else {
				return PKPRequest::getUserVar('op');
			}
			$op = empty($op) ? 'index' : $op;
		}

		return $op;
	}

	/**
	 * Get the arguments requested in the URL (not GET/POST arguments, only arguments appended to the URL separated by "/").
	 * @return array
	 */
	function getRequestedArgs() {
		if (PKPRequest::isPathInfoEnabled()) {
			$args = array();
			if (isset($_SERVER['PATH_INFO'])) {
				$application = PKPApplication::getApplication();
				$contextDepth = $application->getContextDepth();
				$vars = explode('/', $_SERVER['PATH_INFO']);
				if (count($vars) > $contextDepth+3) {
					$args = array_slice($vars, $contextDepth+3);
					for ($i=0, $count=count($args); $i<$count; $i++) {
						$args[$i] = Core::cleanVar(get_magic_quotes_gpc() ? stripslashes($args[$i]) : $args[$i]);
					}
				}
			}
		} else {
			$args = PKPRequest::getUserVar('path');
			if (empty($args)) $args = array();
			elseif (!is_array($args)) $args = array($args);
		}
		return $args;	
	}

	/**
	 * Get the value of a GET/POST variable.
	 * @return mixed
	 */
	function getUserVar($key) {
		static $vars;

		if (!isset($vars)) {
			$vars = array_merge($_GET, $_POST);
		}

		if (isset($vars[$key])) {
			// FIXME Do not clean vars again if function is called more than once?
			PKPRequest::cleanUserVar($vars[$key]);
			return $vars[$key];
		} else {
			return null;
		}
	}

	/**
	 * Get the value of a GET/POST variable generated using the Smarty
	 * html_select_date and/or html_select_time function.
	 * @param $prefix string
	 * @param $defaultDay int
	 * @param $defaultMonth int
	 * @param $defaultYear int
	 * @param $defaultHour int
	 * @param $defaultMinute int
	 * @param $defaultSecond int
	 * @return Date
	 */
	function getUserDateVar($prefix, $defaultDay = null, $defaultMonth = null, $defaultYear = null, $defaultHour = 0, $defaultMinute = 0, $defaultSecond = 0) {
		$monthPart = PKPRequest::getUserVar($prefix . 'Month');
		$dayPart = PKPRequest::getUserVar($prefix . 'Day');
		$yearPart = PKPRequest::getUserVar($prefix . 'Year');
		$hourPart = PKPRequest::getUserVar($prefix . 'Hour');
		$minutePart = PKPRequest::getUserVar($prefix . 'Minute');
		$secondPart = PKPRequest::getUserVar($prefix . 'Second');

		switch (PKPRequest::getUserVar($prefix . 'Meridian')) {
			case 'pm':
				if (is_numeric($hourPart) && $hourPart != 12) $hourPart += 12;
				break;
			case 'am':
			default:	
				// Do nothing.
				break;
		}

		if (empty($dayPart)) $dayPart = $defaultDay;
		if (empty($monthPart)) $monthPart = $defaultMonth;
		if (empty($yearPart)) $yearPart = $defaultYear;
		if (empty($hourPart)) $hourPart = $defaultHour;
		if (empty($minutePart)) $minutePart = $defaultMinute;
		if (empty($secondPart)) $secondPart = $defaultSecond;

		if (empty($monthPart) || empty($dayPart) || empty($yearPart)) return null;
		return mktime($hourPart, $minutePart, $secondPart, $monthPart, $dayPart, $yearPart);
	}

	/**
	 * Sanitize a user-submitted variable (i.e., GET/POST/Cookie variable).
	 * Strips slashes if necessary, then sanitizes variable as per Core::cleanVar().
	 * @param $var mixed
	 */
	function cleanUserVar(&$var, $stripHtml = false) {
		if (isset($var) && is_array($var)) {
			foreach ($var as $key => $value) {
				PKPRequest::cleanUserVar($var[$key], $stripHtml);
			}
		} else if (isset($var)) {
			$var = Core::cleanVar(get_magic_quotes_gpc() ? stripslashes($var) : $var);

		} else {
			return null;
		}
	}

	/**
	 * Get the value of a cookie variable.
	 * @return mixed
	 */
	function getCookieVar($key) {
		if (isset($_COOKIE[$key])) {
			$value = $_COOKIE[$key];
			PKPRequest::cleanUserVar($value);
			return $value;
		} else {
			return null;
		}
	}

	/**
	 * Set a cookie variable.
	 * @param $key string
	 * @param $value mixed
	 */
	function setCookieVar($key, $value) {
		setcookie($key, $value, 0, PKPRequest::getBasePath());
		$_COOKIE[$key] = $value;
	}

	/**
	 * Build a URL into OJS.
	 * @param $journalPath string Optional path for journal to use
	 * @param $page string Optional name of page to invoke
	 * @param $op string Optional name of operation to invoke
	 * @param $path mixed Optional string or array of args to pass to handler
	 * @param $params array Optional set of name => value pairs to pass as user parameters
	 * @param $anchor string Optional name of anchor to add to URL
	 * @param $escape boolean Whether or not to escape ampersands for this URL; default false.
	 */
/*	function url($journalPath = null, $page = null, $op = null, $path = null, $params = null, $anchor = null, $escape = false) {
		$pathInfoDisabled = !PKPRequest::isPathInfoEnabled();

		$amp = $escape?'&amp;':'&';
		$prefix = $pathInfoDisabled?$amp:'?';

		// Establish defaults for page and op
		$defaultPage = PKPRequest::getRequestedPage();
		$defaultOp = PKPRequest::getRequestedOp();

		// If a journal has been specified, don't supply default
		// page or op.
		if ($journalPath) {
			$journalPath = rawurlencode($journalPath);
			$defaultPage = null;
			$defaultOp = null;
		} else {
			$journal =& PKPRequest::getJournal();
			if ($journal) $journalPath = $journal->getPath();
			else $journalPath = 'index';
		}

		// Get overridden base URLs (if available).
		$overriddenBaseUrl = Config::getVar('general', "base_url[$journalPath]");

		// If a page has been specified, don't supply a default op.
		if ($page) {
			$page = rawurlencode($page);
			$defaultOp = null;
		} else {
			$page = $defaultPage;
		}

		// Encode the op.
		if ($op) $op = rawurlencode($op);
		else $op = $defaultOp;

		// Process additional parameters
		$additionalParams = '';
		if (!empty($params)) foreach ($params as $key => $value) {
			if (is_array($value)) foreach($value as $element) {
				$additionalParams .= $prefix . $key . '%5B%5D=' . rawurlencode($element);
				$prefix = $amp;
			} else {
				$additionalParams .= $prefix . $key . '=' . rawurlencode($value);
				$prefix = $amp;
			}
		}

		// Process anchor
		if (!empty($anchor)) $anchor = '#' . rawurlencode($anchor);
		else $anchor = '';

		if (!empty($path)) {
			if (is_array($path)) $path = array_map('rawurlencode', $path);
			else $path = array(rawurlencode($path));
			if (!$page) $page = 'index';
			if (!$op) $op = 'index';
		}

		$pathString = '';
		if ($pathInfoDisabled) {
			$joiner = $amp . 'path%5B%5D=';
			if (!empty($path)) $pathString = $joiner . implode($joiner, $path);
			if (empty($overriddenBaseUrl)) $baseParams = "?journal=$journalPath";
			else $baseParams = '';

			if (!empty($page) || !empty($overriddenBaseUrl)) {
				$baseParams .= empty($baseParams)?'?':$amp . "page=$page";
				if (!empty($op)) {
					$baseParams .= $amp . "op=$op";
				}
			}
		} else {
			if (!empty($path)) $pathString = '/' . implode('/', $path);
			if (empty($overriddenBaseUrl)) $baseParams = "/$journalPath";
			else $baseParams = '';

			if (!empty($page)) {
				$baseParams .= "/$page";
				if (!empty($op)) {
					$baseParams .= "/$op";
				}
			}
		}

		return ((empty($overriddenBaseUrl)?PKPRequest::getIndexUrl():$overriddenBaseUrl) . $baseParams . $pathString . $additionalParams . $anchor);
	}*/

/*	function isCacheable() {
		if (defined('SESSION_DISABLE_INIT')) return false;
		if (!Config::getVar('general', 'installed')) return false;
		if (!empty($_POST) || Validation::isLoggedIn()) return false;
		if (!Config::getVar('cache', 'web_cache')) return false;
		if (!PKPRequest::isPathInfoEnabled()) {
			$ok = array('journal', 'page', 'op', 'path');
			if (!empty($_GET) && count(array_diff(array_keys($_GET), $ok)) != 0) {
				return false;
			}
		} else {
			if (!empty($_GET)) return false;
		}

		if (in_array(PKPRequest::getRequestedPage(), array(
			'about', 'announcement', 'help', 'index', 'information', 'rt', 'issue', ''
		))) return true;

		return false;
	}

	function getCacheFilename() {
		static $cacheFilename;
		if (!isset($cacheFilename)) {
			if (PKPRequest::isPathInfoEnabled()) {
				$id = isset($_SERVER['PATH_INFO'])?$_SERVER['PATH_INFO']:'index';
				$id .= '-' . Locale::getLocale();
			} else {
				$id = PKPRequest::getUserVar('journal') . '-' . PKPRequest::getUserVar('page') . '-' . PKPRequest::getUserVar('op') . '-' . PKPRequest::getUserVar('path') . '-' . Locale::getLocale();
			}
			$path = dirname(dirname(dirname(__FILE__)));
			$cacheFilename = $path . '/cache/wc-' . md5($id) . '.html';
		}
		return $cacheFilename;
	}

	function cacheContent($contents) {
		$filename = PKPRequest::getCacheFilename();
		$fp = fopen($filename, 'w');
		if ($fp) {
			fwrite($fp, mktime() . ':' . $contents);
			fclose($fp);
		}
		return $contents;
	}

	function displayCached() {
		$filename = PKPRequest::getCacheFilename();
		if (!file_exists($filename)) return false;

		$fp = fopen($filename, 'r');
		$data = fread($fp, filesize($filename));
		fclose($fp);

		$i = strpos($data, ':');
		$time = substr($data, 0, $i);
		$contents = substr($data, $i+1);

		if (mktime() > $time + Config::getVar('cache', 'web_cache_hours') * 60 * 60) return false;

		header('Content-Type: text/html; charset=' . Config::getVar('i18n', 'client_charset'));

		echo $contents;
		return true;
	}*/
}

?>
