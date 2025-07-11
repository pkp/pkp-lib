<?php

/**
 * @file classes/file/FileArchive.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FileArchive
 *
 * @ingroup file
 *
 * @brief Class provides functionality for creating an archive of files.
 */

namespace PKP\file;

use Exception;
use PKP\config\Config;
use ZipArchive;

class FileArchive
{
    /**
     * Assembles an array of filenames into either a tar.gz or a .zip
     * file, based on what is available.  Returns a string representing
     * the path to the archive on disk.
     *
     * @param array $files the files to add, in an associative array of the
     *  format ('serverPath' => 'clientFilename')
     * @param string $filesDir a path to the files on disk.
     *
     * @return string the path to the archive.
     */
    public function create($files, $filesDir)
    {
        // Create a temporary file.
        $archivePath = tempnam('/tmp', 'sf-');
        if ($archivePath === false) {
            return false;
        }
        unlink($archivePath);

        // attempt to use Zip first, if it is available.  Otherwise
        // fall back to the tar CLI.
        $zipTest = false;
        if (self::zipFunctional()) {
            $zipTest = true;
            $zip = new ZipArchive();
            if ($zip->open($archivePath, ZipArchive::CREATE) == true) {
                foreach ($files as $serverPath => $clientFilename) {
                    $zip->addFile($filesDir . '/' . $serverPath, $clientFilename);
                }
                $zip->close();
            }
        } elseif (self::tarFunctional()) {
            // Create the archive and download the file.
            exec(
                Config::getVar('cli', 'tar') . ' -c -z ' .
                    '-f ' . escapeshellarg($archivePath) . ' ' .
                    '-C ' . escapeshellarg($filesDir) . ' ' .
                    implode(' ', array_map(escapeshellarg(...), array_keys($files)))
            );
        } else {
            throw new Exception('No archive tool is available!');
        }

        return $archivePath;
    }

    /**
     * Return true if the zip extension is loaded.
     *
     * @return bool
     */
    public static function zipFunctional()
    {
        return (extension_loaded('zip'));
    }

    /**
     * Return true if the tar tools are configured.
     */
    public static function tarFunctional()
    {
        $tarBinary = Config::getVar('cli', 'tar');
        return !empty($tarBinary) && file_exists($tarBinary);
    }

    public static function isFunctional()
    {
        return self::zipFunctional() || self::tarFunctional();
    }
}
