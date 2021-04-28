<?php
/**
 * @file classes/linkAction/request/WizardModal.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class WizardModal
 * @ingroup linkAction_request
 *
 * @brief A modal that contains a wizard retrieved via AJAX.
 */

namespace PKP\linkAction\request;

class WizardModal extends AjaxModal
{
    /**
     * Constructor
     *
     * @param $url string The URL of the AJAX resource to load into the wizard modal.
     * @param $title string (optional) The localized modal title.
     * @param $titleIcon string (optional) The icon to be used in the modal title bar.
     * @param $canClose boolean (optional) Whether the modal will have a close button.
     */
    public function __construct($url, $title = null, $titleIcon = null, $canClose = true)
    {
        parent::__construct($url, $title, $titleIcon, $canClose);
    }


    //
    // Overridden methods from LinkActionRequest
    //
    /**
     * @see LinkActionRequest::getLocalizedOptions()
     */
    public function getLocalizedOptions()
    {
        $options = parent::getLocalizedOptions();
        $options['modalHandler'] = '$.pkp.controllers.modal.WizardModalHandler';
        return $options;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\linkAction\request\WizardModal', '\WizardModal');
}
