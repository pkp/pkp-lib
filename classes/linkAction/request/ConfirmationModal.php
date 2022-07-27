<?php
/**
 * @file classes/linkAction/request/ConfirmationModal.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ConfirmationModal
 * @ingroup linkAction_request
 *
 * @brief Class defining a simple confirmation modal either with remote action or not.
 */

namespace PKP\linkAction\request;

class ConfirmationModal extends Modal
{
    /**
     * @var string A translation key defining the text for the confirmation
     * button of the modal.
     */
    public $_okButton;

    /**
     * @var string a translation key defining the text for the cancel
     * button of the modal.
     */
    public $_cancelButton;

    /**
     * @var string a translation key defining the text for the dialog
     *  text.
     */
    public $_dialogText;

    /**
     * Constructor
     *
     * @param string $dialogText The localized text to appear
     *  in the dialog modal.
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
    public function __construct($dialogText, $title = null, $titleIcon = 'modal_confirm', $okButton = null, $cancelButton = null, $canClose = true)
    {
        $title = (is_null($title) ? __('common.confirm') : $title);
        parent::__construct($title, $titleIcon, $canClose);

        $this->_okButton = (is_null($okButton) ? __('common.ok') : $okButton);
        $this->_cancelButton = (is_null($cancelButton) ? __('common.cancel') : $cancelButton);
        $this->_dialogText = $dialogText;
    }


    //
    // Getters and Setters
    //
    /**
     * Get the translation key for the confirmation
     * button text.
     *
     * @return string
     */
    public function getOkButton()
    {
        return $this->_okButton;
    }

    /**
     * Get the translation key for the cancel
     * button text.
     *
     * @return string
     */
    public function getCancelButton()
    {
        return $this->_cancelButton;
    }

    /**
     * Get the translation key for the dialog
     * text.
     *
     * @return string
     */
    public function getDialogText()
    {
        return $this->_dialogText;
    }


    //
    // Overridden methods from LinkActionRequest
    //
    /**
     * @see LinkActionRequest::getLocalizedOptions()
     */
    public function getLocalizedOptions()
    {
        return array_merge(parent::getLocalizedOptions(), [
            'modalHandler' => '$.pkp.controllers.modal.ConfirmationModalHandler',
            'okButton' => $this->getOkButton(),
            'cancelButton' => $this->getCancelButton(),
            'dialogText' => $this->getDialogText()]);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\linkAction\request\ConfirmationModal', '\ConfirmationModal');
}
