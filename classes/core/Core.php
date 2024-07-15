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

use APP\core\Application;
use Exception;
use Illuminate\Support\Facades\Cache;
use PKP\config\Config;
use PKP\facades\Locale;
use SplFileInfo;

define('PKP_LIB_PATH', 'lib/pkp');
define('COUNTER_USER_AGENTS_FILE', Core::getBaseDir() . '/' . PKP_LIB_PATH . '/lib/counterBots/generated/COUNTER_Robots_list.txt');

class Core
{
    /**
     * Get the path to the base installation directory.
     */
    public static function getBaseDir(): string
    {
        static $baseDir;
        return $baseDir ??= dirname(INDEX_FILE_LOCATION);
    }

    /**
     * Sanitize a value to be used in a file path.
     * Removes any characters except alphanumeric characters, underscores, and dashes.
     */
    public static function cleanFileVar(string $var): string
    {
        return cleanFileVar($var);
    }

    /**
     * Return the current date in ISO (YYYY-MM-DD HH:MM:SS) format.
     *
     * @param int $ts optional, use specified timestamp instead of current time
     */
    public static function getCurrentDate(?int $ts = null): string
    {
        return date('Y-m-d H:i:s', $ts ?? time());
    }

    /**
     * Return *nix timestamp with microseconds (in units of seconds).
     */
    public static function microtime(): float
    {
        [$usec, $sec] = explode(' ', microtime());
        return (float)$sec + (float)$usec;
    }

    /**
     * Check if the server platform is Windows.
     */
    public static function isWindows(): bool
    {
        return strtolower(substr(PHP_OS, 0, 3)) == 'win';
    }

    /**
     * Check the passed user agent for a bot.
     *
     * @param $botRegexpsFile An alternative file with regular expressions to find bots inside user agent strings.
     */
    public static function isUserAgentBot(string $userAgent, string $botRegexpsFile = COUNTER_USER_AGENTS_FILE): bool
    {
        $botRegexps = Cache::remember('botUserAgents-' . md5($botRegexpsFile), 24 * 60 * 60, function () use ($botRegexpsFile) {
            $filteredBotRegexps = array_filter(
                file($botRegexpsFile),
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
            return $botRegexps;
        });

        foreach ($botRegexps as $regexp) {
            // make the search case insensitive
            $regexp .= 'ui';
            if (preg_match($regexp, $userAgent)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get context path present into the passed
     * url information.
     *
     * @param $urlInfo Full url or just path info.
     */
    public static function getContextPath(string $urlInfo): string
    {
        $contextPaths = explode('/', trim($urlInfo, '/'), 2);
        return self::cleanFileVar($contextPaths[0] ?: Application::SITE_CONTEXT_PATH);
    }

    /**
     * Get localization path present into the passed
     * url information.
     */
    public static function getLocalization(string $urlInfo): string
    {
        $locale = self::_getUrlComponents($urlInfo, 0);
        return Locale::isLocaleValid($locale) ? $locale : '';
    }

    /**
     * Get the page present into
     * the passed url information. It expects that urls
     * were built using the system.
     *
     * @param $urlInfo Full url or just path info.
     * @param $userVars (optional) Pass GET variables if needed (for testing only).
     */
    public static function getPage(string $urlInfo, array $userVars = []): string
    {
        $page = static::_getUrlComponents($urlInfo, self::_getOffset($urlInfo, 0), 'page', $userVars);
        return static::cleanFileVar($page ?? '');
    }

    /**
     * Get the operation present into the passed url information. It expects that urls were built using the system.
     *
     * @param $urlInfo Full url or just path info.
     * @param $userVars (optional) Pass GET variables if needed (for testing only).
     */
    public static function getOp(string $urlInfo, array $userVars = []): string
    {
        $operation = static::_getUrlComponents($urlInfo, self::_getOffset($urlInfo, 1), 'op', $userVars);
        return static::cleanFileVar($operation ?: 'index');
    }

    /**
     * Get the arguments present into the passed url information (not GET/POST arguments,
     * only arguments appended to the URL separated by "/").
     * It expects that urls were built using the system.
     *
     * @param $urlInfo Full url or just path info.
     * @param $userVars (optional) Pass GET variables if needed (for testing only).
     */
    public static function getArgs(string $urlInfo, array $userVars = []): array
    {
        return static::_getUrlComponents($urlInfo, self::_getOffset($urlInfo, 2), 'path', $userVars);
    }

    /**
     * Remove base url from the passed url, if any.
     * Also, if true, checks for the context path in
     * url and if it's missing, tries to add it.
     *
     *
     * @return string|null The url without base url, null if it was not possible to remove it.
     */
    public static function removeBaseUrl(string $url): ?string
    {
        [$baseUrl, $contextPath] = Core::_getBaseUrlAndPath($url);

        if (!$baseUrl) {
            return null;
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
     * @return Array with two elements, base url and context path.
     */
    protected static function _getBaseUrlAndPath(string $url): array
    {
        $baseUrl = false;
        $contextPath = false;

        // Check for override base url settings.
        $contextBaseUrls = Config::getContextBaseUrls();

        if (empty($contextBaseUrls)) {
            $baseUrl = Config::getVar('general', 'base_url');
        } else {
            // We are just interested in context base urls, remove the index one.
            if (isset($contextBaseUrls[Application::SITE_CONTEXT_PATH])) {
                unset($contextBaseUrls[Application::SITE_CONTEXT_PATH]);
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
     * @param string $baseUrl Full base url or just its path info.
     * @param string $url Full url or just its path info.
     */
    protected static function _checkBaseUrl(string $baseUrl, string $url): ?bool
    {
        // Check if both base url and url have host
        // component or not.
        $baseUrlHasHost = (bool) parse_url($baseUrl, PHP_URL_HOST);
        $urlHasHost = (bool) parse_url($url, PHP_URL_HOST);
        if ($baseUrlHasHost !== $urlHasHost) {
            return false;
        }

        $contextBaseUrls = Config::getContextBaseUrls();

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
     * Get passed variable value inside the passed url.
     */
    private static function _getUserVar(string $url, string $varName, array $userVars = []): ?string
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $userVarsFromUrl);
        return $userVarsFromUrl[$varName] ?? $userVars[$varName] ?? null;
    }

    /**
     * Get url components (page, operation and args)
     * based on the passed offset.
     */
    private static function _getUrlComponents(string $urlInfo, int $offset, string $varName = '', array $userVars = []): array|string|null
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
     * Get offset. Add 1 extra if localization present in URL
     */
    private static function _getOffset(string $urlInfo, int $varOffset): int
    {
        return $varOffset + (int) !!self::getLocalization($urlInfo);
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
        $libPath = realpath(base_path(PKP_LIB_PATH));
        $isLib = str_starts_with($file->getRealPath(), $libPath);
        $className = str_replace($isLib ? $libPath : realpath(base_path()), '', $file->getRealPath());
        // Drop the "classes" from the path (we don't use it on the namespaces) and the extension
        $className = preg_replace('#^[\\\\/]classes|\.php$#', '', $className);
        // Include the base namespace and replace the directory separator by the namespace separator
        $className = str_replace('/', '\\', '/' . ($isLib ? 'PKP' : 'APP') . $className);

        return class_exists($className)
            ? $className
            : throw new Exception("Failed to map the file \"{$file->getRealPath()}\" to a full qualified class name");
    }
}
