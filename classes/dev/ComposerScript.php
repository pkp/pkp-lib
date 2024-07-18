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
     * copies node_module dependencies
     */
    public static function copyNodeModuleDeps(): void
    {   
        function copyDir($src, $dst) {
            $dir = opendir($src);
            @mkdir($dst, 0755, true);
            while (false !== ($file = readdir($dir))) {
                if ($file != '.' && $file != '..') {
                    if (is_dir($src . '/' . $file)) {
                        copyDir($src . '/' . $file, $dst . '/' . $file);
                    } else {
                        copy($src . '/' . $file, $dst . '/' . $file);
                    }
                }
            }
            closedir($dir);
        }

        try {
            $baseDir = __DIR__ . '/../../../../node_modules';
            $jqueryDist = $baseDir . '/jquery/dist';
            $jqueryUiDist = $baseDir . '/jquery-ui/dist';
            $vendorComponents = __DIR__ . '/../../lib/vendor/components';

            if (!file_exists($vendorComponents . '/jquery')) {
                mkdir($vendorComponents . '/jquery', 0755, true);
            }
            if (!file_exists($vendorComponents . '/jqueryui')) {
                mkdir($vendorComponents . '/jqueryui', 0755, true);
            }

            copyDir($jqueryDist, $vendorComponents . '/jquery');
            copy($jqueryUiDist . '/jquery-ui.js', $vendorComponents . '/jqueryui/jquery-ui.js');
            copy($jqueryUiDist . '/jquery-ui.min.js', $vendorComponents . '/jqueryui/jquery-ui.min.js');

        } catch (Exception $e) {
            error_log($e->getMessage());
        }
    }
}
