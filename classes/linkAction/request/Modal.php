<?php
/**
 * @file classes/linkAction/request/Modal.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Modal
 *
 * @ingroup linkAction_request
 *
 * @brief Abstract base class for all modal dialogs.
 */

namespace PKP\linkAction\request;

define('MODAL_WIDTH_DEFAULT', '710');
define('MODAL_WIDTH_AUTO', 'auto');


class Modal extends LinkActionRequest
{
    /** @var string The localized title of the modal. */
    public $_title;

    /** @var string The icon to be displayed in the title bar. */
    public $_titleIcon;

    /** @var bool Whether the modal has a close icon in the title bar. */
    public $_canClose;

    /** @var string The id of a form which should close the modal when completed */
    public $_closeOnFormSuccessId;

    /** @var array The id of any Vue instances that must be destroyed when modal closed */
    public $_closeCleanVueInstances;

    /** @var string Text for the close button */
    public $_closeButtonText;

    /**
     * Constructor
     *
     * @param string $title (optional) The localized modal title.
     * @param string $titleIcon (optional) The icon to be used in the modal title bar.
     * @param bool $canClose (optional) Whether the modal will have a close button.
     * @param string $closeOnFormSuccessId (optional) Close the modal when the
     *  form with this id fires a formSuccess event.
     * @param array $closeCleanVueInstances (optional) When the modal is closed
     *  destroy the registered vue instances with these ids
     */
    public function __construct(
        $title = null,
        $titleIcon = null,
        $canClose = true,
        $closeOnFormSuccessId = null,
        $closeCleanVueInstances = []
    ) {
        parent::__construct();
        $this->_title = $title;
        $this->_titleIcon = $titleIcon;
        $this->_canClose = $canClose;
        $this->_closeOnFormSuccessId = $closeOnFormSuccessId;
        $this->_closeCleanVueInstances = $closeCleanVueInstances;
        // @todo this should be customizable via an option
        $this->_closeButtonText = __('common.closePanel');
    }


    //
    // Getters and Setters
    //
    /**
     * Get the localized title.
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->_title;
    }

    /**
     * Get the title bar icon.
     *
     * @return string
     */
    public function getTitleIcon()
    {
        return $this->_titleIcon;
    }

    /**
     * Whether the modal has a close icon in the title bar.
     *
     * @return bool
     */
    public function getCanClose()
    {
        return $this->_canClose;
    }

    /**
     * Get the text to be displayed on the close button for screen readers
     */
    public function getCloseButtonText()
    {
        return $this->_closeButtonText;
    }


    //
    // Overridden methods from LinkActionRequest
    //
    /**
     * @see LinkActionRequest::getJSLinkActionRequest()
     */
    public function getJSLinkActionRequest()
    {
        return '$.pkp.classes.linkAction.ModalRequest';
    }

    /**
     * @see LinkActionRequest::getLocalizedOptions()
     */
    public function getLocalizedOptions()
    {
        return [
            'title' => $this->getTitle(),
            'titleIcon' => $this->getTitleIcon(),
            'canClose' => ($this->getCanClose() ? '1' : '0'),
            'closeOnFormSuccessId' => $this->_closeOnFormSuccessId,
            'closeCleanVueInstances' => $this->_closeCleanVueInstances,
            'closeButtonText' => $this->getCloseButtonText(),
        ];
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\linkAction\request\Modal', '\Modal');
}
