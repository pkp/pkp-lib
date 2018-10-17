<?php 

/**
 * @file classes/services/exceptions/ServiceException.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ServiceException
 * @ingroup services_exceptions
 *
 * @brief Abstract base exception class for services
 */

namespace PKP\Services\Exceptions;

use \Exception;

abstract class ServiceException extends Exception {
	
	protected $contextId = null;
	
	/**
	 * Constructor
	 * 
	 * @param string $message
	 * @param int $code
	 */
	public function __construct ($contextId, $message, $code = null) {
		$this->contextId = $contextId;
		parent::__construct($message,$code);
	}
	
	/**
	 * Return context ID
	 * 
	 * @return int
	 */
	protected function getContextId() {
		return $this->contextId;
	}
}