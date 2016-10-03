<?php

/**
 * @file classes/handler/IAPIHandler.inc.php
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2000-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class IAPIHandler
 * @ingroup handler
 *
 * @brief Map HTTP requests to a REST API using the Slim microframework.
 *
 * Requests for [index.php]/index/api are intercepted for site-level API
 * requests, and requests for [index.php]/{contextPath}/api are intercepted for
 * context-level API requests.
 */

interface IAPIHandler {
	/**
	 * Initialization
	 * @return App Slim application object
	 */
	public static function init();
}
