<?php

class MethodNotImplementedException extends BadMethodCallException {
	function __construct() {
		$message = 'No implementation found for the method';
		parent::__construct($message);
	}
}
