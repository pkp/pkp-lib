<?php

/**
 * @file classes/core/JSON.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class JSON
 * @ingroup core
 *
 * @brief Class to build and manipulate JSON (Javascript Object Notation) objects.
 *
 */

// $Id$

class JSON {
	/** @var $status string The status of an event (e.g. false if form validation fails) */
	var $status;

	/** @var $content string The message to be delivered back to the calling script */
	var $content;

	/** @var $isScript string Whether the content is javascript that should be executed */
	var $isScript;

	/** @var $elementId string ID for DOM element that will be replaced */
	var $elementId;

	/** @var $additionalAttributes array Set of additional attributes for special cases*/
	var $additionalAttributes;

	/**
	* Constructor.
	* @param $status string The status of an event (e.g. false if form validation fails)
	* @param $content string The message to be delivered back to the calling script
	*/
	function JSON($status = 'true', $content = '', $isScript = 'false', $elementId = '0', $additionalAttributes = null) {
		$this->status = $status;
		$this->content = $this->json_encode($content);
		$this->isScript = $isScript;
		$this->elementId = $this->json_encode($elementId);
		if (isset($additionalAttributes)) {
			$this->additionalAttributes = $additionalAttributes;
		}
	}

	/**
	* Get the status string
	* @return string
	*/
	function getStatus () {
		return $this->status;
	}

	/**
	* Set the status string
	* @param $status string
	*/
	function setStatus($status) {
		$this->status = $status;
	}

	/**
	* Construct the content string
	* @return string
	*/
	function getContent() {
		return $this->content;
	}

	/**
	* Set the content string
	* @param $content string
	*/
	function setContent($content) {
		$this->content = $this->json_encode($content);
	}

	/**
	* Get the isScript string
	* @return string
	*/
	function getIsScript () {
		return $this->isScript;
	}

	/**
	* Set the isScript string
	* @param $isScript string
	*/
	function setIsScript($isScript) {
		$this->isScript = $isScript;
	}

	/**
	* Get the elementId string
	* @return string
	*/
	function getElementId () {
		return $this->elementId;
	}

	/**
	* Set the elementId string
	* @param $elementId string
	*/
	function setElementId($elementId) {
		$this->elementId = $this->json_encode($elementId);
	}

	/**
	* Get the additionalAttributes array
	* @return array
	*/
	function getAdditionalAttributes () {
		return $this->additionalAttributes;
	}

	/**
	* Set the additionalAttributes array
	* @param $additionalAttributes array
	*/
	function setAdditionalAttributes($additionalAttributes) {
		$this->additionalAttributes = $additionalAttributes;
	}
	/**
	* Construct a JSON string to use for AJAX communication
	* @return string
	*/
	function getString() {
		$jsonString = "{\"status\": $this->status, \"content\": $this->content, \"isScript\": $this->isScript, \"elementId\": $this->elementId";
			if(isset($this->additionalAttributes)) {
				foreach($this->additionalAttributes as $key => $value) {
					$jsonString .= ", \"$key\": " . $this->json_encode($value);
				}
			}
		$jsonString .= "}";

		return $jsonString;
	}

	/**
	 * encode a string for use with JSON
	 * Thanks to: http://usphp.com/manual/en/function.json-encode.php#82904
	 */
	function json_encode($a = false) {
		if (function_exists('json_encode')) {
			return json_encode($a);
		} else {
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
				}
				else {
					return $a;
				}
		    }
			$isList = true;
			for ($i = 0, reset($a); $i < count($a); $i++, next($a)) {
				if (key($a) !== $i) {
					$isList = false;
					break;
				}
			}
			$result = array();
			if ($isList) {
				foreach ($a as $v) $result[] = $this->json_encode($v);
				return '[' . join(',', $result) . ']';
			}
			else {
				foreach ($a as $k => $v) $result[] = $this->json_encode($k).':'.$this->json_encode($v);
				return '{' . join(',', $result) . '}';
			}
		}
	}
}

?>
