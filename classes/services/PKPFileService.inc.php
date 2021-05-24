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

namespace PKP\Services;

use \Application;
use \Config;
use \Exception;
use \HookRegistry;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use Illuminate\Database\Capsule\Manager as Capsule;

class PKPFileService {

	/** @var Filesystem */
	public $fs;

	/**
	 * Initialize and configure flysystem
	 */
	public function __construct() {

		$adapter = new Local(
			Config::getVar('files', 'files_dir'),
			LOCK_EX,
			Local::DISALLOW_LINKS,
			[
				'file' => [
					'public' => FILE_MODE_MASK,
					'private' => FILE_MODE_MASK,
				],
				'dir' => [
					'public' => DIRECTORY_MODE_MASK,
					'private' => DIRECTORY_MODE_MASK,
				]
			]
		);

		HookRegistry::call('File::adapter', [&$adapter, $this]);

		$this->fs = new Filesystem($adapter);
	}

	/**
	 * Get a file by its id
	 *
	 * @param int $id
	 * @return stdObject
	 */
	public function get($id) {
		$file = Capsule::table('files')
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
	 * @return int file id
	 */
	public function add($from, $to) {
		$stream = fopen($from, 'r+');
		if (!$stream) {
			throw new Exception("Unable to copy $from to $to.");
		}
		if (!$this->fs->writeStream($to, $stream)) {
			throw new Exception("Unable to write file at $to.");
		}
		if (is_resource($stream)) {
			fclose($stream);
		}
		return Capsule::table('files')->insertGetId([
			'path' => $to,
			'mimetype' => $this->fs->getMimetype($to),
		], 'file_id');
	}

	/**
	 * Delete an uploaded file
	 *
	 * @param int $id
	 * @return File
	 */
	public function delete($id) {
		$file = $this->get($id);
		if (!$file) {
			throw new Exception("Unable to locate file $id.");
		}
		$path = $file->path;
		if ($this->fs->has($path) && !$this->fs->delete($path)) {
			throw new Exception("Unable to delete file $id at $path.");
		}
		Capsule::table('files')
			->where('file_id', '=', $file->id)
			->delete();
	}

	/**
	 * Download a file
	 *
	 * This method sends a HTTP response and ends the request handling.
	 * No code will run after this method is called.
	 *
	 * @param string|int $pathOrFileId The path to the file or file ID
	 * @param string $filename Filename to give to the downloaded file
	 * @param boolean $inline Whether to stream the file to the browser
	 */
	public function download($pathOrFileId, $filename, $inline = false) {

		$dispatcher = Application::get()->getRequest()->getDispatcher();

		if (is_int($pathOrFileId)) {
			// Is the file ID
			$fileId = $pathOrFileId;
			$file = $this->get($fileId);
			if (!$file) $dispatcher->handle404();
			$path = $file->path;
		} else {
			// Is the path to the file; compatibility fix, see pkp/pkp-lib#6663
			$path = $pathOrFileId;
		}

		if (!$this->fs->has($path)) {
			$dispatcher->handle404();
		}

		if (HookRegistry::call('File::download', [$path, &$filename, $inline])) {
			return;
		}

		// Stream the file to the end user.
		$mimetype = $file->mimetype ?? $this->fs->getMimetype($path) ?? 'application/octet-stream';
		$filesize = $this->fs->getSize($path);
		$encodedFilename = urlencode($filename);
		header("Content-Type: $mimetype");
		header("Content-Length: $filesize");
		header('Accept-Ranges: none');
		header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . ";filename=\"$encodedFilename\";filename*=UTF-8''\"$encodedFilename\"");
		header('Cache-Control: private'); // Workarounds for IE weirdness
		header('Pragma: public');

		fpassthru($this->fs->readStream($path));
		exit();
	}

	/**
	 * Convert a filename into a consistent format with the correct extension
	 *
	 * @param string $path Path to the file
	 * @param string $filename Source filename to sanitize
	 * @return string
	 */
	public function formatFilename($path, $filename) {
		$extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
		$newFilename = $filename;
		if (!empty($extension) && substr($newFilename, (strlen($extension) * -1)) != $extension) {
			$newFilename .= '.' . $extension;
		}

		HookRegistry::call('File::formatFilename', [&$newFilename, $path, $filename]);

		return $newFilename;
	}

	/**
	 * Get document type based on the mimetype
	 *
	 * @param string $mimetype
	 * @return string One of the DOCUMENT_TYPE_ constants
	 */
	public function getDocumentType($mimetype) {
		switch ($mimetype) {
			case 'application/pdf':
			case 'application/x-pdf':
			case 'text/pdf':
			case 'text/x-pdf':
				return DOCUMENT_TYPE_PDF;
			case 'application/msword':
			case 'application/word':
			case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
				return DOCUMENT_TYPE_WORD;
			case 'application/excel':
			case 'application/vnd.ms-excel':
			case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
				return DOCUMENT_TYPE_EXCEL;
			case 'text/html':
				return DOCUMENT_TYPE_HTML;
			case 'application/zip':
			case 'application/x-zip':
			case 'application/x-zip-compressed':
			case 'application/x-compress':
			case 'application/x-compressed':
			case 'multipart/x-zip':
				return DOCUMENT_TYPE_ZIP;
			case 'application/epub':
			case 'application/epub+zip':
				return DOCUMENT_TYPE_EPUB;
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
				return DOCUMENT_TYPE_IMAGE;
			case 'application/x-shockwave-flash':
			case 'video/x-flv':
			case 'application/x-flash-video':
			case 'flv-application/octet-stream':
			case 'video/mpeg':
			case 'video/quicktime':
			case 'video/mp4':
				return DOCUMENT_TYPE_VIDEO;
			case 'audio/mpeg':
			case 'audio/x-aiff':
			case 'audio/x-wav':
				return DOCUMENT_TYPE_AUDIO;
			default:
				return DOCUMENT_TYPE_DEFAULT;
		}
	}

	/**
	 * Get a pretty file size string
	 *
	 * Examples: 82B, 12KB, 2MB, 2GB
	 *
	 * @param integer $size File size in bytes
	 * @return string
	 */
	function getNiceFileSize($size) {
		$niceFileSizeUnits = array('B', 'KB', 'MB', 'GB');
		for($i = 0; $i < 4 && $size > 1024; $i++) {
			$size >>= 10;
		}
		return $size . $niceFileSizeUnits[$i];
	}
}
