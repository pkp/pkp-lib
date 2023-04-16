<?php

/**
 * @defgroup core Core
 * Core web application concerns such as routing, dispatching, etc.
 */

/**
 * @file classes/core/Core.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Core
 *
 * @ingroup core
 *
 * @brief Class containing system-wide functions.
 */

namespace PKP\core;

define('PKP_LIB_PATH', 'lib/pkp');
define('COUNTER_USER_AGENTS_FILE', Core::getBaseDir() . '/' . PKP_LIB_PATH . '/lib/counterBots/generated/COUNTER_Robots_list.txt');

use Illuminate\Support\Str;
use PKP\cache\CacheManager;
use PKP\cache\FileCache;
use PKP\config\Config;
use SplFileInfo;
use Symfony\Component\Finder\Finder;

class Core
{
    /** @var array The regular expressions that will find a bot user agent */
    public static $botRegexps = [];

    /**
     * Get the path to the base installation directory.
     *
     * @return string
     */
    public static function getBaseDir()
    {
        static $baseDir;
        return $baseDir ??= dirname(INDEX_FILE_LOCATION);
    }

    /**
     * Sanitize a value to be used in a file path.
     * Removes any characters except alphanumeric characters, underscores, and dashes.
     *
     * @param string $var
     *
     * @return string
     */
    public static function cleanFileVar($var)
    {
        return cleanFileVar($var);
    }

    /**
     * Return the current date in ISO (YYYY-MM-DD HH:MM:SS) format.
     *
     * @param int $ts optional, use specified timestamp instead of current time
     *
     * @return string
     */
    public static function getCurrentDate($ts = null)
    {
        return date('Y-m-d H:i:s', $ts ?? time());
    }

    /**
     * Return *nix timestamp with microseconds (in units of seconds).
     *
     * @return float
     */
    public static function microtime()
    {
        [$usec, $sec] = explode(' ', microtime());
        return (float)$sec + (float)$usec;
    }

    /**
     * Check if the server platform is Windows.
     *
     * @return bool
     */
    public static function isWindows()
    {
        return strtolower_codesafe(substr(PHP_OS, 0, 3)) == 'win';
    }

    /**
     * Checks to see if a PHP module is enabled.
     *
     * @param string $moduleName
     *
     * @return bool
     */
    public static function checkGeneralPHPModule($moduleName)
    {
        if (extension_loaded($moduleName)) {
            return true;
        }
        return false;
    }

    /**
     * Check the passed user agent for a bot.
     *
     * @param string $userAgent
     * @param string $botRegexpsFile An alternative file with regular
     * expressions to find bots inside user agent strings.
     *
     * @return bool
     */
    public static function isUserAgentBot($userAgent, $botRegexpsFile = COUNTER_USER_AGENTS_FILE)
    {
        static $botRegexps;
        Registry::set('currentUserAgentsFile', $botRegexpsFile);

        if (!isset($botRegexps[$botRegexpsFile])) {
            $botFileCacheId = md5($botRegexpsFile);
            $cacheManager = CacheManager::getManager();
            /** @var FileCache */
            $cache = $cacheManager->getCache('core', $botFileCacheId, ['Core', '_botFileListCacheMiss'], CACHE_TYPE_FILE);
            $botRegexps[$botRegexpsFile] = $cache->getContents();
        }

        foreach ($botRegexps[$botRegexpsFile] as $regexp) {
            // make the search case insensitive
            $regexp .= 'i';
            if (PKPString::regexp_match($regexp, $userAgent)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get context path present into the passed
     * url information.
     *
     * @param string $urlInfo Full url or just path info.
     */
    public static function getContextPath(string $urlInfo): string
    {
        $contextPaths = explode('/', trim($urlInfo, '/'), 2);
        return self::cleanFileVar($contextPaths[0] ?: 'index');
    }

    /**
     * Get the page present into
     * the passed url information. It expects that urls
     * were built using the system.
     *
     * @param string $urlInfo Full url or just path info.
     * @param bool $isPathInfo Tell if the
     * passed url info string is a path info or not.
     * @param array $userVars (optional) Pass GET variables
     * if needed (for testing only).
     *
     * @return string
     */
    public static function getPage($urlInfo, $isPathInfo, $userVars = [])
    {
        $page = Core::_getUrlComponents($urlInfo, $isPathInfo, 0, 'page', $userVars);
        return Core::cleanFileVar(is_null($page) ? '' : $page);
    }

    /**
     * Get the operation present into
     * the passed url information. It expects that urls
     * were built using the system.
     *
     * @param string $urlInfo Full url or just path info.
     * @param bool $isPathInfo Tell if the
     * passed url info string is a path info or not.
     * @param array $userVars (optional) Pass GET variables
     * if needed (for testing only).
     *
     * @return string
     */
    public static function getOp($urlInfo, $isPathInfo, $userVars = [])
    {
        $operation = Core::_getUrlComponents($urlInfo, $isPathInfo, 1, 'op', $userVars);
        return Core::cleanFileVar(empty($operation) ? 'index' : $operation);
    }

    /**
     * Get the arguments present into
     * the passed url information (not GET/POST arguments,
     * only arguments appended to the URL separated by "/").
     * It expects that urls were built using the system.
     *
     * @param string $urlInfo Full url or just path info.
     * @param bool $isPathInfo Tell if the
     * passed url info string is a path info or not.
     * @param array $userVars (optional) Pass GET variables
     * if needed (for testing only).
     *
     * @return array
     */
    public static function getArgs($urlInfo, $isPathInfo, $userVars = [])
    {
        return Core::_getUrlComponents($urlInfo, $isPathInfo, 2, 'path', $userVars);
    }

    /**
     * Remove base url from the passed url, if any.
     * Also, if true, checks for the context path in
     * url and if it's missing, tries to add it.
     *
     * @param string $url
     *
     * @return string|bool The url without base url,
     * false if it was not possible to remove it.
     */
    public static function removeBaseUrl($url)
    {
        [$baseUrl, $contextPath] = Core::_getBaseUrlAndPath($url);

        if (!$baseUrl) {
            return false;
        }

        // Remove base url from url, if any.
        $url = str_replace($baseUrl, '', $url);

        // If url doesn't have the entire protocol and host part,
        // remove any possible base url path from url.
        $baseUrlPath = parse_url($baseUrl, PHP_URL_PATH);
        if ($baseUrlPath == $url) {
            // Access to the base url, no context, the entire
            // url is part of the base url and we can return empty.
            $url = '';
        } else {
            // Handle case where index.php was removed by rewrite rules,
            // and we have base url followed by the args.
            if (strpos($url, $baseUrlPath . '?') === 0) {
                $replacement = '?'; // Url path replacement.
                $baseSystemEscapedPath = preg_quote($baseUrlPath . '?', '/');
            } else {
                $replacement = '/'; // Url path replacement.
                $baseSystemEscapedPath = preg_quote($baseUrlPath . '/', '/');
            }
            $url = preg_replace('/^' . $baseSystemEscapedPath . '/', $replacement, $url);

            // Remove possible index.php page from url.
            $url = str_replace('/index.php', '', $url);
        }

        if ($contextPath) {
            // We found the contextPath using the base_url
            // config file settings. Check if the url starts
            // with the context path, if not, prepend it.
            if (strpos($url, '/' . $contextPath . '/') !== 0) {
                $url = '/' . $contextPath . $url;
            }
        }

        // Remove any possible trailing slashes.
        $url = rtrim($url, '/');

        return $url;
    }

    /**
     * Try to get the base url and, if configuration
     * is set to use base url override, context
     * path for the passed url.
     *
     * @param string $url
     *
     * @return array With two elements, base url and context path.
     */
    protected static function _getBaseUrlAndPath($url)
    {
        $baseUrl = false;
        $contextPath = false;

        // Check for override base url settings.
        $contextBaseUrls = Config::getContextBaseUrls();

        if (empty($contextBaseUrls)) {
            $baseUrl = Config::getVar('general', 'base_url');
        } else {
            // We are just interested in context base urls, remove the index one.
            if (isset($contextBaseUrls['index'])) {
                unset($contextBaseUrls['index']);
            }

            // Arrange them in length order, so we make sure
            // we get the correct one, in case there's an overlaping
            // of contexts, eg.:
            // base_url[context1] = http://somesite.com/
            // base_url[context2] = http://somesite.com/context2
            $sortedBaseUrls = array_combine($contextBaseUrls, array_map('strlen', $contextBaseUrls));
            arsort($sortedBaseUrls);

            foreach (array_keys($sortedBaseUrls) as $workingBaseUrl) {
                $urlHost = parse_url($url, PHP_URL_HOST);
                if (is_null($urlHost)) {
                    // Check the base url without the host part.
                    $baseUrlHost = parse_url($workingBaseUrl, PHP_URL_HOST);
                    if (is_null($baseUrlHost)) {
                        break;
                    }
                    $baseUrlToSearch = substr($workingBaseUrl, strpos($workingBaseUrl, $baseUrlHost) + strlen($baseUrlHost));
                    // Base url with only host part, add trailing slash
                    // so it can be checked below.
                    if (!$baseUrlToSearch) {
                        $baseUrlToSearch = '/';
                    }
                } else {
                    $baseUrlToSearch = $workingBaseUrl;
                }

                $baseUrlCheck = Core::_checkBaseUrl($baseUrlToSearch, $url);
                if (is_null($baseUrlCheck)) {
                    // Can't decide. Stop searching.
                    break;
                } elseif ($baseUrlCheck === true) {
                    $contextPath = array_search($workingBaseUrl, $contextBaseUrls);
                    $baseUrl = $workingBaseUrl;
                    break;
                }
            }
        }

        // If we still have no base URL, this may be a situation where we have an install with some customized URLs, and some not.
        // Return the default base URL.

        if (!$baseUrl) {
            $baseUrl = Config::getVar('general', 'base_url');
        }

        return [$baseUrl, $contextPath];
    }

    /**
     * Check if the passed base url is part of
     * the passed url, based on the context base url
     * configuration. Both parameters can represent
     * full url (host plus path) or just the path,
     * but they have to be consistent.
     *
     * @param string $baseUrl Full base url
     * or just it's path info.
     * @param string $url Full url or just it's
     * path info.
     *
     * @return bool
     */
    protected static function _checkBaseUrl($baseUrl, $url)
    {
        // Check if both base url and url have host
        // component or not.
        $baseUrlHasHost = (bool) parse_url($baseUrl, PHP_URL_HOST);
        $urlHasHost = (bool) parse_url($url, PHP_URL_HOST);
        if ($baseUrlHasHost !== $urlHasHost) {
            return false;
        }

        $contextBaseUrls = & Config::getContextBaseUrls();

        // If the base url is found inside the passed url,
        // then we might found the right context path.
        if (strpos($url, $baseUrl) === 0) {
            if (strpos($url, '/index.php') == strlen($baseUrl) - 1) {
                // index.php appears right after the base url,
                // no more possible paths.
                return true;
            } else {
                // Still have to check if there is no other context
                // base url that combined with it's context path is
                // equal to this base url. If it exists, we can't
                // tell which base url is contained in url.
                foreach ($contextBaseUrls as $contextPath => $workingBaseUrl) {
                    $urlToCheck = $workingBaseUrl . '/' . $contextPath;
                    if (!$baseUrlHasHost) {
                        $urlToCheck = parse_url($urlToCheck, PHP_URL_PATH);
                    }
                    if ($baseUrl == $urlToCheck) {
                        return null;
                    }
                }

                return true;
            }
        }

        return false;
    }

    /**
     * Bot list file cache miss fallback.
     * (WARNING: This function appears to be used externally, hence public despite _ prefix.)
     *
     * @param FileCache $cache
     *
     * @return array
     */
    public static function _botFileListCacheMiss($cache)
    {
        $id = $cache->getCacheId();
        $filteredBotRegexps = array_filter(
            file(Registry::get('currentUserAgentsFile')),
            function ($regexp) {
                $regexp = trim($regexp);
                return !empty($regexp) && $regexp[0] != '#';
            }
        );
        $botRegexps = array_map(
            function ($regexp) {
                $delimiter = '/';
                $regexp = trim($regexp);
                if (strpos($regexp, $delimiter) !== 0) {
                    // Make sure delimiters are in place.
                    $regexp = $delimiter . $regexp . $delimiter;
                }
                return $regexp;
            },
            $filteredBotRegexps
        );
        $cache->setEntireCache($botRegexps);
        return $botRegexps;
    }

    /**
     * Get passed variable value inside the passed url.
     *
     * @param string $url
     * @param string $varName
     * @param array $userVars
     *
     * @return string|null
     */
    private static function _getUserVar($url, $varName, $userVars = [])
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $userVarsFromUrl);
        return $userVarsFromUrl[$varName] ?? $userVars[$varName] ?? null;
    }

    /**
     * Get url components (page, operation and args)
     * based on the passed offset.
     *
     * @param string $urlInfo
     * @param string $isPathInfo
     * @param int $offset
     * @param string $varName
     * @param array $userVars (optional) GET variables
     * (only for testing).
     *
     * @return mixed array|string|null
     */
    private static function _getUrlComponents($urlInfo, $isPathInfo, $offset, $varName = '', $userVars = [])
    {
        $component = null;

        $isArrayComponent = false;
        if ($varName == 'path') {
            $isArrayComponent = true;
        }

        $vars = explode('/', trim($urlInfo ?? '', '/'));
        if (count($vars) > $offset + 1) {
            if ($isArrayComponent) {
                $component = array_slice($vars, $offset + 1);
            } else {
                $component = $vars[$offset + 1];
            }
        }

        if ($isArrayComponent) {
            if (empty($component)) {
                $component = [];
            } elseif (!is_array($component)) {
                $component = [$component];
            }
        }

        return $component;
    }

    /**
     * Extract the class name from the given file path.
     *
     * @param SplFileInfo $file info about a file extract class name from
     *
     * @return string fully qualified class name
     *
     * @see Finder
     */
    public static function classFromFile(SplFileInfo $file): string
    {
        $pathFromBase = trim(Str::replaceFirst(base_path(), '', $file->getRealPath()), '/');
        $libPath = 'lib/pkp/';
        $namespace = Str::startsWith($pathFromBase, $libPath) ? 'PKP\\' : 'APP\\';

        $path = $pathFromBase;
        if ($namespace === 'PKP\\') {
            $path = Str::replaceFirst($libPath, '', $path);
        }
        if (Str::startsWith($path, 'classes')) {
            $path = Str::replaceFirst('classes/', '', $path);
        }

        return $namespace . str_replace('/', '\\', Str::replaceLast('.php', '', $path));
    }
}
