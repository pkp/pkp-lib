<?php
/**
 * @file classes/filter/RuntimeEnvironment.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class RuntimeEnvironment
 * @ingroup filter
 *
 * @brief Class that describes a runtime environment.
 */

// $Id$


class RuntimeEnvironment {
	/** @var string */
	var $_phpVersionMin;

	/** @var string */
	var $_phpVersionMax;

	/** @var array */
	var $_phpExtensions;

	/** @var array */
	var $_externalPrograms;

	function RuntimeEnvironment($phpVersionMin, $phpVersionMax = null, $phpExtensions = array(), $externalPrograms = array()) {
		$this->_phpVersionMin = PHP_REQUIRED_VERSION;
		$this->_phpVersionMax = $phpVersionMax;
		$this->_phpExtensions = $phpExtensions;
		$this->_externalPrograms = $externalPrograms;
	}

	/**
	 * Checks whether the current runtime environment is
	 * compatible with the specified parameters.
	 * @return boolean
	 */
	function isCompatible() {
		// Check PHP version
		if (!checkPhpVersion($this->_phpVersionMin)) return false;
		if (version_compare(PHP_VERSION, $this->_phpVersionMax) === 1) return false;

		// Check PHP extensions
		foreach($this->_phpExtensions as $requiredExtension) {
			if(!extension_loaded($requiredExtension)) return false;
		}

		// Check external programs
		foreach($this->_externalPrograms as $requiredProgram) {
			$externalProgram = Config::getVar('cli', $requiredProgram);
			if (!file_exists($externalProgram)) return false;
			if (function_exists('is_executable')) {
				if (!is_executable($filename)) return false;
			}
		}

		// Compatibility check was successful
		return true;
	}
}
?>