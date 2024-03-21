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
     * A post-install-cmd custom composer script that
     * creates languages.json from downloaded Weblate languages.csv.
     */
    public static function weblateFilesDownload(): void
    {
        try {
            $dirPath = dirname(__FILE__, 3) . "/lib/weblateLanguages";
            $langFilePath = "$dirPath/languages.json";
            $urlCsv = 'https://raw.githubusercontent.com/WeblateOrg/language-data/main/languages.csv';

            if (!is_dir($dirPath)) {
                mkdir($dirPath);
            }

            $streamContext = stream_context_create(['http' => ['method' => 'HEAD']]);
            $languagesCsv = !preg_match('/200 OK/', get_headers($urlCsv, false, $streamContext)[0] ?? "") ?: file($urlCsv, FILE_SKIP_EMPTY_LINES);
            if (!is_array($languagesCsv) || !$languagesCsv) {
                throw new Exception(__METHOD__ . " : The Weblate file 'languages.csv' cannot be downloaded !");
            }

            array_shift($languagesCsv);
            $languages = [];
            foreach($languagesCsv as $languageCsv) {
                $localeAndName = str_getcsv($languageCsv, ",");
                if (isset($localeAndName[0], $localeAndName[1]) && preg_match('/^[\w@-]{2,50}$/', $localeAndName[0])) {
                    $displayName = locale_get_display_name($localeAndName[0], 'en');
                    $languages[$localeAndName[0]] = (($displayName && $displayName !== $localeAndName[0]) ? $displayName : $localeAndName[1]);
                }
            }

            $languagesJson = json_encode($languages, JSON_THROW_ON_ERROR);
            if (!$languagesJson || !file_put_contents($langFilePath, $languagesJson)) {
                throw new Exception(__METHOD__ . " : Json file empty, or save unsuccessful: $langFilePath !");
            }
        } catch (Exception $e) {
            error_log($e->getMessage());
        }
    }
}
