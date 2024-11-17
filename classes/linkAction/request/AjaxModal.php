<?php
/**
 * @file classes/linkAction/request/AjaxModal.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AjaxModal
 *
 * @ingroup linkAction_request
 *
 * @brief A modal that retrieves its content from via AJAX.
 */

namespace PKP\linkAction\request;

class AjaxModal extends Modal
{
    /** @var string The URL to be loaded into the modal. */
    public $_url;

    /**
     * Constructor
     *
     * @param string $url The URL of the AJAX resource to load into the modal.
     * @param string $title (optional) The localized modal title.
     * @param string $modalStyle (optional) The modal state/style to be used.
     * @param bool $canClose (optional) Whether the modal will have a close button.
     * @param string $closeOnFormSuccessId (optional) Close the modal when the
     *  form with this id fires a formSuccess event.
     * @param array $closeCleanVueInstances (optional) When the modal is closed
     *  destroy the registered vue instances with these ids
     */
    public function __construct(
        $url,
        $title = null,
        $modalStyle = null,
        $canClose = true,
        $closeOnFormSuccessId = null,
        $closeCleanVueInstances = []
    ) {
        parent::__construct($title, $modalStyle, $canClose, $closeOnFormSuccessId, $closeCleanVueInstances);

        $this->_url = $url;
    }


    //
    // Getters and Setters
    //
    /**
     * Get the URL to be loaded into the modal.
     *
     * @return string
     */
    public function getUrl()
    {
        return $this->_url;
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
                'modalHandler' => '$.pkp.controllers.modal.AjaxModalHandler',
                'url' => $this->getUrl(),
            ]
        );
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\linkAction\request\AjaxModal', '\AjaxModal');
}
