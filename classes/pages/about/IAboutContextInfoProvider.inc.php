<?php

/**
 * @file lib/pkp/classes/pages/about/IAboutContextInfoProvider.inc.php
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class IAboutContextInfoProvider
 * @ingroup classes_pages_about
 *
 * @brief Interface to retrieve about context information.
 */

interface IAboutContextInfoProvider {

	/**
	 * Get about information for each handler operation.
	 * @param $context Context
	 * @return array The key should be the operation name,
	 * and the values all data that the operation uses to
	 * render the page.
	 */
	static function getAboutInfo($context);
}

?>
