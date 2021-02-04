<?php

/**
 * @file classes/install/DowngradeNotSupportedException.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Exception indicating an unsupported downgrade.
 */

namespace PKP\install;

class DowngradeNotSupportedException extends \Exception {
	function __construct() {
		parent::__construct('Downgrade not supported!');
	}
}

