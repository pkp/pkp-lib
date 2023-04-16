<?php

/**
 * @file classes/core/JSONMessage.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class JSONMessage
 *
 * @ingroup core
 *
 * @brief Class to represent a JSON (Javascript Object Notation) message.
 *
 */

namespace PKP\core;

class JSONMessage
{
    /** @var string The status of an event (e.g. false if form validation fails). */
    public $_status;

    /** @var Mixed The message to be delivered back to the calling script. */
    public $_content;

    /** @var string ID for DOM element that will be replaced. */
    public $_elementId;

    /** @var array List of JS events generated on the server side. */
    public $_events;

    /** @var array Set of additional attributes for special cases. */
    public $_additionalAttributes;

    /**
     * Constructor.
     *
     * @param bool $status The status of an event (e.g. false if form validation fails).
     * @param Mixed $content The message to be delivered back to the calling script.
     * @param string $elementId The DOM element to be replaced.
     * @param array $additionalAttributes Additional data to be returned.
     */
    public function __construct($status = true, $content = '', $elementId = '0', $additionalAttributes = null)
    {
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
     *
     * @return string
     */
    public function getStatus()
    {
        return $this->_status;
    }

    /**
     * Set the status string
     *
     * @param string $status
     */
    public function setStatus($status)
    {
        assert(is_bool($status));
        $this->_status = $status;
    }

    /**
     * Get the content string
     */
    public function getContent()
    {
        return $this->_content;
    }

    /**
     * Set the content data
     *
     */
    public function setContent($content)
    {
        $this->_content = $content;
    }

    /**
     * Get the elementId string
     *
     * @return string
     */
    public function getElementId()
    {
        return $this->_elementId;
    }

    /**
     * Set the elementId string
     *
     * @param string $elementId
     */
    public function setElementId($elementId)
    {
        assert(is_string($elementId) || is_numeric($elementId));
        $this->_elementId = $elementId;
    }

    /**
     * Set the event to trigger with this JSON message
     *
     * @param string $eventName
     * @param null|mixed $eventData
     */
    public function setEvent($eventName, $eventData = null)
    {
        assert(is_string($eventName));

        // Construct the even as an associative array.
        $event = ['name' => $eventName];
        if (!is_null($eventData)) {
            $event['data'] = $eventData;
        }

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
     * @param string $eventName
     * @param array $eventData Global event data must be an assoc array
     */
    public function setGlobalEvent($eventName, $eventData = [])
    {
        assert(is_array($eventData));
        $eventData['isGlobalEvent'] = true;
        $this->setEvent($eventName, $eventData);
    }

    /**
     * Get the events to trigger with this JSON message
     *
     * @return array
     */
    public function getEvents()
    {
        return $this->_events;
    }

    /**
     * Get the additionalAttributes array
     *
     * @return array
     */
    public function getAdditionalAttributes()
    {
        return $this->_additionalAttributes;
    }

    /**
     * Set the additionalAttributes array
     *
     * @param array $additionalAttributes
     */
    public function setAdditionalAttributes($additionalAttributes)
    {
        assert(is_array($additionalAttributes));
        $this->_additionalAttributes = $additionalAttributes;
    }

    /**
     * Construct a JSON string to use for AJAX communication
     *
     * @return string
     */
    public function getString()
    {
        // Construct an associative array that contains all information we require.
        $jsonObject = [
            'status' => $this->getStatus(),
            'content' => $this->getContent(),
            'elementId' => $this->getElementId(),
            'events' => $this->getEvents(),
        ];
        if (is_array($this->getAdditionalAttributes())) {
            foreach ($this->getAdditionalAttributes() as $key => $value) {
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

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\core\JSONMessage', '\JSONMessage');
}
