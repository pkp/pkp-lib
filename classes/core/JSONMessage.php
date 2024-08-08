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
    /** @var bool The status of an event (e.g. false if form validation fails). */
    public bool $_status;

    /** @var mixed The message to be delivered back to the calling script. */
    public mixed $_content;

    /** @var string ID for DOM element that will be replaced. */
    public string $_elementId;

    /** @var array List of JS events generated on the server side. */
    public array $_events = [];

    /** @var array Set of additional attributes for special cases. */
    public array $_additionalAttributes;

    /**
     * Constructor.
     *
     * @param $status The status of an event (e.g. false if form validation fails).
     * @param $content The message to be delivered back to the calling script.
     * @param $elementId The DOM element to be replaced.
     * @param $additionalAttributes Additional data to be returned.
     */
    public function __construct(bool $status = true, string|array $content = '', string $elementId = '0', array $additionalAttributes = [])
    {
        // Set internal state.
        $this->setStatus($status);
        $this->setContent($content);
        $this->setElementId($elementId);
        $this->setAdditionalAttributes($additionalAttributes);
    }

    /**
     * Get the status string
     */
    public function getStatus(): bool
    {
        return $this->_status;
    }

    /**
     * Set the status string
     */
    public function setStatus(bool $status)
    {
        $this->_status = $status;
    }

    /**
     * Get the content data
     */
    public function getContent(): mixed
    {
        return $this->_content;
    }

    /**
     * Set the content data
     */
    public function setContent(mixed $content)
    {
        $this->_content = $content;
    }

    /**
     * Get the elementId string
     */
    public function getElementId(): string
    {
        return $this->_elementId;
    }

    /**
     * Set the elementId string
     */
    public function setElementId(string $elementId)
    {
        $this->_elementId = $elementId;
    }

    /**
     * Set the event to trigger with this JSON message
     */
    public function setEvent(string $eventName, mixed $eventData = null)
    {
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
     * @param $eventData Global event data must be an assoc array
     */
    public function setGlobalEvent(string $eventName, array $eventData = [])
    {
        assert(is_array($eventData));
        $eventData['isGlobalEvent'] = true;
        $this->setEvent($eventName, $eventData);
    }

    /**
     * Get the events to trigger with this JSON message
     */
    public function getEvents(): array
    {
        return $this->_events;
    }

    /**
     * Get the additionalAttributes
     */
    public function getAdditionalAttributes(): array
    {
        return $this->_additionalAttributes;
    }

    /**
     * Set the additionalAttributes
     */
    public function setAdditionalAttributes(array $additionalAttributes)
    {
        $this->_additionalAttributes = $additionalAttributes;
    }

    /**
     * Construct a JSON string to use for AJAX communication, or false if an error occurred
     */
    public function getString(): string|bool
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
