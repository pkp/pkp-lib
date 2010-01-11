<?php

/**
 * @file classes/core/DataObject.inc.php
 *
 * Copyright (c) 2000-2009 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class DataObject
 * @ingroup core
 * @see Core
 *
 * @brief Any class with an associated DAO should extend this class.
 */

// $Id$


class DataObject {
	/** Array of object data */
	var $_data;

	/**
	 * Constructor.
	 */
	function DataObject($callHooks = true) {
		$this->_data = array();
	}

	function &getLocalizedData($key) {
		$localePrecedence = Locale::getLocalePrecedence();
		foreach ($localePrecedence as $locale) {
			$value =& $this->getData($key, $locale);
			if (!empty($value)) return $value;
			unset($value);
		}

		// Fallback: Get the first available piece of data.
		$data =& $this->getData($key, null);
		if (!empty($data)) return $data[array_shift(array_keys($data))];

		// No data available; return null.
		unset($data);
		$data = null;
		return $data;
	}

	/**
	 * Get the value of a data variable.
	 * @param $key string
	 * @param $locale string (optional)
	 * @return mixed
	 */
	function &getData($key, $locale = null) {
		if (is_null($locale)) {
			if (isset($this->_data[$key])) {
				return $this->_data[$key];
			}
		} else {
			// see http://bugs.php.net/bug.php?id=29848
			if (isset($this->_data[$key]) && is_array($this->_data[$key]) && isset($this->_data[$key][$locale])) {
				// We cannot retrieve by reference here otherwise
				// we get "Cannot create references to/from string offsets"
				// in PHP 5.
				$value = $this->_data[$key][$locale];
				return $value;
			}
		}
		$nullVar = null;
		return $nullVar;
	}

	/**
	 * Set the value of a new or existing data variable.
	 * NB: Passing in null as a value will unset the
	 * data variable if it already existed.
	 * @param $key string
	 * @param $locale string (optional)
	 * @param $value mixed
	 */
	function setData($key, $value, $locale = null) {
		if (is_null($locale)) {
			if (is_null($value)) {
				if (isset($this->_data[$key])) unset($this->_data[$key]);
			} else {
				$this->_data[$key] = $value;
			}
		} else {
			if (is_null($value)) {
				// see http://bugs.php.net/bug.php?id=29848
				if (isset($this->_data[$key])) {
					if (is_array($this->_data[$key]) && isset($this->_data[$key][$locale])) unset($this->_data[$key][$locale]);
					// Was this the last entry for the data variable?
					if (empty($this->_data[$key])) unset($this->_data[$key]);
				}
			} else {
				$this->_data[$key][$locale] = $value;
			}
		}
	}

	/**
	 * Check whether a value exists for a given data variable.
	 * @param $key string
	 * @param $locale string (optional)
	 * @return boolean
	 */
	function hasData($key, $locale = null) {
		if (is_null($locale)) {
			return isset($this->_data[$key]);
		} else {
			// see http://bugs.php.net/bug.php?id=29848
			return isset($this->_data[$key]) && is_array($this->_data[$key]) && isset($this->_data[$key][$locale]);
		}
	}

	/**
	 * Return an array with all data variables.
	 * @return array
	 */
	function &getAllData() {
		return $this->_data;
	}

	/**
	 * Set all data variables at once.
	 * @param $data array
	 */
	function setAllData(&$data) {
		$this->_data =& $data;
	}

	/**
	 * Get ID of object.
	 * @return int
	 */
	function getId() {
		return $this->getData('id');
	}

	/**
	 * Set ID of object.
	 * @param $id int
	 */
	function setId($id) {
		return $this->setData('id', $id);
	}
}

?>
