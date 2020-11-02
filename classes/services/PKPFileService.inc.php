<?php
/**
 * @file classes/services/PKPFileService.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPFileService
 * @ingroup services
 *
 * @brief Helper class that encapsulates business logic for publications
 */

namespace PKP\Services;

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
	 * Get a file path by its id
	 *
	 * @param int $id
	 * @return string
	 */
	public function getPath($id) {
		$file = Capsule::table('files')
			->where('file_id', '=', $id)
			->first();
		return $file ? $file->path : '';
	}

	/**
	 * Add a file
	 *
	 * @param string $from
	 * @param string $to
	 * @return int file id
	 */
	public function add($from, $to) {
		if (!$this->fs->copy($from, $to)) {
			throw new Exception("Unable to copy file $from to $to.");
		}
		return Capsule::table('files')->insertGetId(['path' => $to], 'file_id');
	}

	/**
	 * Delete an uploaded file
	 *
	 * @param int $id
	 * @return File
	 */
	public function delete($id) {
		$path = $this->getPath($id);
		if (!$path) {
			throw new Exception("Unable to locate file $id.");
		}
		if (!$this->fs->delete($path)) {
			throw new Exception("Unable to delete file $id at $path.");
		}
		Capsule::table('files')
			->where('file_id', '=', $id)
			->delete();
	}

	/**
	 * Download a file
	 *
	 * This method sends a HTTP response and ends the request handling.
	 * No code will run after this method is called.
	 *
	 * @param int|string $idOrPath The id or path to the file
	 * @param string $filename Filename to give to the downloaded file
	 * @param boolean $inline Whether to stream the file to the browser
	 */
	public function download($idOrPath, $filename, $inline = false) {

		if (is_int($idOrPath)) {
			$path = $this->getPath($idOrPath);
		} else {
			$path = $idOrPath;
		}

		if (!$this->fs->has($path)) {
			throw new Exception('File download failed because no file was found at ' . $path);
		}

		if (HookRegistry::call('File::download', [$idOrPath, &$filename, $inline])) {
			return;
		}

		// Stream the file to the end user.
		$mimetype = $this->fs->getMimetype($path) ?? 'application/octet-stream';
		$filesize = $this->fs->getSize($path);
		header("Content-Type: $mimetype");
		header("Content-Length: $filesize");
		header('Accept-Ranges: none');
		header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . "; filename=\"$filename\"");
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
		$extension = \Stringy\Stringy::create(pathinfo($path, PATHINFO_EXTENSION))->toLowerCase();
		$newFilename = \Stringy\Stringy::create($filename)->toLowerCase()->dasherize()->regexReplace('[^a-z0-9\-\_.]', '');
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
