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

require_once __DIR__ . '/../../../../tools/bootstrap.php';
import('lib.pkp.classes.file.FileManager');

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
     * copies composer installs from repositories
     * to the correct/existing directories of the following dependencies:
     * jquery-ui, jquery validation and chartjs
     */
    public static function copyVendorAssets(): void
    {
        $fileManager = new \FileManager();
        $vendorBaseDir = __DIR__ . '/../../lib/vendor';
        $jsPluginsDir = __DIR__ . '/../../js/lib';

        $source = [
            'jquery-ui.js' => $vendorBaseDir . '/jquery/ui/dist/jquery-ui.js',
            'jquery-ui.min.js' => $vendorBaseDir . '/jquery/ui/dist/jquery-ui.min.js',
            'jquery-validate' => $vendorBaseDir . '/jquery/validation/dist',
            'Chart.js' => $vendorBaseDir . '/chart/js/dist/Chart.js',
            'Chart.min.js' => $vendorBaseDir . '/chart/js/dist/Chart.min.js'
        ];

        $dest = [
            'jquery-ui.js' => $vendorBaseDir . '/components/jqueryui/jquery-ui.js',
            'jquery-ui.min.js' => $vendorBaseDir . '/components/jqueryui/jquery-ui.min.js',
            'jquery-validate' => $jsPluginsDir . '/jquery/plugins/validate',
            'Chart.js' => $jsPluginsDir . '/Chart.js',
            'Chart.min.js' => $jsPluginsDir . '/Chart.min.js'
        ];

        // jQuery UI
        if (!$fileManager->copyFile($source['jquery-ui.js'], $dest['jquery-ui.js'])) {
            throw new Exception('Failed to copy jquery-ui.js to destination folder');
        }
        if (!$fileManager->copyFile($source['jquery-ui.min.js'], $dest['jquery-ui.min.js'])) {
            throw new Exception('Failed to copy jquery-ui.min.js to destination folder');
        }

        // jQuery Validation
        if (!$fileManager->copyDir($source['jquery-validate'], $dest['jquery-validate'])) {
            throw new Exception('Failed to copy jquery-validate to destination folder');
        }

        // Chart.js
        if (!$fileManager->copyFile($source['Chart.js'], $dest['Chart.js'])) {
            throw new Exception('Failed to copy Chart.js to destination folder');
        }
        if (!$fileManager->copyFile($source['Chart.min.js'], $dest['Chart.min.js'])) {
            throw new Exception('Failed to copy Chart.min.js to destination folder');
        }
    }
}
