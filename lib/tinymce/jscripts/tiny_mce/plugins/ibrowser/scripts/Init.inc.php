<?php

// Get paths to system base directories
$baseDir = dirname(dirname(dirname(dirname(dirname(dirname(dirname(dirname(dirname($_SERVER['SCRIPT_FILENAME'])))))))));
$pkpBaseDir = dirname(dirname(dirname(dirname(dirname(dirname(dirname(dirname(__FILE__))))))));
$curDir = dirname(dirname(__FILE__));

// Load system files, initialize
if(basename($_SERVER['SCRIPT_FILENAME'], ".php") == "rfiles") $baseDir = dirname($baseDir);
define('INDEX_FILE_LOCATION', $baseDir . '/index.php');

// Load and execute initialization code
chdir($baseDir);
require($baseDir . '/lib/pkp/includes/bootstrap.inc.php');

// Manually set up a context router to get access
// to the application context (required by Locale).
$application =& PKPApplication::getApplication();
$request =& $application->getRequest();
import('core.PageRouter');
$router = new PageRouter();
$router->setApplication($application);
$request->setRouter($router);
AppLocale::initialize();
AppLocale::requireComponents(LOCALE_COMPONENT_PKP_INSTALLER);

// Load user variables
$sessionManager =& SessionManager::getManager();
$userSession =& $sessionManager->getUserSession();
$user =& $userSession->getUser();

// Insert system variables into associative array to be used by iBrowser
$init['publicDir'] = Config::getVar('files', 'public_files_dir');

if (isset($user)) {
	// User is logged in
	$init['user'] = $user->getUsername();
	$init['lang'] = getLocaleCode(AppLocale::getLocale());
	$init['baseUrl'] = Config::getVar('general', 'base_url');
	$init['baseDir'] =  $baseDir;

	$url = $router->url($request, null, 'user', 'viewCaptcha');
	$init['captchaPath'] = str_replace('/lib/pkp/lib/tinymce/jscripts/tiny_mce/plugins/ibrowser', '', $url);
} else {
	// User is not logged in; they may only enter a URL for image upload
	// Insert into array to be used by the config script
	$init['user'] = null;
	$init['lang'] = 'en';
	$init['baseUrl'] = Config::getVar('general', 'base_url');
	$init['baseDir'] =  Core::getBaseDir();
	$init['captchaPath'] = null;
}

function getLocaleCode($appLocale) {
	switch ($appLocale) {
		case 'cs_CZ': return 'cs';
		case 'da_DK': return 'da';
		case 'de_DE': return 'de';
		case 'en_US': return 'en';
		case 'es_ES': return 'es';
		case 'fr_CA': return 'fr';
		case 'it_IT': return 'it';
		case 'nl_NL': return 'nl';
		case 'pt_BR':
		case 'pt_PT': return 'pt_br';
		default: return 'en';
	}
}

?>
