<?php
/**
 * @file classes/core/RuntimeEnvironment.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RuntimeEnvironment
 * @ingroup core
 *
 * @brief Class that describes a runtime environment.
 */

namespace PKP\core;

class RuntimeEnvironment
{
    /** @var string */
    public $_phpVersionMin;

    /** @var string */
    public $_phpVersionMax;

    /** @var array */
    public $_phpExtensions;

    /** @var array */
    public $_externalPrograms;

    public function __construct($phpVersionMin = PKPApplication::PHP_REQUIRED_VERSION, $phpVersionMax = null, $phpExtensions = [], $externalPrograms = [])
    {
        $this->_phpVersionMin = $phpVersionMin;
        $this->_phpVersionMax = $phpVersionMax;
        $this->_phpExtensions = $phpExtensions;
        $this->_externalPrograms = $externalPrograms;
    }

    //
    // Setters and Getters
    //
    /**
     * Get the min required PHP version
     *
     * @return string
     */
    public function getPhpVersionMin()
    {
        return $this->_phpVersionMin;
    }

    /**
     * Get the max required PHP version
     *
     * @return string
     */
    public function getPhpVersionMax()
    {
        return $this->_phpVersionMax;
    }

    /**
     * Get the required PHP extensions
     *
     * @return array
     */
    public function getPhpExtensions()
    {
        return $this->_phpExtensions;
    }

    /**
     * Get the required external programs
     *
     * @return array
     */
    public function getExternalPrograms()
    {
        return $this->_externalPrograms;
    }


    //
    // Public methods
    //
    /**
     * Checks whether the current runtime environment is
     * compatible with the specified parameters.
     *
     * @return bool
     */
    public function isCompatible()
    {
        // Check PHP version
        if (!is_null($this->_phpVersionMin) && version_compare(PHP_VERSION, $this->_phpVersionMin) < 0) {
            return false;
        }
        if (!is_null($this->_phpVersionMax) && version_compare(PHP_VERSION, $this->_phpVersionMax) > 0) {
            return false;
        }

        // Check PHP extensions
        foreach ($this->_phpExtensions as $requiredExtension) {
            if (!extension_loaded($requiredExtension)) {
                return false;
            }
        }

        // Check external programs
        foreach ($this->_externalPrograms as $requiredProgram) {
            $externalProgram = Config::getVar('cli', $requiredProgram);
            if (!file_exists($externalProgram)) {
                return false;
            }
            if (function_exists('is_executable')) {
                if (!is_executable($externalProgram)) {
                    return false;
                }
            }
        }

        // Compatibility check was successful
        return true;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\core\RuntimeEnvironment', '\RuntimeEnvironment');
}
