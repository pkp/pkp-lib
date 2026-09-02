<?php

/**
 * @file classes/dev/ComposerScript.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ComposerScript
 *
 * @brief Custom composer script that checks if the file iso_639-2.json exists in sokil library
 */

namespace PKP\dev;

use Exception;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class ComposerScript
{
    /**
     * Vendor package assets copied into the application's public/ directory.
     *
     * Keys are source directories relative to lib/pkp/, values are destinations relative to public/.
     */
    private const PUBLISHABLE_ASSETS = [
        'lib/vendor/opcodesio/log-viewer/public' => 'vendor/log-viewer',
    ];

    /**
     * A post-install-cmd custom composer script that checks if
     * the file iso_639-2.json exists in the installed sokil library
     *
     * @throw Exception
     */
    public static function isoFileCheck(): void
    {
        // We use dirname(__FILE__, 3) and not Core::getBaseDir() because
        // this funciton is called by Composer, where INDEX_FILE_LOCATION is not defined.
        $iso6392bFile = dirname(__FILE__, 3) . '/lib/vendor/sokil/php-isocodes-db-i18n/databases/iso_639-2.json';
        if (!file_exists($iso6392bFile)) {
            throw new Exception("The ISO639-2b file {$iso6392bFile} does not exist.");
        }
    }

    /**
     * A post-install-cmd custom composer script that publishes vendor package
     * assets (e.g. log-viewer) to the public directory.
     *
     * Also called by the install and upgrade migrations, so it must stay free of any dependency
     * on the App bootstrap: Composer runs it before the application is available, which is why
     * paths are derived from __FILE__ rather than Core::getBaseDir().
     */
    public static function publishPackageAssets(): void
    {
        // dirname(__FILE__, 3) is lib/pkp; two levels above that is the application root.
        $pkpPath = dirname(__FILE__, 3);
        $publicPath = dirname($pkpPath, 2) . '/public';

        foreach (self::PUBLISHABLE_ASSETS as $source => $destination) {
            $sourcePath = "{$pkpPath}/{$source}";

            if (!is_dir($sourcePath)) {
                static::report("skipped '{$destination}', package assets not found at {$sourcePath}.");
                continue;
            }

            if (!is_writable($publicPath)) {
                static::report("skipped '{$destination}', {$publicPath} is not writable. Re-run 'composer install' once it is.");
                continue;
            }

            $copied = static::copyDirectory($sourcePath, "{$publicPath}/{$destination}");

            static::report($copied === null
                ? "failed to publish '{$destination}'."
                : "published {$copied} file(s) to public/{$destination}.");
        }
    }

    /**
     * Report progress to wherever the caller can see it.
     *
     * Composer and tools/upgrade.php both run on the console, where printing is the whole point;
     * a web-based install must not echo into the page, and error_log() may be a file anyway.
     */
    private static function report(string $message): void
    {
        if (PHP_SAPI === 'cli') {
            echo "Package assets: {$message}\n";
            return;
        }

        error_log("Package assets: {$message}");
    }

    /**
     * Recursively copy a directory, overwriting existing files.
     *
     * @return null|int The number of files copied, or null on failure.
     */
    private static function copyDirectory(string $source, string $destination): ?int
    {
        if (!is_dir($destination) && !mkdir($destination, 0755, true) && !is_dir($destination)) {
            return null;
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        $copied = 0;
        $sourcePrefixLength = strlen($source) + 1;

        foreach ($items as $item) {
            // Not $items->getSubPathname(): that is only reachable through
            // RecursiveIteratorIterator::__call(), so static analysis cannot see it.
            $target = $destination . '/' . substr($item->getPathname(), $sourcePrefixLength);

            if ($item->isDir()) {
                if (!is_dir($target) && !mkdir($target, 0755, true) && !is_dir($target)) {
                    return null;
                }
                continue;
            }

            if (!copy($item->getPathname(), $target)) {
                return null;
            }

            $copied++;
        }

        return $copied;
    }


    /**
     * A post-install-cmd custom composer script that
     * creates languages.json from downloaded Weblate languages.csv.
     */
    public static function weblateFilesDownload(): void
    {
        try {
            $dirPath = dirname(__FILE__, 3) . '/lib/weblateLanguages';
            $langFilePath = "{$dirPath}/languages.json";
            $urlCsv = 'https://raw.githubusercontent.com/WeblateOrg/language-data/main/languages.csv';

            if (!is_dir($dirPath)) {
                mkdir($dirPath);
            }

            // Download languages.csv using curl (proxy picked up from environment)
            $ch = curl_init($urlCsv);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FAILONERROR, true);
            $languagesCsvRaw = curl_exec($ch);
            if ($languagesCsvRaw === false) {
                throw new Exception(__METHOD__ . " : The Weblate file 'languages.csv' cannot be downloaded! Curl error: " . curl_error($ch));
            }
            curl_close($ch);
            $languagesCsv = explode("\n", $languagesCsvRaw);

            array_shift($languagesCsv);
            $languages = [];
            foreach ($languagesCsv as $languageCsv) {
                $localeAndName = str_getcsv($languageCsv, ',', escape: '\\');
                if (isset($localeAndName[0], $localeAndName[1]) && preg_match('/^[\w@-]{2,50}$/', $localeAndName[0])) {
                    $displayName = locale_get_display_name($localeAndName[0], 'en');
                    $languages[$localeAndName[0]] = (($displayName && $displayName !== $localeAndName[0]) ? $displayName : $localeAndName[1]);
                }
            }

            $languagesJson = json_encode($languages, JSON_THROW_ON_ERROR);
            if (!$languagesJson || !file_put_contents($langFilePath, $languagesJson)) {
                throw new Exception(__METHOD__ . " : Json file empty, or save unsuccessful: {$langFilePath} !");
            }
        } catch (Exception $e) {
            error_log($e->getMessage());
        }
    }
}
