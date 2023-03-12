<?php

/**
 * @defgroup file File
 * Implements file management tools, including a database-backed list of files
 * associated with submissions.
 */

/**
 * @file classes/file/FileManager.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 * ePUB mime type added  Leah M Root (rootl) SUNY Geneseo
 * @class FileManager
 * @ingroup file
 *
 * @brief Class defining basic operations for file management.
 */

namespace PKP\file;

use APP\core\Application;
use Exception;
use GuzzleHttp\Psr7\Stream;
use GuzzleHttp\Psr7\Utils;
use PKP\config\Config;
use PKP\core\PKPString;
use PKP\plugins\Hook;
use SplFileObject;
use Throwable;

class FileManager
{
    public const FILE_MODE_MASK = 0666;
    public const DIRECTORY_MODE_MASK = 0777;

    public const DOCUMENT_TYPE_DEFAULT = 'default';
    public const DOCUMENT_TYPE_AUDIO = 'audio';
    public const DOCUMENT_TYPE_EXCEL = 'excel';
    public const DOCUMENT_TYPE_HTML = 'html';
    public const DOCUMENT_TYPE_IMAGE = 'image';
    public const DOCUMENT_TYPE_PDF = 'pdf';
    public const DOCUMENT_TYPE_WORD = 'word';
    public const DOCUMENT_TYPE_EPUB = 'epub';
    public const DOCUMENT_TYPE_VIDEO = 'video';
    public const DOCUMENT_TYPE_ZIP = 'zip';
    public const DOCUMENT_TYPE_URL = 'url';

    /**
     * Constructor
     */
    public function __construct()
    {
    }

    /**
     * Return true if an uploaded file exists.
     *
     * @param string $fileName the name of the file used in the POST form
     *
     * @return bool
     */
    public function uploadedFileExists($fileName)
    {
        if (isset($_FILES[$fileName]) && isset($_FILES[$fileName]['tmp_name'])
                && is_uploaded_file($_FILES[$fileName]['tmp_name'])) {
            return true;
        }
        return false;
    }

    /**
     * Return true iff an error occurred when trying to upload a file.
     *
     * @param string $fileName the name of the file used in the POST form
     *
     * @return bool
     */
    public function uploadError($fileName)
    {
        return (isset($_FILES[$fileName]) && $_FILES[$fileName]['error'] != UPLOAD_ERR_OK);
    }

    /**
     * Get the error code of a file upload
     *
     * @see http://php.net/manual/en/features.file-upload.errors.php
     *
     * @param string $fileName the name of the file used in the POST form
     *
     * @return int
     */
    public function getUploadErrorCode($fileName)
    {
        return $_FILES[$fileName]['error'];
    }

    /**
     * Get the filename of the first uploaded file in the $_FILES array. The
     * returned filename is the value used in the form that submitted the request.
     *
     * @return string
     */
    public function getFirstUploadedPostName()
    {
        return key($_FILES);
    }

    /**
     * Return the (temporary) path to an uploaded file.
     *
     * @param string $fileName the name of the file used in the POST form
     *
     * @return string (boolean false if no such file)
     */
    public function getUploadedFilePath($fileName)
    {
        if (isset($_FILES[$fileName]['tmp_name']) && is_uploaded_file($_FILES[$fileName]['tmp_name'])) {
            return $_FILES[$fileName]['tmp_name'];
        }
        return false;
    }

    /**
     * Return the user-specific (not temporary) filename of an uploaded file.
     *
     * @param string $fileName the name of the file used in the POST form
     *
     * @return string (boolean false if no such file)
     */
    public function getUploadedFileName($fileName)
    {
        if (isset($_FILES[$fileName]['name'])) {
            return $_FILES[$fileName]['name'];
        }
        return false;
    }

    /**
     * Return the type of an uploaded file.
     *
     * @param string $fileName the name of the file used in the POST form
     *
     * @return string
     */
    public function getUploadedFileType($fileName)
    {
        if (isset($_FILES[$fileName])) {
            $exploded = explode('.', $_FILES[$fileName]['name']);

            $type = PKPString::mime_content_type(
                $_FILES[$fileName]['tmp_name'], // Location on server
                array_pop($exploded) // Extension on client machine
            );

            if (!empty($type)) {
                return $type;
            }
            return $_FILES[$fileName]['type'];
        }
        return false;
    }

    /**
     * Upload a file.
     *
     * @param string $fileName the name of the file used in the POST form
     *
     * @return bool returns true if successful
     */
    public function uploadFile($fileName, $destFileName)
    {
        $destDir = dirname($destFileName);
        if (!$this->fileExists($destDir, 'dir')) {
            // Try to create the destination directory
            $this->mkdirtree($destDir);
        }
        if (!isset($_FILES[$fileName])) {
            return false;
        }
        if (move_uploaded_file($_FILES[$fileName]['tmp_name'], $destFileName)) {
            return $this->setMode($destFileName, self::FILE_MODE_MASK);
        }
        return false;
    }

    /**
     * Write a file.
     *
     * @param string $dest the path where the file is to be saved
     * @param string $contents the contents to write to the file
     *
     * @return bool returns true if successful
     */
    public function writeFile($dest, &$contents)
    {
        $success = true;
        $destDir = dirname($dest);
        if (!$this->fileExists($destDir, 'dir')) {
            // Try to create the destination directory
            $this->mkdirtree($destDir);
        }
        if (($f = fopen($dest, 'wb')) === false) {
            $success = false;
        }
        if ($success && fwrite($f, $contents) === false) {
            $success = false;
        }
        @fclose($f);

        if ($success) {
            return $this->setMode($dest, self::FILE_MODE_MASK);
        }
        return false;
    }

    /**
     * Copy a file.
     *
     * @param string $source the source URL for the file
     * @param string $dest the path where the file is to be saved
     *
     * @return bool returns true if successful
     */
    public function copyFile($source, $dest)
    {
        $destDir = dirname($dest);
        if (!$this->fileExists($destDir, 'dir')) {
            // Try to create the destination directory
            $this->mkdirtree($destDir);
        }
        if (copy($source, $dest)) {
            return $this->setMode($dest, self::FILE_MODE_MASK);
        }
        return false;
    }

    /**
     * Copy a directory.
     * Adapted from code by gimmicklessgpt at gmail dot com, at http://php.net/manual/en/function.copy.php
     *
     * @param string $source the path to the source directory
     * @param string $dest the path where the directory is to be saved
     *
     * @return bool returns true if successful
     */
    public function copyDir($source, $dest)
    {
        if (is_dir($source)) {
            $this->mkdir($dest);
            $destDir = dir($source);

            while (($entry = $destDir->read()) !== false) {
                if ($entry == '.' || $entry == '..') {
                    continue;
                }

                $Entry = "${source}/${entry}";
                if (is_dir($Entry)) {
                    $this->copyDir($Entry, "${dest}/${entry}");
                    continue;
                }
                $this->copyFile($Entry, "${dest}/${entry}");
            }

            $destDir->close();
        } else {
            $this->copyFile($source, $dest);
        }

        if ($this->fileExists($dest, 'dir')) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Retrieves whether the filename is a URL
     */
    public static function isVirtualPath(string $filenameOrUrl): bool
    {
        return !!filter_var($filenameOrUrl, FILTER_VALIDATE_URL);
    }

    /**
     * Get a PSR7 stream given a filename or URL.
     */
    public static function getStream(string $filenameOrUrl): ?Stream
    {
        if (static::isVirtualPath($filenameOrUrl)) {
            // Remote URL.
            $client = Application::get()->getHttpClient();
            $response = $client->request('GET', $filenameOrUrl);
            return Utils::streamFor($response->getBody());
        } elseif (file_exists($filenameOrUrl) && is_readable($filenameOrUrl)) {
            $resource = fopen($filenameOrUrl, 'r');
            return Utils::streamFor($resource);
        }
        return null;
    }

    /**
     * Read a file's contents.
     *
     * @param string $filePath the location of the file to be read
     * @param bool $output output the file's contents instead of returning a string
     *
     * @return string|boolean
     */
    public function readFileFromPath($filePath, $output = false)
    {
        if (is_readable($filePath)) {
            $f = fopen($filePath, 'rb');
            if (!$f) {
                return false;
            }
            $data = '';
            while (!feof($f)) {
                $data .= fread($f, 4096);
                if ($output) {
                    echo $data;
                    $data = '';
                }
            }
            fclose($f);

            if ($output) {
                return true;
            }
            return $data;
        }
        return false;
    }

    /**
     * Download a file.
     * Outputs HTTP headers and file content for download
     *
     * @param string $filePath the location of the file to be sent
     * @param string $mediaType the MIME type of the file, optional
     * @param bool $inline print file as inline instead of attachment, optional
     * @param string $fileName Optional filename to use on the client side
     *
     * @return bool
     */
    public function downloadByPath($filePath, $mediaType = null, $inline = false, $fileName = null)
    {
        $result = null;
        if (Hook::call('FileManager::downloadFile', [&$filePath, &$mediaType, &$inline, &$result, &$fileName])) {
            return $result;
        }
        if (is_readable($filePath)) {
            if ($mediaType === null) {
                // If the media type wasn't specified, try to detect.
                $mediaType = PKPString::mime_content_type($filePath);
                if (empty($mediaType)) {
                    $mediaType = 'application/octet-stream';
                }
            }
            if ($fileName === null) {
                // If the filename wasn't specified, use the server-side.
                $fileName = basename($filePath);
            }

            // Stream the file to the end user.
            header("Content-Type: ${mediaType}");
            header('Content-Length: ' . filesize($filePath));
            header('Accept-Ranges: none');
            header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . "; filename=\"${fileName}\"");
            header('Cache-Control: private'); // Workarounds for IE weirdness
            header('Pragma: public');
            $this->readFileFromPath($filePath, true);
            $returner = true;
        } else {
            $returner = false;
        }
        Hook::call('FileManager::downloadFileFinished', [&$returner]);
        return $returner;
    }

    /**
     * Delete a file.
     *
     * @param string $filePath the location of the file to be deleted
     *
     * @return bool returns true if successful
     */
    public function deleteByPath($filePath)
    {
        if ($this->fileExists($filePath)) {
            $result = null;
            if (Hook::call('FileManager::deleteFile', [$filePath, &$result])) {
                return $result;
            }
            return unlink($filePath);
        }
        return false;
    }

    /**
     * Create a new directory.
     *
     * @param string $dirPath the full path of the directory to be created
     * @param string $perms the permissions level of the directory (optional)
     *
     * @return bool returns true if successful
     */
    public function mkdir($dirPath, $perms = null)
    {
        if ($perms !== null) {
            return mkdir($dirPath, $perms);
        } else {
            if (mkdir($dirPath)) {
                return $this->setMode($dirPath, static::DIRECTORY_MODE_MASK);
            }
            return false;
        }
    }

    /**
     * Remove a directory.
     *
     * @param string $dirPath the full path of the directory to be delete
     *
     * @return bool returns true if successful
     */
    public function rmdir($dirPath)
    {
        return rmdir($dirPath);
    }

    /**
     * Delete all contents including directory (equivalent to "rm -r")
     *
     * @param string $file the full path of the directory to be removed
     *
     * @return bool true iff success, otherwise false
     */
    public function rmtree($file)
    {
        if (file_exists($file)) {
            if (is_dir($file)) {
                $handle = opendir($file);
                while (($filename = readdir($handle)) !== false) {
                    if ($filename != '.' && $filename != '..') {
                        if (!$this->rmtree($file . '/' . $filename)) {
                            return false;
                        }
                    }
                }
                closedir($handle);
                if (!rmdir($file)) {
                    return false;
                }
            } else {
                if (!unlink($file)) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Create a new directory, including all intermediate directories if required (equivalent to "mkdir -p")
     *
     * @param string $dirPath the full path of the directory to be created
     * @param string $perms the permissions level of the directory (optional)
     *
     * @return bool returns true if successful
     */
    public function mkdirtree($dirPath, $perms = null)
    {
        if (!file_exists($dirPath)) {
            //Avoid infinite recursion when file_exists reports false for root directory
            if ($dirPath == dirname($dirPath)) {
                fatalError('There are no readable files in this directory tree. Are safe mode or open_basedir active?');
                return false;
            } elseif ($this->mkdirtree(dirname($dirPath), $perms)) {
                return $this->mkdir($dirPath, $perms);
            } else {
                return false;
            }
        }
        return true;
    }

    /**
     * Check if a file path is valid;
     *
     * @param string $filePath the file/directory to check
     * @param string $type (file|dir) the type of path
     */
    public function fileExists($filePath, $type = 'file')
    {
        switch ($type) {
            case 'file':
                return file_exists($filePath);
            case 'dir':
                return file_exists($filePath) && is_dir($filePath);
            default:
                return false;
        }
    }

    /**
     * Returns a file type, based on generic categories defined above
     *
     * @param string $type
     *
     * @return string (Enumerated DOCUMENT_TYPEs)
     */
    public function getDocumentType($type)
    {
        if ($this->getImageExtension($type)) {
            return self::DOCUMENT_TYPE_IMAGE;
        }

        switch ($type) {
            case 'application/pdf':
            case 'application/x-pdf':
            case 'text/pdf':
            case 'text/x-pdf':
                return self::DOCUMENT_TYPE_PDF;
            case 'application/msword':
            case 'application/word':
                return self::DOCUMENT_TYPE_WORD;
            case 'application/excel':
                return self::DOCUMENT_TYPE_EXCEL;
            case 'text/html':
                return self::DOCUMENT_TYPE_HTML;
            case 'application/zip':
            case 'application/x-zip':
            case 'application/x-zip-compressed':
            case 'application/x-compress':
            case 'application/x-compressed':
            case 'multipart/x-zip':
                return self::DOCUMENT_TYPE_ZIP;
            case 'application/epub':
            case 'application/epub+zip':
                return self::DOCUMENT_TYPE_EPUB;
            default:
                return self::DOCUMENT_TYPE_DEFAULT;
        }
    }

    /**
     * Returns file extension associated with the given document type,
     * or false if the type does not belong to a recognized document type.
     *
     * @param string $type
     */
    public function getDocumentExtension($type)
    {
        switch ($type) {
            case 'application/pdf':
                return '.pdf';
            case 'application/word':
                return '.doc';
            case 'text/css':
                return '.css';
            case 'text/html':
                return '.html';
            case 'application/epub+zip':
                return '.epub';
            default:
                return false;
        }
    }

    /**
     * Returns file extension associated with the given image type,
     * or false if the type does not belong to a recognized image type.
     *
     * @param string $type
     */
    public function getImageExtension($type)
    {
        switch ($type) {
            case 'image/gif':
                return '.gif';
            case 'image/jpeg':
            case 'image/pjpeg':
                return '.jpg';
            case 'image/png':
            case 'image/x-png':
                return '.png';
            case 'image/vnd.microsoft.icon':
            case 'image/x-icon':
            case 'image/x-ico':
            case 'image/ico':
                return '.ico';
            case 'image/svg+xml':
            case 'image/svg':
                return '.svg';
            case 'application/x-shockwave-flash':
                return '.swf';
            case 'video/x-flv':
            case 'application/x-flash-video':
            case 'flv-application/octet-stream':
                return '.flv';
            case 'audio/mpeg':
                return '.mp3';
            case 'audio/x-aiff':
                return '.aiff';
            case 'audio/x-wav':
                return '.wav';
            case 'video/mpeg':
                return '.mpg';
            case 'video/quicktime':
                return '.mov';
            case 'video/mp4':
                return '.mp4';
            case 'text/javascript':
                return '.js';
            case 'image/webp':
                return '.webp';
            default:
                return false;
        }
    }

    /**
     * Parse file extension from file name.
     *
     * @param string $fileName a valid file name
     *
     * @return string extension
     */
    public function getExtension($fileName)
    {
        $extension = '';
        $fileParts = explode('.', $fileName);
        if (is_array($fileParts)) {
            $extension = $fileParts[count($fileParts) - 1];
        }
        return $extension;
    }

    /**
     * Truncate a filename to fit in the specified length.
     */
    public function truncateFileName($fileName, $length = 127)
    {
        if (PKPString::strlen($fileName) <= $length) {
            return $fileName;
        }
        $ext = $this->getExtension($fileName);
        $truncated = PKPString::substr($fileName, 0, $length - 1 - PKPString::strlen($ext)) . '.' . $ext;
        return PKPString::substr($truncated, 0, $length);
    }

    /**
     * Return pretty file size string (in B, KB, MB, or GB units).
     *
     * @param int $size file size in bytes
     *
     * @return string
     */
    public function getNiceFileSize($size)
    {
        $niceFileSizeUnits = ['B', 'KB', 'MB', 'GB'];
        for ($i = 0; $i < 4 && $size > 1024; $i++) {
            $size >>= 10;
        }
        return $size . $niceFileSizeUnits[$i];
    }

    /**
     * Set file/directory mode based on the 'umask' config setting.
     *
     * @param string $path
     * @param int $mask
     *
     * @return bool
     */
    public function setMode($path, $mask)
    {
        $umask = Config::getVar('files', 'umask');
        if (!$umask) {
            return true;
        }
        return chmod($path, $mask & ~$umask);
    }

    /**
     * Parse the file extension from a filename/path.
     *
     * @param string $fileName
     *
     * @return string
     */
    public function parseFileExtension($fileName)
    {
        $fileParts = explode('.', $fileName);
        if (is_array($fileParts) && count($fileParts) > 1) {
            $fileExtension = $fileParts[count($fileParts) - 1];
        }

        // FIXME Check for evil
        if (!isset($fileExtension) || stristr($fileExtension, 'php') || strlen($fileExtension) > 6 || !preg_match('/^\w+$/', $fileExtension)) {
            $fileExtension = 'txt';
        }

        // consider .tar.gz extension
        if (strtolower(substr($fileName, -7)) == '.tar.gz') {
            $fileExtension = substr($fileName, -6);
        }

        return $fileExtension;
    }

    /**
     * Decompress passed gziped file.
     *
     * @throws \Exception
     *
     * @return string $filePath The file path that was created.
     *
     */
    public function gzDecompressFile(string $filePath): string
    {
        $bufferSize = 4096; // read 4kb at a time
        $destPath = str_replace('.gz', '', $filePath);

        // Open our files (in binary mode)
        $file = gzopen($filePath, 'rb');
        if ($file === false) {
            throw new Exception(__('admin.error.gzDecompressFile.gzopen', ['filePath' => $filePath]));
        }
        $destFile = fopen($destPath, 'wb');
        if ($destFile === false) {
            throw new Exception(__('admin.error.gzDecompressFile.fopen', ['filePath' => $destPath]));
        }
        while (!gzeof($file)) {
            // Read buffer-size bytes
            $contents = gzread($file, $bufferSize);
            if ($contents === false) {
                throw new Exception(__('admin.error.gzDecompressFile.gzread', ['file' => $file]));
            }
            if (fwrite($destFile, $contents) === false) {
                throw new Exception(__('admin.error.gzDecompressFile.fwrite', ['filePath' => $destFile]));
            }
        }
        $success = fclose($destFile);
        if (false === $success) {
            throw new Exception(__('admin.error.gzDecompressFile.fclose', ['filePath' => $destFile]));
        }
        $success = gzclose($file);
        if (false === $success) {
            throw new Exception(__('admin.error.gzDecompressFile.gzclose', ['filePath' => $file]));
        }

        if (unlink($filePath) === false) {
            throw new Exception(__('admin.error.gzDecompressFile.unlink', ['filePath' => $filePath]));
        }
        return $destPath;
    }

    /**
     * Compress passed file.
     *
     * @param string $filePath The file to be compressed.
     * @param int $level Compression level
     *
     * @throws \Exception
     *
     * @return string The file path that was created.
     *
     */
    public function gzCompressFile(string $filePath, int $level = 9): string
    {
        $bufferSize = 4096; // read 4kb at a time
        $destPath = $filePath . '.gz';
        $mode = 'wb' . $level;

        $destFile = gzopen($destPath, $mode);
        if ($destFile === false) {
            throw new Exception(__('admin.error.gzCompressFile.gzopen', ['filePath' => $destPath]));
        }
        $file = fopen($filePath, 'rb');
        if ($file === false) {
            throw new Exception(__('admin.error.gzCompressFile.fopen', ['filePath' => $filePath]));
        }
        while (!feof($file)) {
            $contents = fread($file, $bufferSize);
            if ($contents === false) {
                throw new Exception(__('admin.error.gzCompressFile.fread', ['filePath' => $file]));
            }
            if (gzwrite($destFile, $contents) === false) {
                throw new Exception(__('admin.error.gzCompressFile.gzwrite', ['filePath' => $destFile]));
            }
        }
        $success = fclose($file);
        if (false === $success) {
            throw new Exception(__('admin.error.gzCompressFile.fclose', ['filePath' => $file]));
        }
        $success = gzclose($destFile);
        if (false === $success) {
            throw new Exception(__('admin.error.gzCompressFile.gzclose', ['filePath' => $destFile]));
        }

        if (unlink($filePath) === false) {
            throw new Exception(__('admin.error.gzCompressFile.unlink', ['filePath' => $filePath]));
        }
        return $destPath;
    }

    /**
     * Attempts to create a locked and writable temporary file
     * The prefix/suffix will receive a minor sanitization
     */
    public static function getTemporaryFile(?string $prefix = null, ?string $suffix = null, ?int $lockType = LOCK_EX, int $retries = 10): SplFileObject
    {
        $sanitize = fn (string $path) => preg_replace('/[^\w.-]/', '', $path);
        $basePath = rtrim(sys_get_temp_dir(), '\\/') . "/";
        for ($retries = abs($retries); $retries--; ) {
            try {
                $file = new SplFileObject($basePath . $sanitize($prefix) . substr(md5(mt_rand()), 0, 10) . $sanitize($suffix), 'x+');
                if ($lockType) {
                    $file->flock(LOCK_EX) || throw new Exception('Failed to acquire lock');
                }
                return $file;
            } catch (Throwable $e) {
                error_log($e);
            }
        }
        throw new Exception('Failed to create temporary file');
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\file\FileManager', '\FileManager');
    foreach ([
        'FILE_MODE_MASK',
        'DIRECTORY_MODE_MASK',
        'DOCUMENT_TYPE_DEFAULT', 'DOCUMENT_TYPE_AUDIO', 'DOCUMENT_TYPE_EXCEL', 'DOCUMENT_TYPE_HTML', 'DOCUMENT_TYPE_IMAGE', 'DOCUMENT_TYPE_PDF', 'DOCUMENT_TYPE_WORD', 'DOCUMENT_TYPE_EPUB', 'DOCUMENT_TYPE_VIDEO', 'DOCUMENT_TYPE_ZIP',
    ] as $constantName) {
        define($constantName, constant('\FileManager::' . $constantName));
    }
}
