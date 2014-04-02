<?php

/**
 * @file classes/site/VersionCheck.inc.php
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class VersionCheck
 * @ingroup site
 * @see Version
 *
 * @brief Provides methods to check for the latest version of OJS.
 */


define('VERSION_CODE_PATH', 'dbscripts/xml/version.xml');

import('lib.pkp.classes.db.XMLDAO');
import('lib.pkp.classes.site.Version');

class VersionCheck {

	/**
	 * Return information about the latest available version.
	 * @return array
	 */
	function &getLatestVersion() {
		$application =& PKPApplication::getApplication();
		$returner =& VersionCheck::parseVersionXML(
			$application->getVersionDescriptorUrl()
		);
		return $returner;
	}

	/**
	 * Return the currently installed database version.
	 * @return Version
	 */
	function &getCurrentDBVersion() {
		$versionDao =& DAORegistry::getDAO('VersionDAO');
		$dbVersion =& $versionDao->getCurrentVersion();
		return $dbVersion;
	}

	/**
	 * Return the current code version.
	 * @return Version
	 */
	function &getCurrentCodeVersion() {
		$versionInfo = VersionCheck::parseVersionXML(VERSION_CODE_PATH);
		if ($versionInfo) {
			$version = $versionInfo['version'];
		} else {
			$version = false;
		}
		return $version;
	}

	/**
	 * Parse information from a version XML file.
	 * @return array
	 */
	function &parseVersionXML($url) {
		$xmlDao = new XMLDAO();
		$data = $xmlDao->parseStruct($url, array());
		if (!$data) {
			$result = false;
			return $result;
		}

		// FIXME validate parsed data?
		$versionInfo = array();

		if(isset($data['application'][0]['value']))
			$versionInfo['application'] = $data['application'][0]['value'];
		if(isset($data['type'][0]['value']))
			$versionInfo['type'] = $data['type'][0]['value'];
		if(isset($data['release'][0]['value']))
			$versionInfo['release'] = $data['release'][0]['value'];
		if(isset($data['tag'][0]['value']))
			$versionInfo['tag'] = $data['tag'][0]['value'];
		if(isset($data['date'][0]['value']))
			$versionInfo['date'] = $data['date'][0]['value'];
		if(isset($data['info'][0]['value']))
			$versionInfo['info'] = $data['info'][0]['value'];
		if(isset($data['package'][0]['value']))
			$versionInfo['package'] = $data['package'][0]['value'];
		if(isset($data['patch'][0]['value'])) {
			$versionInfo['patch'] = array();
			foreach ($data['patch'] as $patch) {
				$versionInfo['patch'][$patch['attributes']['from']] = $patch['value'];
			}
		}
		if(isset($data['class'][0]['value']))
			$versionInfo['class'] = (string) $data['class'][0]['value'];
		$versionInfo['lazy-load'] = (isset($data['lazy-load'][0]['value']) ? (int) $data['lazy-load'][0]['value'] : 0);
		$versionInfo['sitewide'] = (isset($data['sitewide'][0]['value']) ? (int) $data['sitewide'][0]['value'] : 0);

		if(isset($data['release'][0]['value']) && isset($data['application'][0]['value'])) {
			$version =& Version::fromString(
				$data['release'][0]['value'],
				isset($data['type'][0]['value']) ? $data['type'][0]['value'] : null,
				$data['application'][0]['value'],
				isset($data['class'][0]['value']) ? $data['class'][0]['value'] : '',
				$versionInfo['lazy-load'],
				$versionInfo['sitewide']
			);
			$versionInfo['version'] =& $version;
		}

		return $versionInfo;
	}

	/**
	 * Find the applicable patch for the current code version (if available).
	 * @param $versionInfo array as returned by parseVersionXML()
	 * @param $codeVersion as returned by getCurrentCodeVersion()
	 * @return string
	 */
	function getPatch(&$versionInfo, $codeVersion = null) {
		if (!isset($codeVersion)) {
			$codeVersion =& VersionCheck::getCurrentCodeVersion();
		}
		if (isset($versionInfo['patch'][$codeVersion->getVersionString()])) {
			return $versionInfo['patch'][$codeVersion->getVersionString()];
		}
		return null;
	}

	/**
	 * Checks whether the given version file exists and whether it
	 * contains valid data. Returns a Version object if everything
	 * is ok, otherwise null. If $returnErroMsg is true, returns the
	 * error message.
	 *
	 * @param $versionFile string
	 * @param $returnErrorMesg boolean
	 * @return Version or null/string if invalid or missing version file
	 */
	function &getValidPluginVersionInfo($versionFile, $returnErrorMsg = false) {
		$nullVar = null;
		$errorMsg = null;
		$fileManager = new FileManager();
		if ($fileManager->fileExists($versionFile)) {
			$versionInfo =& VersionCheck::parseVersionXML($versionFile);
		} else {
			$errorMsg = 'manager.plugins.versionFileNotFound';
		}

		// Validate plugin name and type to avoid abuse
		if (is_null($errorMsg)) {
			$productType = explode(".", $versionInfo['type']);
			if(count($productType) != 2 || $productType[0] != 'plugins') {
				$errorMsg = 'manager.plugins.versionFileInvalid';
			}
		}

		if (is_null($errorMsg)) {
			$pluginVersion =& $versionInfo['version'];
			$namesToValidate = array($pluginVersion->getProduct(), $productType[1]);
			foreach($namesToValidate as $nameToValidate) {
				if (!String::regexp_match('/[a-z][a-zA-Z0-9]+/', $nameToValidate)) {
					$errorMsg = 'manager.plugins.versionFileInvalid';
					break;
				}
			}
		}

		if ($errorMsg) {
			if ($returnErrorMsg) {
				return $errorMsg;
			} else {
				$templateMgr =& TemplateManager::getManager();
				$templateMgr->assign('message', $errorMsg);
				return $nullVar;
			}
		} else {
			return $pluginVersion;
		}
	}

	/**
	 * Checks the application's version against the latest version 
	 * on the PKP servers.
	 * @return string or false if no newer version
	 */
	function checkIfNewVersionExists() {
		$versionInfo =& VersionCheck::getLatestVersion();
		$latestVersion = $versionInfo['release'];

		$currentVersion =& VersionCheck::getCurrentDBVersion();
		if($currentVersion->compare($latestVersion) < 0) return $latestVersion;
		else return false;
	}
}

?>
