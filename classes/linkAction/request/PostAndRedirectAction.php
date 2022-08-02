<?php
/**
 * @file classes/linkAction/request/PostAndRedirectAction.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PostAndRedirectAction
 * @ingroup linkAction_request
 *
 * @brief Class defining a post and redirect action. See PostAndRedirectRequest.js
 * to detailed description.
 */

namespace PKP\linkAction\request;

class PostAndRedirectAction extends RedirectAction
{
    /** @var string The url to be used for posting data */
    public $_postUrl;

    /**
     * Constructor
     *
     * @param string $postUrl The target URL to post data.
     * @param string $redirectUrl The target URL to redirect.
     */
    public function __construct($postUrl, $redirectUrl)
    {
        parent::__construct($redirectUrl);
        $this->_postUrl = $postUrl;
    }


    //
    // Getters and Setters
    //
    /**
     * Get the url to post data.
     *
     * @return string
     */
    public function getPostUrl()
    {
        return $this->_postUrl;
    }


    //
    // Overridden protected methods from LinkActionRequest
    //
    /**
     * @see LinkActionRequest::getJSLinkActionRequest()
     */
    public function getJSLinkActionRequest()
    {
        return '$.pkp.classes.linkAction.PostAndRedirectRequest';
    }

    /**
     * @see LinkActionRequest::getLocalizedOptions()
     */
    public function getLocalizedOptions()
    {
        $options = parent::getLocalizedOptions();
        return array_merge(
            $options,
            ['postUrl' => $this->getPostUrl()]
        );
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\linkAction\request\PostAndRedirectAction', '\PostAndRedirectAction');
}
