<?php

/**
 * @file classes/webservice/WebServiceRequest.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class WebServiceRequest
 * @ingroup webservice
 *
 * @brief Represents a web service request.
 */


class WebServiceRequest
{
    /** @var string */
    public $_url;

    /** @var mixed array (key value pairs) or string */
    public $_params;

    /** @var string HTTP request method */
    public $_method;

    /** @var string Accept header */
    public $_accept;

    /** @var array Additional request headers */
    public $_headers = [];

    /** @var boolean Whether to make an asynchronous request */
    public $_async = false;

    /** @var boolean Whether to consider the proxy settings in the config.inc.php */
    public $_useProxySettings = true;

    /**
     * Constructor
     *
     * @param $url string The request URL
     * @param $params mixed array (key value pairs) or string request parameters
     * @param $method string GET or POST
     * @param $useProxy boolean Whether the proxy settings from config.inc.php should be considered
     */
    public function __construct($url, $params, $method = 'GET', $useProxy = true)
    {
        $this->_url = $url;
        $this->_params = $params;
        $this->_method = $method;
        $this->_accept = 'text/xml, */*';
        $this->_useProxySettings = $useProxy;
    }

    //
    // Getters and Setters
    //
    /**
     * Get the web service URL
     *
     * @return string
     */
    public function getUrl()
    {
        return $this->_url;
    }

    /**
     * Set the web service URL
     *
     * @param $url string
     */
    public function setUrl($url)
    {
        $this->_url = $url;
    }

    /**
     * Get the request parameters
     *
     * @return mixed array (key value pairs) or string
     */
    public function &getParams()
    {
        return $this->_params;
    }

    /**
     * Set the request parameters
     *
     * @param $params mixed array (key value pairs) or string
     */
    public function setParams(&$params)
    {
        $this->_params = & $params;
    }

    /**
     * Get the request method
     *
     * @return string
     */
    public function getMethod()
    {
        return $this->_method;
    }

    /**
     * Set the request method
     *
     * @param $method string
     */
    public function setMethod($method)
    {
        $this->_method = $method;
    }

    /**
     * Set the accept header value
     *
     * @param $accept string
     */
    public function setAccept($accept)
    {
        $this->_accept = $accept;
    }

    /**
     * Get the accept header value
     *
     * @return string
     */
    public function getAccept()
    {
        return $this->_accept;
    }

    /**
     * Set an additional request header.
     *
     * @param $header string
     * @param $content string
     */
    public function setHeader($header, $content)
    {
        $this->_headers[$header] = $content;
    }

    /**
     * Check whether the given header is
     * present in the request.
     *
     * The check is case insensitive.
     *
     * @param $header string
     */
    public function hasHeader($header)
    {
        $header = strtolower($header);
        foreach ($this->_headers as $h => $dummy) {
            if ($header == strtolower($h)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get additional request headers.
     */
    public function getHeaders()
    {
        return $this->_headers;
    }

    /**
     * Set whether to make an async request.
     * (POST requests only)
     *
     * @param $async boolean
     */
    public function setAsync($async)
    {
        $this->_async = (bool)$async;
    }

    /**
     * Whether to make an async request.
     *
     * @return boolean
     */
    public function getAsync()
    {
        return $this->_async;
    }

    /**
     * Set whether to consider the proxy settings in config.inc.php.
     *
     * @param $useProxySettings boolean
     */
    public function setUseProxySettings($useProxySettings)
    {
        $this->_useProxySettings = $useProxySettings;
    }

    /**
     * Get whether to consider the proxy settings in config.inc.php.
     *
     * @return boolean
     */
    public function getUseProxySettings()
    {
        return $this->_useProxySettings;
    }
}
