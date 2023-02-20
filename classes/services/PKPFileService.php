<?php
/**
 * @file classes/services/PKPFileService.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPFileService
 * @ingroup services
 *
 * @brief Helper class that encapsulates business logic for publications
 */

namespace PKP\services;

use APP\core\Application;
use Exception;
use Illuminate\Support\Facades\DB;

use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;
use PKP\config\Config;
use PKP\file\FileManager;
use PKP\plugins\Hook;

class PKPFileService
{
    /** @var Filesystem */
    public $fs;

    /**
     * Initialize and configure flysystem
     */
    public function __construct()
    {
        $umask = Config::getVar('files', 'umask', 0022);
        $adapter = new LocalFilesystemAdapter(
            Config::getVar('files', 'files_dir'),
            PortableVisibilityConverter::fromArray([
                'file' => [
                    'public' => FileManager::FILE_MODE_MASK & ~$umask,
                    'private' => FileManager::FILE_MODE_MASK & ~$umask,
                ],
                'dir' => [
                    'public' => DIRECTORY_MODE_MASK & ~$umask,
                    'private' => DIRECTORY_MODE_MASK & ~$umask,
                ]
            ]),
            LOCK_EX,
            LocalFilesystemAdapter::DISALLOW_LINKS
        );

        Hook::call('File::adapter', [&$adapter, $this]);

        $this->fs = new Filesystem($adapter);
    }

    /**
     * Get a file by its id
     *
     * @param int $id
     *
     * @return stdObject
     */
    public function get($id)
    {
        $file = DB::table('files')
            ->where('file_id', '=', $id)
            ->select(['file_id as id', 'path', 'mimetype'])
            ->first();
        return $file;
    }

    /**
     * Add a file
     *
     * @param string $from absolute path to file
     * @param string $to relative path in file dir
     *
     * @return int file id
     */
    public function add($from, $to)
    {
        $stream = fopen($from, 'r+');
        if (!$stream) {
            throw new Exception("Unable to copy ${from} to ${to}.");
        }
        $this->fs->writeStream($to, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }
        $mimetype = $this->fs->mimeType($to);

        // Check and override ambiguous mime types based on file extension
        if ($extension = pathinfo($to, PATHINFO_EXTENSION)) {
            $checkAmbiguous = strtolower($extension . ':' . $mimetype);
            if (array_key_exists($checkAmbiguous, $extensionsMap = \PKPString::getAmbiguousExtensionsMap())) {
                $mimetype = $extensionsMap[$checkAmbiguous];
            }
        }

        return DB::table('files')->insertGetId([
            'path' => $to,
            'mimetype' => $mimetype,
        ], 'file_id');
    }

    /**
     * Delete an uploaded file
     *
     * @param int $id
     *
     * @return File
     */
    public function delete($id)
    {
        $file = $this->get($id);
        if (!$file) {
            throw new Exception("Unable to locate file ${id}.");
        }
        $path = $file->path;
        if ($this->fs->has($path)) {
            try {
                $this->fs->delete($path);
            } catch (Exception $e) {
                throw new Exception("Unable to delete file ${id} at ${path}.");
            }
        }
        DB::table('files')
            ->where('file_id', '=', $file->id)
            ->delete();
    }

    /**
     * Download a file
     *
     * This method sends a HTTP response and ends the request handling.
     * No code will run after this method is called.
     *
     * @param int $fileId File ID
     * @param string $filename Filename to give to the downloaded file
     * @param bool $inline Whether to stream the file to the browser
     */
    public function download($fileId, $filename, $inline = false)
    {
        $file = $this->get($fileId);
        $dispatcher = Application::get()->getRequest()->getDispatcher();
        if (!$file) {
            $dispatcher->handle404();
        }

        $path = $file->path;
        if (!$this->fs->has($path)) {
            $dispatcher->handle404();
        }

        if (Hook::call('File::download', [$file, &$filename, $inline])) {
            return;
        }

        // Stream the file to the end user.
        $mimetype = $file->mimetype ?? 'application/octet-stream';
        $filesize = $this->fs->fileSize($path);
        $encodedFilename = urlencode($filename);
        header("Content-Type: ${mimetype}");
        header("Content-Length: ${filesize}");
        header('Accept-Ranges: none');
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . ";filename=\"${encodedFilename}\";filename*=UTF-8''${encodedFilename}");
        header('Cache-Control: private'); // Workarounds for IE weirdness
        header('Pragma: public');

        fpassthru($this->fs->readStream($path));
        exit;
    }

    /**
     * Convert a filename into a consistent format with the correct extension
     *
     * @param string $path Path to the file
     * @param string $filename Source filename to sanitize
     *
     * @return string
     */
    public function formatFilename($path, $filename)
    {
        $newFilename = $filename;
        # pattern extended to also capture captures .tar.gz extensions
        if (preg_match('/(\\.\\w{1,3})?\\.\\w+$/', $path, $extension)) {
            # If $newFilename has no/not the correct extension: Append extension
            if (strcasecmp(substr($newFilename, (strlen($extension[0]) * -1)), $extension[0]) != 0) {
                $newFilename .= $extension[0];
            }
        }
        Hook::call('File::formatFilename', [&$newFilename, $path, $filename]);

        return $newFilename;
    }

    /**
     * Get document type based on the mimetype
     *
     * @param string $mimetype
     *
     * @return string One of the FileManager::DOCUMENT_TYPE_ constants
     */
    public function getDocumentType($mimetype)
    {
        switch ($mimetype) {
            case 'application/pdf':
            case 'application/x-pdf':
            case 'text/pdf':
            case 'text/x-pdf':
                return FileManager::DOCUMENT_TYPE_PDF;
            case 'application/msword':
            case 'application/word':
            case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
                return FileManager::DOCUMENT_TYPE_WORD;
            case 'application/excel':
            case 'application/vnd.ms-excel':
            case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
                return FileManager::DOCUMENT_TYPE_EXCEL;
            case 'text/html':
                return FileManager::DOCUMENT_TYPE_HTML;
            case 'application/zip':
            case 'application/x-zip':
            case 'application/x-zip-compressed':
            case 'application/x-compress':
            case 'application/x-compressed':
            case 'multipart/x-zip':
                return FileManager::DOCUMENT_TYPE_ZIP;
            case 'application/epub':
            case 'application/epub+zip':
                return FileManager::DOCUMENT_TYPE_EPUB;
            case 'image/gif':
            case 'image/jpeg':
            case 'image/pjpeg':
            case 'image/png':
            case 'image/x-png':
            case 'image/vnd.microsoft.icon':
            case 'image/x-icon':
            case 'image/x-ico':
            case 'image/ico':
            case 'image/svg+xml':
            case 'image/svg':
                return FileManager::DOCUMENT_TYPE_IMAGE;
            case 'application/x-shockwave-flash':
            case 'video/x-flv':
            case 'application/x-flash-video':
            case 'flv-application/octet-stream':
            case 'video/mpeg':
            case 'video/quicktime':
            case 'video/mp4':
                return FileManager::DOCUMENT_TYPE_VIDEO;
            case 'audio/mpeg':
            case 'audio/x-aiff':
            case 'audio/x-wav':
                return FileManager::DOCUMENT_TYPE_AUDIO;
            default:
                return FileManager::DOCUMENT_TYPE_DEFAULT;
        }
    }

    /**
     * Get a pretty file size string
     *
     * Examples: 82B, 12KB, 2MB, 2GB
     *
     * @param int $size File size in bytes
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
}
