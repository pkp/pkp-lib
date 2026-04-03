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

class ComposerScript
{
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
     * A post-install-cmd custom composer script that publishes
     * Laravel package assets (e.g., log-viewer) to the public directory.
     */
    public static function publishPackageAssets(): void
    {
        // dirname(__FILE__, 3) resolves to lib/pkp/ since this is called by Composer
        // where Core::getBaseDir() / base_path() are not available.
        $pkpBase = dirname(__FILE__, 3);
        $appBase = dirname($pkpBase, 2);
        $publicPath = $appBase . '/public';

        // Load the canonical package registry from PublishPackageAssetsMigration
        require_once $pkpBase . '/classes/migration/install/PublishPackageAssetsMigration.php';
        $packages = \PKP\migration\install\PublishPackageAssetsMigration::getPublishablePackages();

        foreach ($packages as $name => $config) {
            $sourcePath = $appBase . '/' . $config['source'];
            $destPath = $publicPath . '/' . $config['destination'];

            if (!is_dir($sourcePath)) {
                echo "Warning: source directory not found for package '{$name}': {$sourcePath}. Skipping asset publishing.\n";
                continue;
            }

            if (!is_writable($publicPath)) {
                echo "Warning: public/ directory is not writable. Skipping asset publishing for '{$name}'.\n";
                continue;
            }

            if (!is_dir($destPath)) {
                mkdir($destPath, 0755, true);
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($sourcePath, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            $copied = 0;
            foreach ($iterator as $item) {
                /** @var \RecursiveDirectoryIterator $innerIterator */
                $innerIterator = $iterator->getInnerIterator();
                $subPath = $innerIterator->getSubPathname();
                $itemDestPath = $destPath . DIRECTORY_SEPARATOR . $subPath;

                if ($item->isDir()) {
                    if (!is_dir($itemDestPath)) {
                        mkdir($itemDestPath, 0755, true);
                    }
                } else {
                    $parentDir = dirname($itemDestPath);
                    if (!is_dir($parentDir)) {
                        mkdir($parentDir, 0755, true);
                    }
                    copy($item->getPathname(), $itemDestPath);
                    $copied++;
                }
            }

            echo "Published {$copied} asset file(s) for package '{$name}'.\n";
        }
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
