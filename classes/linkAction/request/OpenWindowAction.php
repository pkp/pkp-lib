<?php
/**
 * @file classes/linkAction/request/OpenWindowAction.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class OpenWindowAction
 *
 * @ingroup linkAction_request
 *
 * @brief This action request redirects to another page.
 */

namespace PKP\linkAction\request;

class OpenWindowAction extends LinkActionRequest
{
    /** @var string The URL this action will invoke */
    public $_url;

    /**
     * Constructor
     *
     * @param string $url Target URL
     */
    public function __construct($url)
    {
        parent::__construct();
        $this->_url = $url;
    }


    //
    // Getters and Setters
    //
    /**
     * Get the target URL.
     *
     * @return string
     */
    public function getUrl()
    {
        return $this->_url;
    }


    //
    // Overridden protected methods from LinkActionRequest
    //
    /**
     * @see LinkActionRequest::getJSLinkActionRequest()
     */
    public function getJSLinkActionRequest()
    {
        return '$.pkp.classes.linkAction.OpenWindowRequest';
    }

    /**
     * @see LinkActionRequest::getLocalizedOptions()
     */
    public function getLocalizedOptions()
    {
        return ['url' => $this->getUrl()];
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\linkAction\request\OpenWindowAction', '\OpenWindowAction');
}
