<?php
/**
 * @file classes/linkAction/request/RemoteActionConfirmationModal.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RemoteActionConfirmationModal
 *
 * @ingroup linkAction_request
 *
 * @brief Class defining a simple confirmation modal with a remote action and ok/cancel buttons.
 */

namespace PKP\linkAction\request;

class RemoteActionConfirmationModal extends ConfirmationModal
{
    /** @var string A URL to be called when the confirmation button is clicked. */
    public $_remoteAction;

    /** @var string A CSRF token. */
    public $_csrfToken;

    /**
     * Constructor
     *
     * @param Session $session The user's session object.
     * @param string $dialogText The localized text to appear
     *  in the dialog modal.
     * @param string $title (optional) The localized modal title.
     * @param string $remoteAction (optional) A URL to be
     *  called when the confirmation button is clicked.
     * @param string $titleIcon (optional) The icon to be used
     *  in the modal title bar.
     * @param string $okButton (optional) The localized text to
     *  appear on the confirmation button.
     * @param string $cancelButton (optional) The localized text to
     *  appear on the cancel button.
     * @param bool $canClose (optional) Whether the modal will
     *  have a close button.
     */
    public function __construct($session, $dialogText, $title = null, $remoteAction = null, $titleIcon = null, $okButton = null, $cancelButton = null, $canClose = true)
    {
        parent::__construct($dialogText, $title, $titleIcon, $okButton, $cancelButton, $canClose);

        $this->_remoteAction = $remoteAction;
        $this->_csrfToken = $session->getCSRFToken();
    }


    //
    // Getters and Setters
    //
    /**
     * Get the remote action.
     *
     * @return string
     */
    public function getRemoteAction()
    {
        return $this->_remoteAction;
    }

    /**
     * Get the CSRF token.
     *
     * @return string
     */
    public function getCSRFToken()
    {
        return $this->_csrfToken;
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
                'modalHandler' => '$.pkp.controllers.modal.RemoteActionConfirmationModalHandler',
                'remoteAction' => $this->getRemoteAction(),
                'csrfToken' => $this->getCSRFToken(),
            ]
        );
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\linkAction\request\RemoteActionConfirmationModal', '\RemoteActionConfirmationModal');
}
