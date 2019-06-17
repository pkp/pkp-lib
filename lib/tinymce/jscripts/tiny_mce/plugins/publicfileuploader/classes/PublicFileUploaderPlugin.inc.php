<?php
/**
 * @class PublicFileUploaderPlugin
 */

class PublicFileUploaderPlugin {
	
	/* @var fileManager FileManager object (see lib/pkp/classes/file/FileManager.inc.php) */
	var $fileManager;
	
	/* @var baseDir string Path to the base OJS directory */
	var $baseDir;
	
	/* @var baseURL string URL the base OJS directory */
	var $baseUrl;
	
	/* @var fileURL string URL of a file in the upload directory */
	var $fileUrl;
	
	/* @var publicDir string Path to the public upload directory (beneath this directory are the journal directories) */
	var $publicDir;
	
	/* @var publicJournalDir string Path to the journal's upload directory */
	var $publicJournalDir;

	/* @var filePath string See publicJournalDir, but with ending slash */
	var $filePath;
	
	/* @var journalId int Id of the current journal */
	var $journalId;
	
	/* @var maxDirSize float Maximum size of a journal's directory */
	var $maxDirSize;
	
	/* @var maxFileNameLength int Maximum length (characters) of a filename displayed in the file manager */
	var $maxFileNameLength = 25;
	
	
	/**
	 * Constructor (see jbimages/integratePKP.php)
	 * @param $upload boolean
	 * @param $filemanager boolean
	 */
	function PublicFileUploaderPlugin($upload = false, $filemanager = false) {
		$this->baseDir = $_SERVER['SCRIPT_FILENAME'];
	
		for ($i = 0; $i < 9; $i++) $this->baseDir = dirname($this->baseDir);
		chdir($this->baseDir);
		define('INDEX_FILE_LOCATION', $this->baseDir . '/index.php');
		require($this->baseDir . '/lib/pkp/includes/bootstrap.inc.php');
		
		$this->baseUrl = Config::getVar('general', 'base_url');
		$this->publicDir = Config::getVar('files', 'files_dir') . '/publicuploads';
		$this->maxDirSize = $this->_getMaxDirSize();

		$sessionManager = SessionManager::getManager();
		$userSession = $sessionManager->getUserSession();
		$this->journalId = $userSession->getSessionVar('journalId');
		$user = $userSession->getUser();

		import('lib.pkp.classes.file.FileManager');
		$this->fileManager = new FileManager();
		
		if (isset($user)) {
			$this->publicJournalDir = $this->publicDir . '/' . $this->journalId;
			
			if (!file_exists($this->publicJournalDir)) {
				$this->fileManager->mkdir($this->publicJournalDir);
			}
			$this->filePath = $this->publicJournalDir . '/';
		} else {
			$this->filePath = null;
		}
		
		chdir(dirname($_SERVER['SCRIPT_FILENAME']));

		if ($upload == true) {
			$this->_upload();
		} elseif ($filemanager == true) {
			$this->_displayFilemanager();
		}
	}
	
	
	/**
	 * Upload a file into the current user's directory
	 * @return boolean
	 */
	function _upload() {
		$uploadName = 'public_file';
		$fileInfo = $_FILES[$uploadName];
		$fileName = $fileInfo['name'];
		$fileSize = $fileInfo['size'];
		$destName = $this->filePath . $fileName;
		
		if (!$this->fileManager->uploadError($uploadName)) {
			if ($this->_checkFileSize($fileSize) && $this->_checkFileExtension($fileName)) {
				if ($this->fileManager->uploadedFileExists($uploadName)) {
					if ($this->fileManager->uploadFile($uploadName, $destName)) {
						$this->_displaySuccessMsg($fileName);
						return true;
					}
				}
			}
		}

		$this->_displayUploadError();
		return false;
	}
	
	
	/**
	 * Get the maximum size of the public file directory (defined in config.inc.php)
	 * @return float
	 */
	function _getMaxDirSize() {
		$maxDirSize = $this->_convertFileSize(Config::getVar('files', 'public_files_max_size'));
		return $maxDirSize;
	}
	
	
	/**
	 * Filter dangerous filetypes
	 * @param $fileName string
	 * @return boolean
	 */
	function _checkFileExtension($fileName) {
		$fileExtension = '';
		$fileParts = explode('.', $fileName);
		$unwantedFileTypes = array('php','py','pl','rb','jsp','java','jar','sh','exe','bat');
		$pattern = '/' . implode('|', $unwantedFileTypes) . '/i';

		if (is_array($fileParts)) {
			$fileExtension = $fileParts[count($fileParts) - 1];
		}
		
		if ((strlen($fileExtension) < 2) || (strlen($fileExtension) > 6) || preg_match($pattern, $fileExtension)) {
			return false;
		} 
		return true;
	}
	
	
	/**
	 * Check file size of the uploaded file (mustn't exceed upload_max_filesize in php.ini)
	 * @param $fileSize float
	 * @return boolean
	 */
	function _checkFileSize($fileSize) {
		$maxUploadSize = $this->_convertFileSize(ini_get('upload_max_filesize'));

		if ($fileSize > $maxUploadSize) {
			return false;
		}
		
		/* check size of the whole directory */
		$newDirSize = $this->_getDirSize() + $fileSize;
		if($newDirSize > $this->maxDirSize) {
			return false;
		}
		
		return true;
	}
	
	
	/**
	 * Convert file size format from GByte, MByte, KByte to Byte
	 * @param $size string
	 * @return float
	 */
	function _convertFileSize($size) {
		$patternKbyte = '/k|kb/i';
		$patternMbyte = '/m|mb/i';
		$patternGbyte = '/g|gb/i';
		
		$fileSize = 0;
		
		if (preg_match($patternKbyte, $size)) {
			$fileSize = (float)preg_replace($patternKbyte, '', $size) * 1024;
		} elseif (preg_match($patternMbyte, $size)) {
			$fileSize = (float)preg_replace($patternMbyte, '', $size) * 1024 * 1024;
		} elseif (preg_match($patternGbyte, $size)) {
			$fileSize = (float)preg_replace($patternGbyte, '', $size) * 1024 * 1024 * 1024;
		}
		
		return $fileSize;
	}
	
	
	/**
	 * Filesize of the whole directory
	 * @return float
	 */
	function _getDirSize() {
		$files = $this->_getPublicFiles();
		$sum = 0;
		
		foreach($files as $file) {
			$sum += $file['size'];
		}
		return $sum;
	}
	
	
	/**
	 * Shorten filenames which are too long to display
	 * @param $fileName string
	 * @return string
	 */
	function _shortFileName($fileName) {
		$limit = $this->maxFileNameLength;
		if (strlen($fileName) > $limit) {
			return substr($fileName, 0, ($limit-3)) . '...';
		} 
		return $fileName;
	}
	
	
	/**
	 * Files in the current journal's public directory
	 * @return array
	 */
	function _getPublicFiles() { 
		$files = array();
		if ($dh = @opendir($this->publicJournalDir)) {
			while (($file = readdir($dh)) !== false) {
				if ($file != '.' && $file != '..') {
					$files[] = array('name' => $file, 'size' => filesize($this->publicJournalDir . '/' . $file));
				}
			}
			closedir($dh);
		}
		usort($files, create_function('$a,$b', 'return strcasecmp($a["name"], $b["name"]);'));
		return $files;
	}
	
	
	/**
	 * URL via which a file is accessible 
	 * (the directory containing these files should not be located in/under the web root)
	 * @param $fileName string
	 * @return string
	 */
	function _getFileUrl($fileName) {
		return $this->baseUrl . '/index.php/index/publicfiles/download/' . $this->journalId . '/' . $fileName;
	}
	
	
	
	/*********************/
	/* Display functions */
	/*********************/
	
	
	/**
	 * show upload error message
	 */
	function _displayUploadError() {
		$output = '<p>{#publicfileuploader_dlg.upload_error}!</p>';
		$output .= '<p><a href=\'upload_dialog.html\'>{#publicfileuploader_dlg.link_back_to_upload}</a></p>';
		
		echo $output;
	}
	
	
	/**
	 * show upload success message
	 * @param $fileName string
	 */
	function _displaySuccessMsg($fileName) {
		$fileUrl = $this->_getFileUrl($fileName);
		
		$output = '<p>{#publicfileuploader_dlg.upload_success}:</p>';
		$output .= '<div id=\'file_url\'><small>' . $fileUrl . '</small></div>';
		$output .= '<p class=\'upload_more\'><a href=\'upload_dialog.html\'>{#publicfileuploader_dlg.upload_more_files}</a></p>';
		$output .= '</div></body></html>';
		
		echo $output;
	}
	
	
	/**
	 * display the list of files in the user's directory
	 */
	function _displayFilemanager() {
		$output = '<strong>' . $this->publicJournalDir . '</strong>';

		$dirUrl = $this->baseUrl . '/index.php/index/publicfiles/delete/' . $this->journalId . '/';
		$files = $this->_getPublicFiles();

		if (!empty($files)) {
			$output .= '<small id=\'storage_info\'>{#publicfileuploader_dlg.storage_used} ' . $this->fileManager->getNiceFileSize($this->_getDirSize());
			$output .= ' {#publicfileuploader_dlg.of} ' . $this->fileManager->getNiceFileSize($this->maxDirSize) . '</small>';
			
			$output .= '<ul id=\'publicfileslist\'>';
			foreach($files as $file) {
				$fileName = $file['name'];
				$fileSize = $this->fileManager->getNiceFileSize($file['size']);
				$fileUrl = $this->_getFileUrl($fileName);
				
				$output .= '<li>' . $this->_shortFileName($fileName) . ' (' . $fileSize . ')';
				$output .= '<a href=\'#\' class=\'link_url\' onclick=\'toggleUrlField(this);\'>URL</a>';
				$output .= '<a href=\'' . $dirUrl . $fileName . '\' class=\'link_delete\' onclick=\'return confirm("{#publicfileuploader_dlg.confirm_delete}?");\'>{#publicfileuploader_dlg.delete_file}</a>';
				$output .= '</li>';
				$output .= '<li style=\'display:none\'><small>' . $fileUrl . '</small></li>';
			}
			$output .= '</ul>';
			$output .= '<p class=\'upload_more\'><a href=\'upload_dialog.html\'>{#publicfileuploader_dlg.upload_more_files}</a></p>';
		} else {
			$output .= '<p>{#publicfileuploader_dlg.no_files}</p>';
			$output .= '<p class=\'upload_more\'><a href=\'upload_dialog.html\'>{#publicfileuploader_dlg.upload_new_files}</a></p>';
		}
		$output .= '</div></body></html>';
		
		echo $output;
	}
}