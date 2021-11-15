<?php
declare(strict_types = 1);

/**
 * @defgroup i18n I18N
 * Implements localization concerns such as locale files, time zones, and country lists.
 */

/**
 * @file classes/i18n/Locale.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Locale
 * @ingroup i18n
 *
 * @brief Provides methods for loading locale data and translating strings identified by unique keys
 */

namespace PKP\i18n;

use Illuminate\Support\Facades\App;
use PKP\config\Config;
use PKP\core\Registry;
use PKP\core\PKPRequest;
use PKP\db\DAORegistry;
use PKP\i18n\interfaces\LocaleInterface;
use PKP\plugins\HookRegistry;
use PKP\plugins\PluginRegistry;
use PKP\security\Validation;
use PKP\session\SessionManager;
use Illuminate\Support\Facades\Cache;
use PKP\i18n\translation\LocaleBundle;
use DateTime;
use DomainException;
use InvalidArgumentException;
use LogicException;
use PKP\facades\Repo;
use SplFileInfo;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RecursiveRegexIterator;
use RegexIterator;
use SimpleXMLElement;

class Locale implements LocaleInterface
{
    /** @var string Keeps the locales available in the system */
    protected const LOCALE_REGISTRY_FILE = 'registry/locales.xml';
    
    /**
     * @var callable Formatter for missing locale keys
     * Receives the locale key and must return a string
     */
    protected $missingKeyHandler;

    /** @var string Current locale */
    protected $locale;

    /** @var LocaleBundle[] List of locales */
    protected $localeBundles = [];

    /** @var array List of folders where locales can be found (also keeps a cache of locale files) */
    protected $folders = [];

    /** @var callable[] List of custom loaders */
    protected $loaders = [];

    /** @var PKPRequest Keeps the request */
    protected $request;

    /**
     * @copy \Illuminate\Contracts\Translation\Translator::get()
     */
    public function get($key, ?array $params = [], $locale = null): string
    {
        // Turned the array into a nullable array to avoid unexpected errors (it breaks the Laravel's contract, but doesn't emit errors)
        if ($params === null) {
            error_log((string) new LogicException('The $params argument cannot be null'));
            $params = [];
        }

        if (($key = trim($key)) === '') {
            return '';
        }

        $locale = $locale ?? $this->getLocale();
        $localeBundle = $this->getBundle($locale);
        if (($value = $localeBundle->translateSingular($key, $params)) !== null) {
            return $value;
        }

        // Add a missing key to the debug notes.
        $notes =& Registry::get('system.debug.notes');
        $notes[] = ['debug.notes.missingLocaleKey', ['key' => $key]];

        return HookRegistry::call('Locale::get', [&$key, &$params, &$locale, &$localeBundle, &$value])
            ? $value
            : $this->_handleMissingKey($key);
    }

    /**
     * @copy \Illuminate\Contracts\Translation\Translator::choice()
     */
    public function choice($key, $number, ?array $params = [], $locale = null): ?string
    {
        // Turned the array into a nullable array to avoid unexpected errors (it breaks the Laravel's contract, but doesn't emit errors)
        if ($params === null) {
            error_log((string) new LogicException('The $params argument cannot be null'));
            $params = [];
        }

        if (($key = trim($key)) === '') {
            return '';
        }

        $locale = $locale ?? $this->getLocale();
        $localeBundle = $this->getBundle($locale);
        if (($value = $localeBundle->translatePlural($key, $number, $params)) !== null) {
            return $value;
        }

        // Add a missing key to the debug notes.
        $notes =& Registry::get('system.debug.notes');
        $notes[] = ['debug.notes.missingLocaleKey', ['key' => $key]];

        return HookRegistry::call('Locale::choice', [&$key, &$number, &$params, &$locale, &$localeBundle, &$value])
            ? $value
            : sprintf($this->missingKeyFormat, htmlentities($key));
    }

    /**
     * @copy \Illuminate\Contracts\Translation\Translator::getLocale()
     */
    public function getLocale(): string
    {
        if (!$this->locale) {
            $request = $this->_getRequest();
            $locale = $request->getUserVar('setLocale')
                ?: (SessionManager::hasSession() ? SessionManager::getManager()->getUserSession()->getSessionVar('currentLocale') : null)
                ?: $request->getCookieVar('currentLocale');
            $this->setLocale(in_array($locale, array_keys($this->getSupportedLocales())) ? $locale : $this->getPrimaryLocale());
        }
        return $this->locale;
    }

    /**
     * @copy \Illuminate\Contracts\Translation\Translator::setLocale()
     */
    public function setLocale($locale): void
    {
        if (!$this->isLocaleValid($locale) || !in_array($locale, array_keys($this->getSupportedLocales()))) {
            error_log((string) new DomainException("Invalid \$locale (\"${locale}\"), default locale restored"));
            $locale = $this->getPrimaryLocale();
        }

        $this->locale = $locale;
        setlocale(LC_ALL, $locale . '.utf-8', $locale);
        putenv("LC_ALL=${locale}");
        ini_set('default_charset', 'utf-8');
    }

    /**
     * @copy LocaleInterface::getPrimaryLocale()
     */
    public function getPrimaryLocale(): string
    {
        static $locale;
        if (!$locale) {
            $request = $this->_getRequest();
            $locale = SessionManager::isDisabled()
                ? $this->getDefaultLocale()
                : ($request->getContext() ?? $request->getSite())->getPrimaryLocale();
            if (!$this->isLocaleValid($locale)) {
                $locale = $this->getDefaultLocale();
            }
        }
        return $locale;
    }

    /**
     * @copy LocaleInterface::registerFolder()
     */
    public function registerFolder(string $path, int $priority = 0): void
    {
        $path = new SplFileInfo($path);
        if (!$path->isDir()) {
            throw new InvalidArgumentException("${path} isn't a valid folder");
        }

        // Invalidate the loaded bundles cache
        $realPath = $path->getRealPath();
        if (($this->folders[$realPath] ?? null) !== $priority) {
            $this->folders[$realPath] = $priority;
            $this->localeBundles = [];
        }
    }

    /**
     * @copy LocaleInterface::registerLoader()
     */
    public function registerLoader(callable $fileLoader, int $priority = 0): void
    {
        // Invalidate the loaded bundles cache
        if (array_search($fileLoader, $this->loaders[$priority] ?? [], true) === false) {
            $this->loaders[$priority][] = $fileLoader;
            $this->localeBundles = [];
            ksort($this->loaders, SORT_NUMERIC);
        }
    }

    /**
     * @copy LocaleInterface::isLocaleValid()
     */
    public function isLocaleValid(?string $locale): bool
    {
        // Variants can be composed of five to eight letters, or of four characters starting with a digit
        return !empty($locale)
            && preg_match('/^[a-z]{2}(_[A-Z]{2})?(@([A-Za-z\d]{5,8}|\d[A-Za-z\d]{3}))?$/', $locale)
            && file_exists(BASE_SYS_DIR . "/locale/$locale");
    }

    /**
     * @copy LocaleInterface::getLocaleMetadata()
     */
    public function getLocaleMetadata(string $locale): ?LocaleMetadata
    {
        return $this->getLocales()[$locale] ?? null;
    }

    /**
     * @copy LocaleInterface::getLocales()
     */
    public function getLocales(): array
    {
        static $cache;

        $key = static::class . static::LOCALE_REGISTRY_FILE;
        $cache = $cache ?? Cache::get($key);
        $path = BASE_SYS_DIR . '/' . static::LOCALE_REGISTRY_FILE;
        if (!is_array($cache) || filemtime($path) > ($cache['createdAt'] ?? new DateTime())->getTimestamp()) {
            $xml = new SimpleXMLElement(file_get_contents($path));
            $locales = [];
            foreach ($xml->locale as $item) {
                $locales[(string) $item['key']] = LocaleMetadata::createFromXml($item);
            }
            $cache = [
                'createdAt' => new DateTime(),
                'data' => $locales
            ];
            Cache::put($key, $cache);
        }
        return $cache['data'];
    }

    /**
     * @copy LocaleInterface::installLocale()
     */
    public function installLocale(string $locale): void
    {
        Repo::emailTemplate()->dao->installEmailTemplateLocaleData(Repo::emailTemplate()->dao->getMainEmailTemplatesFilename(), [$locale]);

        // Load all plugins so they can add locale data if needed
        $categories = PluginRegistry::getCategories();
        foreach ($categories as $category) {
            PluginRegistry::loadCategory($category);
        }
        HookRegistry::call('Locale::installLocale', [&$locale]);
    }

    /**
     * @copy LocaleInterface::uninstallLocale()
     */
    public function uninstallLocale(string $locale): void
    {
        // Delete locale-specific data
        Repo::emailTemplate()->dao->deleteEmailTemplatesByLocale($locale);
        Repo::emailTemplate()->dao->deleteDefaultEmailTemplatesByLocale($locale);
    }

    /**
     * @copy LocaleInterface::getSupportedFormLocales()
     */
    public function getSupportedFormLocales(): array
    {
        static $locales;
        if (!$locales) {
            $request = $this->_getRequest();
            $locales = SessionManager::isDisabled()
                ? array_map(fn(LocaleMetadata $locale) => $locale->name, Locale::getLocales())
                : (($context = $request->getContext()) ? $context->getSupportedFormLocaleNames() : $request->getSite()->getSupportedLocaleNames());
        }
        return $locales;
    }

    /**
     * @copy LocaleInterface::getSupportedLocales()
     */
    public function getSupportedLocales(): array
    {
        static $locales;
        if (!$locales) {
            $request = $this->_getRequest();
            $locales = SessionManager::isDisabled()
                ? array_map(fn(LocaleMetadata $locale) => $locale->name, Locale::getLocales())
                : ($request->getContext() ?? $request->getSite())->getSupportedLocaleNames();
        }
        return $locales;
    }

    /**
     * @copy LocaleInterface::setMissingKeyHandler()
     */
    public function setMissingKeyHandler(?callable $handler): void
    {
        $this->missingKeyHandler = $handler;
    }

    /**
     * @copy LocaleInterface::getMissingKeyHandler()
     */
    public function getMissingKeyHandler(): ?callable
    {
        return $this->missingKeyHandler;
    }

    /**
     * @copy LocaleInterface::getBundle()
     */
    public function getBundle(string $locale): LocaleBundle
    {
        if (!isset($this->localeBundles[$locale])) {
            $bundle = [];
            foreach ($this->folders as $folder => $priority) {
                $bundle += $this->_getLocaleFiles($folder, $locale, $priority);
            }
            foreach ($this->loaders as $loader) {
                $loader($locale, $bundle);
            }
            $this->localeBundles[$locale] = new LocaleBundle($locale, $bundle);
        }

        return $this->localeBundles[$locale];
    }

    /**
     * @copy LocaleInterface::getDefaultLocale()
     */
    public function getDefaultLocale(): string
    {
        return Config::getVar('i18n', 'locale');
    }

    /**
     * Given a locale folder, retrieves all locale files (.po)
     *
     * @return int[]
     */
    private function _getLocaleFiles(string $folder, string $locale, int $priority): array
    {
        static $cache = [];
        $files = $cache[$folder][$locale] ?? null;
        if ($files === null) {
            $files = [];
            if (is_dir($path = "$folder/$locale")) {
                $directory = new RecursiveDirectoryIterator($path);
                $iterator = new RecursiveIteratorIterator($directory);
                $files = array_keys(iterator_to_array(new RegexIterator($iterator, '/\.po$/i', RecursiveRegexIterator::GET_MATCH)));
            }
            $cache[$folder][$locale] = $files;
        }
        return array_fill_keys($files, $priority);
    }

    /**
     * Retrieves the request
     */
    private function _getRequest(): PKPRequest
    {
        return App::make(PKPRequest::class);
    }

    /**
     * Formats a missing locale key
     */
    private function _handleMissingKey(string $key): string
    {
        return is_callable($this->missingKeyHandler) ? ($this->missingKeyHandler)($key) : '##' . htmlentities($key) . '##';
    }
}
