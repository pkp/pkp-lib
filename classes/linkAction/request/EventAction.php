<?php
/**
 * @file classes/linkAction/request/EventAction.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EventAction
 * @ingroup linkAction_request
 *
 * @brief This action triggers a Javascript event.
 */

namespace PKP\linkAction\request;

class EventAction extends LinkActionRequest
{
    /** @var string Target selector */
    public $targetSelector;

    /** @var string Event name */
    public $eventName;

    /** @var array Event options */
    public $options;

    /**
     * Constructor
     *
     * @param string $targetSelector Selector for target to receive event.
     * @param string $eventName Name of Javascript event to trigger.
     */
    public function __construct($targetSelector, $eventName, $options = [])
    {
        parent::__construct();
        $this->targetSelector = $targetSelector;
        $this->eventName = $eventName;
        $this->options = $options;
    }


    //
    // Overridden protected methods from LinkActionRequest
    //
    /**
     * @see LinkActionRequest::getJSLinkActionRequest()
     */
    public function getJSLinkActionRequest()
    {
        return '$.pkp.classes.linkAction.EventAction';
    }

    /**
     * @see LinkActionRequest::getLocalizedOptions()
     */
    public function getLocalizedOptions()
    {
        return array_merge(
            $this->options,
            [
                'target' => $this->targetSelector,
                'event' => $this->eventName,
            ]
        );
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\linkAction\request\EventAction', '\EventAction');
}
