<?php
/**
 * @file classes/linkAction/request/AjaxModal.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class VueModal
 *
 * @ingroup linkAction_request
 *
 * @brief A modal that open native Vue.js side modal.
 */

namespace PKP\linkAction\request;

class VueModal extends Modal
{
    /** @var string Component name, needs to be registered in ModalManager.vue. */
    public $_component;

    /** @var string Component props */

    public $_props;

    /**
     * Constructor
     *
     */
    public function __construct(
        $component,
        $props,
    ) {
        parent::__construct($component, $props);

        $this->_component = $component;
        $this->_props = $props;
    }


    //
    // Getters and Setters
    //
    /**
     * Get the Component name.
     *
     * @return string
     */
    public function getComponent()
    {
        return $this->_component;
    }


    /**
     * Get the Props for Vue.js modal.
     *
     * @return string
     */
    public function getProps()
    {
        return $this->_props;
    }

    //
    // Overridden methods from LinkActionRequest
    //
    /**
     * @see LinkActionRequest::getLocalizedOptions()
     */
    public function getLocalizedOptions()
    {
        return array_merge(
            parent::getLocalizedOptions(),
            [
                'modalHandler' => '$.pkp.controllers.modal.VueModalHandler',
                'component' => $this->getComponent(),
                'props' => $this->getProps()

            ]
        );
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\linkAction\request\VueModal', '\VueModal');
}
