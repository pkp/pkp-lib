<?php
/**
 * @file classes/linkAction/request/JsEventConfirmationModal.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class JsEventConfirmationModal
 *
 * @ingroup linkAction_request
 *
 * @brief Class defining a simple confirmation modal which generates a JS event and ok/cancel buttons.
 */

namespace PKP\linkAction\request;

use PKP\core\JSONMessage;

class JsEventConfirmationModal extends ConfirmationModal
{
    /** @var string The name of the event to be generated when this modal is confirmed */
    public $_event;

    /** @var array extra arguments to be passed to the JS controller */
    public $_extraArguments;

    /**
     * Constructor
     *
     * @param string $dialogText The localized text to appear
     *  in the dialog modal.
     * @param string $event the name of the JS event.
     * @param array $extraArguments (optional) extra information to be passed as JSON data with the event.
     * @param string $title (optional) The localized modal title.
     * @param string $titleIcon (optional) The icon to be used
     *  in the modal title bar.
     * @param string $okButton (optional) The localized text to
     *  appear on the confirmation button.
     * @param string $cancelButton (optional) The localized text to
     *  appear on the cancel button.
     * @param bool $canClose (optional) Whether the modal will
     *  have a close button.
     */
    public function __construct($dialogText, $event = 'confirmationModalConfirmed', $extraArguments = null, $title = null, $titleIcon = null, $okButton = null, $cancelButton = null, $canClose = true)
    {
        parent::__construct($dialogText, $title, $titleIcon, $okButton, $cancelButton, $canClose);

        $this->_event = $event;
        $this->_extraArguments = $extraArguments;
    }


    //
    // Getters and Setters
    //
    /**
     * Get the event.
     *
     * @return string
     */
    public function getEvent()
    {
        return $this->_event;
    }

    /**
     * Get the extra arguments.
     *
     * @return string
     */
    public function getExtraArguments()
    {
        return $this->_extraArguments;
    }


    //
    // Overridden methods from LinkActionRequest
    //
    /**
     * @see LinkActionRequest::getLocalizedOptions()
     */
    public function getLocalizedOptions()
    {
        $parentLocalizedOptions = parent::getLocalizedOptions();
        // override the modalHandler option.
        $parentLocalizedOptions['modalHandler'] = '$.pkp.controllers.modal.JsEventConfirmationModalHandler';
        $parentLocalizedOptions['jsEvent'] = $this->getEvent();
        if (is_array($this->getExtraArguments())) {
            $json = new JSONMessage();
            $json->setContent($this->getExtraArguments());
            $parentLocalizedOptions['extraArguments'] = $json->getString();
        }
        return $parentLocalizedOptions;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\linkAction\request\JsEventConfirmationModal', '\JsEventConfirmationModal');
}
