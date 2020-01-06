<?php
/**
 * @file classes/core/APIError.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class APIError
 * @ingroup core
 *
 * @brief Extends the Error class in the Slim microframework.
 */

require_once('./lib/pkp/lib/vendor/slim/slim/Slim/Handlers/Error.php');

class APIError extends \Slim\Handlers\Error {

	/**
	 * Render JSON error
	 *
	 * @param Exception $exception
	 *
	 * @return string
	 */
	protected function renderJsonErrorMessage(Exception $exception)
	{
		$error = [
			'error' => 'api.500.serverError',
			'errorMessage' => "The request cannot be completed due to a server error."
		];

		return json_encode($error, JSON_PRETTY_PRINT);
	}	
}
