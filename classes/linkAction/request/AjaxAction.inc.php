<?php
/**
 * @file classes/linkAction/request/AjaxAction.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AjaxAction
 * @ingroup linkAction_request
 *
 * @brief Class defining an AJAX action.
 */

namespace PKP\linkAction\request;

define('AJAX_REQUEST_TYPE_GET', 'get');
define('AJAX_REQUEST_TYPE_POST', 'post');


class AjaxAction extends LinkActionRequest
{
    /** @var string */
    public $_remoteAction;

    /** @var string */
    public $_requestType;


    /**
     * Constructor
     *
     * @param $remoteAction string The target URL.
     * @param $requestType string One of the AJAX_REQUEST_TYPE_* constants.
     */
    public function __construct($remoteAction, $requestType = AJAX_REQUEST_TYPE_POST)
    {
        parent::__construct();
        $this->_remoteAction = $remoteAction;
        $this->_requestType = $requestType;
    }


    //
    // Getters and Setters
    //
    /**
     * Get the target URL.
     *
     * @return string
     */
    public function getRemoteAction()
    {
        return $this->_remoteAction;
    }

    /**
     * Get the modal object.
     *
     * @return Modal
     */
    public function getRequestType()
    {
        return $this->_requestType;
    }


    //
    // Overridden protected methods from LinkActionRequest
    //
    /**
     * @see LinkActionRequest::getJSLinkActionRequest()
     */
    public function getJSLinkActionRequest()
    {
        return '$.pkp.classes.linkAction.AjaxRequest';
    }

    /**
     * @see LinkActionRequest::getLocalizedOptions()
     */
    public function getLocalizedOptions()
    {
        return [
            'url' => $this->getRemoteAction(),
            'requestType' => $this->getRequestType()
        ];
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\linkAction\request\AjaxAction', '\AjaxAction');
}
