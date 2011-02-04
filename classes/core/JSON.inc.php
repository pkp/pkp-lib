<?php

/**
 * @file classes/core/JSON.inc.php
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class JSON
 * @ingroup core
 *
 * @brief Class to build and manipulate JSON (Javascript Object Notation) objects.
 *
 */


class JSON {
	/** @var string The status of an event (e.g. false if form validation fails). */
	var $_status;

	/** @var string The message to be delivered back to the calling script. */
	var $_content;

	/** @var string Whether the content is javascript that should be executed. */
	var $_isScript;

	/** @var string ID for DOM element that will be replaced. */
	var $_elementId;

	/** @var array A JS event generated on the server side. */
	var $_event;

	/** @var array Set of additional attributes for special cases. */
	var $_additionalAttributes;

	/** @var boolean An internal variable used for unit testing only. */
	var $_simulatePhp4 = false;

	/**
	 * Constructor.
	 * @param $status boolean The status of an event (e.g. false if form validation fails).
	 * @param $content string The message to be delivered back to the calling script.
	 * @param $isScript boolean Whether the JSON returns a script. FIXME: see #6375 - scripts in JSON are evil.
	 * @param $elementId string The DOM element to be replaced.
	 * @param $additionalAttributes array Additional data to be returned.
	 */
	function JSON($status = true, $content = '', $isScript = false, $elementId = '0', $additionalAttributes = null) {
		// Set internal state.
		$this->setStatus($status);
		$this->setContent($content);
		$this->setIsScript($isScript);
		$this->setElementId($elementId);
		if (isset($additionalAttributes)) {
			$this->setAdditionalAttributes($additionalAttributes);
		}
	}

	/**
	 * Get the status string
	 * @return string
	 */
	function getStatus () {
		return $this->_status;
	}

	/**
	 * Set the status string
	 * @param $status string
	 */
	function setStatus($status) {
		assert(is_bool($status));
		$this->_status = $status;
	}

	/**
	 * Construct the content string
	 * @return string
	 */
	function getContent() {
		return $this->_content;
	}

	/**
	 * Set the content string
	 * @param $content string
	 */
	function setContent($content) {
		assert(is_string($content));
		$this->_content = $content;
	}

	/**
	* Get the isScript string
	* @return string
	*/
	function getIsScript () {
		return $this->_isScript;
	}

	/**
	 * Set the isScript string
	 * @param $isScript string
	 */
	function setIsScript($isScript) {
		assert(is_bool($isScript));
		$this->_isScript = $isScript;
	}

	/**
	 * Get the elementId string
	 * @return string
	 */
	function getElementId () {
		return $this->_elementId;
	}

	/**
	 * Set the elementId string
	 * @param $elementId string
	 */
	function setElementId($elementId) {
		assert(is_string($elementId));
		$this->_elementId = $elementId;
	}

	/**
	 * Set the event to trigger with this JSON message
	 * @param $eventName string
	 * @param $eventData string
	 */
	function setEvent($eventName, $eventData = null) {
		assert(is_string($eventName));

		// Construct the even as an associative array.
		$event = array('name' => $eventName);
		if(!is_null($eventData)) $event['data'] = $eventData;

		$this->_event = $event;
	}

	/**
	 * Get the event to trigger with this JSON message
	 * @return array
	 */
	function getEvent() {
		return $this->_event;
	}

	/**
	 * Get the additionalAttributes array
	 * @return array
	 */
	function getAdditionalAttributes () {
		return $this->_additionalAttributes;
	}

	/**
	 * Set the additionalAttributes array
	 * @param $additionalAttributes array
	 */
	function setAdditionalAttributes($additionalAttributes) {
		assert(is_array($additionalAttributes));
		$this->_additionalAttributes = $additionalAttributes;
	}

	/**
	 * Set to simulate a PHP4 environment.
	 * This is for internal use in unit tests only.
	 * @param $simulatePhp4 boolean
	 */
	function setSimulatePhp4($simulatePhp4) {
		assert(is_bool($simulatePhp4));
		$this->_simulatePhp4 = $simulatePhp4;
	}

	/**
	 * Construct a JSON string to use for AJAX communication
	 * @return string
	 */
	function getString() {
		// Construct an associative array that contains all information we require.
		$jsonObject = array(
			'status' => $this->getStatus(),
			'content' => $this->getContent(),
			'isScript' => $this->getIsScript(),
			'elementId' => $this->getElementId()
		);
		if(is_array($this->getAdditionalAttributes())) {
			foreach($this->getAdditionalAttributes() as $key => $value) {
				$jsonObject[$key] = $value;
			}
		}
		if(is_array($this->getEvent())) {
			$jsonObject['event'] = $this->getEvent();
		}

		// Encode the object.
		return $this->_json_encode($jsonObject);
	}


	//
	// Private helper methods
	//
	/**
	 * PHP4 compatible version of json_encode()
	 * Thanks to: http://usphp.com/manual/en/function.json-encode.php#82904
	 *
	 * @param $a mixed The content to encode.
	 * @return string The encoded content.
	 */
	function _json_encode($a = false) {
		if (function_exists('json_encode') && !$this->_simulatePhp4) {
			// Use the internal function if it exists.
			return json_encode($a);
		} else {
			// Deal with scalar variables.
			if (is_null($a)) return 'null';
			if ($a === false) return 'false';
			if ($a === true) return 'true';
			if (is_scalar($a)){
				if (is_float($a)) {
					// Always use "." for floats.
					return floatval(str_replace(",", ".", strval($a)));
				}
				if (is_string($a)) {
					static $jsonReplaces = array(array("\\", "/", "\n", "\t", "\r", "\b", "\f", '"'), array('\\\\', '\\/', '\\n', '\\t', '\\r', '\\b', '\\f', '\"'));
					return '"' . str_replace($jsonReplaces[0], $jsonReplaces[1], $a) . '"';
				} else {
					return $a;
				}
			}

			// Find out whether this is an indexed array
			// or an associative array/object.
			$isList = true;
			for ($i = 0, reset($a); $i < count($a); $i++, next($a)) {
				if (key($a) !== $i) {
					$isList = false;
					break;
				}
			}

			// Render the array/object.
			$result = array();
			if ($isList) {
				// Indexed lists.
				foreach ($a as $v) $result[] = $this->_json_encode($v);
				return '[' . join(',', $result) . ']';
			} else {
				// Objects or associative arrays.
				foreach ($a as $k => $v) $result[] = $this->_json_encode($k).':'.$this->_json_encode($v);
				return '{' . join(',', $result) . '}';
			}
		}
	}
}

?>
