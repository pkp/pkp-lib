<?php
/**
 * @class IntegratePKP
 *
 * Integrates PKP applications with the jbimages image upload utility for TinyMCE
 */


class IntegratePKP {
	/* @var $baseDir string Path to the base OxS directory */
	var $baseDir;

	/* @var $baseUrl string URL to the public uploads directory */
	var $baseUrl;

	/* @var imageDir String path to the user's image upload directory */
	var $imageDir;

	public function __construct() {
		// Get paths to system base directories
		$this->baseDir = $_SERVER['SCRIPT_FILENAME'];
		for ($i = 0; $i < 10; $i++) $this->baseDir = dirname($this->baseDir);

		// Load and execute initialization code
		chdir($this->baseDir);
		define('INDEX_FILE_LOCATION', $this->baseDir . '/index.php');
		require($this->baseDir . '/lib/pkp/includes/bootstrap.inc.php');

		$publicDir = Config::getVar('files', 'public_files_dir');
		$this->baseUrl = Config::getVar('general', 'base_url');

		// Skip locale detection
		define('SESSION_DISABLE_INIT', 1);

		// Register locale files in the registry
		$locale = LOCALE_DEFAULT;
		$localeFile = new LocaleFile(
			$locale,
			$this->baseDir . "/lib/pkp/locale/$locale/installer.xml"
		);
		Registry::get('localeFiles', true, array($locale => array($localeFile)));

		// Load user variables
		$sessionManager =& SessionManager::getManager();
		$userSession =& $sessionManager->getUserSession();
		$user =& $userSession->getUser();

		if (isset($user)) {
			// User is logged in
			$siteDir = $this->baseDir . '/' . $publicDir . '/site/';
			if (!file_exists($siteDir . '/images/')) {
				import('lib.pkp.classes.file.FileManager');
				$fileManager = new FileManager();

				// Check that the public/site/ directory exists and is writeable
				if(!file_exists($siteDir) || !is_writeable($siteDir)) {
					die(__('installer.installFilesDirError'));
				}
				// Create the images directory
				if (!$fileManager->mkdir($siteDir . '/images/')) {
					die(__('installer.installFilesDirError'));
				}
			}
			//Check if user's image directory exists, else create it
			if (Validation::isLoggedIn() && !file_exists($siteDir . '/images/' . $user->getUsername())) {
				import('lib.pkp.classes.file.FileManager');
				$fileManager = new FileManager();

				// Check that the public/site/images/ directory exists and is writeable
				if(!file_exists($siteDir . '/images/') || !is_writeable($siteDir . '/images/')) {
					die(__('installer.installFilesDirError'));
				}
				// Create the directory to store the user's images
				if (!$fileManager->mkdir($siteDir . '/images/' . $user->getUsername())) {
					die(__('installer.installFilesDirError'));
				}
				$this->imageDir = $publicDir . '/site/images/' . $user->getUsername();

			} else if (Validation::isLoggedIn()) {
				// User's image directory already exists
				$this->imageDir = $publicDir . '/site/images/' . $user->getUsername();
			}
		} else {
			// Not logged in; Do not allow images to be uploaded
			$this->imageDir = null;
		}

		// Set the base directory back to its original location
		chdir(dirname($_SERVER['SCRIPT_FILENAME']));
	}

	/**
	 * Get the absolute path to the user's image upload directory
	 * @return string
	 */
	public function getPKPImageUploadPath() {
		if (isset($this->baseDir) && isset($this->imageDir)) {
			return $this->baseDir . '/' . $this->imageDir;
		}

		die(__('installer.installFilesDirError'));
	}

	/**
	 * Get the URL (minus domain name) for the user's image upload directory
	 * @return string
	 */
	public function getPKPImageUrl() {
		if (isset($this->baseUrl) && isset($this->imageDir)) {
			$url = $this->baseUrl . '/' . $this->imageDir;
			$urlParts = parse_url($url);
			return $urlParts['path'];
		}

		die(__('installer.installFilesDirError'));
	}
}
?>
