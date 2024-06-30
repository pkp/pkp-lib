<?php

/**
 * @file classes/config/ConfigParser.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2014-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ConfigParser
 *
 * @brief Class for parsing and modifying php.ini style configuration files.
 */

namespace PKP\config;

class ConfigParser
{
    /**
     * Contents of the config file currently being parsed
     */
    protected string $content;

    /**
     * Read a configuration file into a multidimensional array.
     * This is a replacement for the PHP parse_ini_file function, which does not type setting values.
     *
     * @param string $file full path to the config file
     *
     * @return bool|array the configuration data (same format as http://php.net/parse_ini_file)
     */
    public static function &readConfig(string $file): bool|array
    {
        $configData = [];
        $currentSection = false;
        $falseValue = false;

        if (!file_exists($file) || !is_readable($file)) {
            return $falseValue;
        }

        $fp = fopen($file, 'rb');
        if (!$fp) {
            return $falseValue;
        }

        while (!feof($fp)) {
            $line = fgets($fp);
            $line = trim($line);
            if ($line === '' || strpos($line, ';') === 0) {
                // Skip empty or commented line
                continue;
            }

            if (preg_match('/^\[(.+)\]/', $line, $matches)) {
                // Found a section
                $currentSection = $matches[1];
                if (!isset($configData[$currentSection])) {
                    $configData[$currentSection] = [];
                }
            } elseif (strpos($line, '=') !== false) {
                // Found a setting
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // FIXME This may produce incorrect results if the line contains a comment
                if (preg_match('/^[\"\'](.*)[\"\']$/', $value, $matches)) {
                    // Treat value as a string
                    $value = stripslashes($matches[1]);
                } else {
                    preg_match('/^([\S]*)/', $value, $matches);
                    $value = $matches[1];

                    // Try to determine the type of the value
                    if ($value === '') {
                        $value = null;
                    } elseif (is_numeric($value)) {
                        if (strstr($value, '.')) {
                            // floating-point
                            $value = (float) $value;
                        } elseif (substr($value, 0, 2) == '0x') {
                            // hex
                            $value = intval($value, 16);
                        } elseif (substr($value, 0, 1) == '0') {
                            // octal
                            $value = intval($value, 8);
                        } else {
                            // integer
                            $value = (int) $value;
                        }
                    } elseif (strtolower($value) == 'true' || strtolower($value) == 'on') {
                        $value = true;
                    } elseif (strtolower($value) == 'false' || strtolower($value) == 'off') {
                        $value = false;
                    } elseif (defined($value)) {
                        // The value matches a named constant
                        $value = constant($value);
                    }
                }

                if ($currentSection === false) {
                    $configData[$key] = $value;
                } elseif (is_array($configData[$currentSection])) {
                    $configData[$currentSection][$key] = $value;
                }
            }
        }

        fclose($fp);

        return $configData;
    }

    /**
     * Read a configuration file and update variables.
     * This method stores the updated configuration but does not write it out.
     * Use writeConfig() or getFileContents() afterwards to do something with the new config.
     *
     * @param string $file full path to the config file
     * @param array $params an associative array of configuration parameters to update. If the value is an associative array (of variable name/value pairs) instead of a scalar, the key is treated as a section instead of a variable. Parameters not in $params remain unchanged
     *
     * @return bool true if file could be read, false otherwise
     */
    public function updateConfig(string $file, array $params): bool
    {
        if (!file_exists($file) || !is_readable($file)) {
            return false;
        }

        $this->content = '';
        $lines = file($file);

        // Parse each line of the configuration file
        $currentSection = null;
        for ($i = 0, $count = count($lines); $i < $count; $i++) {
            $line = $lines[$i];

            if (preg_match('/^;/', $line) || preg_match('/^\s*$/', $line)) {
                // Comment or empty line
                $this->content .= $line;
            } elseif (preg_match('/^\s*\[(\w+)\]/', $line, $matches)) {
                // Start of new section
                $currentSection = $matches[1];
                $this->content .= $line;
            } elseif (preg_match('/^\s*(\w+)\s*=/', $line, $matches)) {
                // Variable definition
                $key = $matches[1];

                if (!isset($currentSection) && array_key_exists($key, $params) && !is_array($params[$key])) {
                    // Variable not in a section
                    $value = $params[$key];
                } elseif (isset($params[$currentSection]) && is_array($params[$currentSection]) && array_key_exists($key, $params[$currentSection])) {
                    // Variable in a section
                    $value = $params[$currentSection][$key];
                } else {
                    // Variable not to be changed, do not modify line
                    $this->content .= $line;
                    continue;
                }

                if (preg_match('/[^\w\-\/]/', $value)) {
                    // Escape strings containing non-alphanumeric characters
                    $valueString = '"' . addslashes($value) . '"';
                } else {
                    $valueString = $value;
                }

                $this->content .= "{$key} = {$valueString}\n";
            } else {
                $this->content .= $line;
            }
        }

        return true;
    }

    /**
     * Write contents of current config file
     *
     * @param string $file full path to output file
     *
     * @return bool file write is successful
     */
    public function writeConfig(string $file): bool
    {
        if (!(file_exists($file) && is_writable($file))
            && !(!file_exists($file) && is_dir(dirname($file)) && is_writable(dirname($file)))) {
            // File location cannot be written to
            return false;
        }

        $fp = @fopen($file, 'wb');
        if (!$fp) {
            return false;
        }

        fwrite($fp, $this->content);
        fclose($fp);
        return true;
    }

    /**
     * Return the contents of the current config file.
     */
    public function getFileContents(): string
    {
        return $this->content;
    }

    /**
     * Set the content of file
     */
    public function setFileContent(string $content): static
    {
        $this->content = $content;

        return $this;
    }
}
