<?php

/**
 * @file classes/core/JSONMessage.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class JSONMessage
 * @ingroup core
 *
 * @brief Class to represent a JSON (Javascript Object Notation) message.
 *
 */


class JSONMessage {
	/** @var string The status of an event (e.g. false if form validation fails). */
	var $_status;

	/** @var Mixed The message to be delivered back to the calling script. */
	var $_content;

	/** @var string ID for DOM element that will be replaced. */
	var $_elementId;

	/** @var array List of JS events generated on the server side. */
	var $_events;

	/** @var array Set of additional attributes for special cases. */
	var $_additionalAttributes;

	/**
	 * Constructor.
	 * @param $status boolean The status of an event (e.g. false if form validation fails).
	 * @param $content Mixed The message to be delivered back to the calling script.
	 * @param $elementId string The DOM element to be replaced.
	 * @param $additionalAttributes array Additional data to be returned.
	 */
	function __construct($status = true, $content = '', $elementId = '0', $additionalAttributes = null) {
		// Set internal state.
		$this->setStatus($status);
		$this->setContent($content);
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
	 * Get the content string
	 * @return mixed
	 */
	function getContent() {
		return $this->_content;
	}

	/**
	 * Set the content data
	 * @param $content mixed
	 */
	function setContent($content) {
		$this->_content = $content;
	}

	/**
	 * Get the elementId string
	 * @return string
	 */
	function getElementId() {
		return $this->_elementId;
	}

	/**
	 * Set the elementId string
	 * @param $elementId string
	 */
	function setElementId($elementId) {
		assert(is_string($elementId) || is_numeric($elementId));
		$this->_elementId = $elementId;
	}

	/**
	 * Set the event to trigger with this JSON message
	 * @param $eventName string
	 * @param $eventData mixed
	 */
	function setEvent($eventName, $eventData = null) {
		assert(is_string($eventName));

		// Construct the even as an associative array.
		$event = array('name' => $eventName);
		if(!is_null($eventData)) $event['data'] = $eventData;

		$this->_events[] = $event;
	}

	/**
	 * Set a global event to trigger with this JSON message
	 *
	 * This is a wrapper for the setEvent method.
	 *
	 * Global events are triggered on the global event router instead of being
	 * triggered directly on the handler. They are intended for broadcasting
	 * updates from one handler to other handlers.
	 *
	 * @param $eventName string
	 * @param $eventData array Global event data must be an assoc array
	 */
	function setGlobalEvent($eventName, $eventData = array()) {
		assert(is_array($eventData));
		$eventData['isGlobalEvent'] = true;
		$this->setEvent($eventName, $eventData);
	}

	/**
	 * Get the events to trigger with this JSON message
	 * @return array
	 */
	function getEvents() {
		return $this->_events;
	}

	/**
	 * Get the additionalAttributes array
	 * @return array
	 */
	function getAdditionalAttributes() {
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
	 * Construct a JSON string to use for AJAX communication
	 * @return string
	 */
	function getString() {
		// Construct an associative array that contains all information we require.
		$jsonObject = array(
			'status' => $this->getStatus(),
			'content' => $this->getContent(),
			'elementId' => $this->getElementId(),
			'events' => $this->getEvents(),
		);
		if(is_array($this->getAdditionalAttributes())) {
			foreach($this->getAdditionalAttributes() as $key => $value) {
				$jsonObject[$key] = $value;
			}
		}

		// Encode the object.
		$json = json_encode($jsonObject);

		if ($json === false) {
			error_log(json_last_error_msg());
		}

		return $json;
	}
}


