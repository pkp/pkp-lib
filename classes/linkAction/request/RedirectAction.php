<?php
/**
 * @file classes/linkAction/request/RedirectAction.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RedirectAction
 * @ingroup linkAction_request
 *
 * @brief This action request redirects to another page.
 */

namespace PKP\linkAction\request;

class RedirectAction extends LinkActionRequest
{
    /** @var string The URL this action will invoke */
    public $_url;

    /** @var string The name of the window */
    public $_name;

    /** @var string The specifications of the window */
    public $_specs;

    /**
     * Constructor
     *
     * @param string $url Target URL
     * @param string $name Name of window to direct (defaults to current window)
     * @param string $specs Optional set of window specs (see window.open JS reference)
     */
    public function __construct($url, $name = '_self', $specs = '')
    {
        parent::__construct();
        $this->_url = $url;
        $this->_name = $name;
        $this->_specs = $specs;
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

    /**
     * Get the target name.
     * See JS reference for the name parameter to "window.open".
     *
     * @return string
     */
    public function getName()
    {
        return $this->_name;
    }

    /**
     * Get the target specifications.
     * See JS reference for the specs parameter to "window.open".
     *
     * @return string
     */
    public function getSpecs()
    {
        return $this->_specs;
    }


    //
    // Overridden protected methods from LinkActionRequest
    //
    /**
     * @see LinkActionRequest::getJSLinkActionRequest()
     */
    public function getJSLinkActionRequest()
    {
        return '$.pkp.classes.linkAction.RedirectRequest';
    }

    /**
     * @see LinkActionRequest::getLocalizedOptions()
     */
    public function getLocalizedOptions()
    {
        return [
            'url' => $this->getUrl(),
            'name' => $this->getName(),
            'specs' => $this->getSpecs()
        ];
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\linkAction\request\RedirectAction', '\RedirectAction');
}
