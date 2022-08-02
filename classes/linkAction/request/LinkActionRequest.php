<?php
/**
 * @defgroup linkAction_request Link Action Request
 */

/**
 * @file classes/linkAction/request/LinkActionRequest.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class LinkActionRequest
 * @ingroup linkAction_request
 *
 * @brief Abstract base class defining an action to be taken when a link action is activated.
 */

namespace PKP\linkAction\request;

class LinkActionRequest
{
    /**
     * Constructor
     */
    public function __construct()
    {
    }


    //
    // Public methods
    //
    /**
     * Return the JavaScript controller that will
     * handle this request.
     *
     * @return string
     */
    public function getJSLinkActionRequest()
    {
        assert(false);
    }

    /**
     * Return the options to be passed on to the
     * JS action request handler.
     *
     * @return array An array describing the dialog
     *  options.
     */
    public function getLocalizedOptions()
    {
        return [];
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\linkAction\request\LinkActionRequest', '\LinkActionRequest');
}
