<?php
/**
 * @file classes/linkAction/request/RedirectConfirmationModal.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RedirectConfirmationModal
 *
 * @ingroup linkAction_request
 *
 * @brief Class defining a simple confirmation modal with a redirect url and ok/cancel buttons.
 */

namespace PKP\linkAction\request;

class RedirectConfirmationModal extends ConfirmationModal
{
    /** @var string A URL to be redirected to when the confirmation button is clicked. */
    public $_remoteUrl;

    /**
     * Constructor
     *
     * @param string $dialogText The localized text to appear
     *  in the dialog modal.
     * @param string $title (optional) The localized modal title.
     * @param string $remoteUrl (optional) A URL to be
     *  redirected to when the confirmation button is clicked.
     * @param string $titleIcon (optional) The icon to be used
     *  in the modal title bar.
     * @param string $okButton (optional) The localized text to
     *  appear on the confirmation button.
     * @param string $cancelButton (optional) The localized text to
     *  appear on the cancel button.
     * @param bool $canClose (optional) Whether the modal will
     *  have a close button.
     */
    public function __construct($dialogText, $title = null, $remoteUrl = null, $titleIcon = null, $okButton = null, $cancelButton = null, $canClose = true)
    {
        parent::__construct($dialogText, $title, $titleIcon, $okButton, $cancelButton, $canClose);

        $this->_remoteUrl = $remoteUrl;
    }


    //
    // Getters and Setters
    //
    /**
     * Get the remote url.
     *
     * @return string
     */
    public function getRemoteUrl()
    {
        return $this->_remoteUrl;
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
        $parentLocalizedOptions['modalHandler'] = '$.pkp.controllers.modal.RedirectConfirmationModalHandler';
        $parentLocalizedOptions['remoteUrl'] = $this->getRemoteUrl();
        return $parentLocalizedOptions;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\linkAction\request\RedirectConfirmationModal', '\RedirectConfirmationModal');
}
