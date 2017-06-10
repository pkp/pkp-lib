<?php 

/**
 * @file classes/repositories/exceptions/ValidationException.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2000-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ValidationException
 * @ingroup repositories_exceptions
 *
 * @brief Repository validation exception
 */

namespace App\Repositories\Exceptions;

use \Exception;

class ValidationException extends Exception {
	
	/**
	 * Constructor
	 *
	 * @param string $message
	 * @param int $code
	 */
	public function __construct ($message, $code = null) {
		parent::__construct($message,$code);
	}
}

