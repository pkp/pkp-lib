<?php

/**
 * @file classes/site/VersionCheck.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class VersionCheck
 * @ingroup site
 * @see Version
 *
 * @brief Provides methods to check for the latest version of OJS.
 */

// $Id$


define('VERSION_CODE_PATH', 'dbscripts/xml/version.xml');

import('db.XMLDAO');
import('site.Version');

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
		if(isset($data['version'][0]['value']))
			$versionInfo['version'] = Version::fromString($data['release'][0]['value'], $data['application'][0]['value'], isset($data['type'][0]['value']) ? $data['type'][0]['value'] : null);

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
}

?>
