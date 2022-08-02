<?php
/**
 * @file classes/linkAction/request/AjaxAction.php
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

use APP\core\Application;

class AjaxAction extends LinkActionRequest
{
    public const AJAX_REQUEST_TYPE_GET = 'get';
    public const AJAX_REQUEST_TYPE_POST = 'post';

    /** @var string */
    public $_remoteAction;

    /** @var string */
    public $_requestType;

    /** @var array */
    public $_requestData;

    /**
     * Constructor
     *
     * @param string $remoteAction The target URL.
     * @param string $requestType One of the AJAX_REQUEST_TYPE_* constants.
     * @param array $requestData Any request data (e.g. POST params) to be sent.
     */
    public function __construct($remoteAction, $requestType = self::AJAX_REQUEST_TYPE_POST, $requestData = [])
    {
        parent::__construct();
        $this->_remoteAction = $remoteAction;
        $this->_requestType = $requestType;
        $this->_requestData = array_merge($requestData, [
            'csrfToken' => Application::get()->getRequest()->getSession()->getCSRFToken(),
        ]);
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
     * Get the request type.
     *
     * @return string
     */
    public function getRequestType()
    {
        return $this->_requestType;
    }

    /**
     * Get the request data.
     *
     * @return array
     */
    public function getRequestData()
    {
        return $this->_requestData;
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
            'requestType' => $this->getRequestType(),
            'data' => $this->getRequestData(),
        ];
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\linkAction\request\AjaxAction', '\AjaxAction');
    foreach ([
        'AJAX_REQUEST_TYPE_GET',
        'AJAX_REQUEST_TYPE_POST',
    ] as $constantName) {
        define($constantName, constant('\AjaxAction::' . $constantName));
    }
}
