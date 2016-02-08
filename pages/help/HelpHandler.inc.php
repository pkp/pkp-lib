<?php

/**
 * @file pages/about/HelpHandler.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class HelpHandler
 * @ingroup pages_help
 *
 * @brief Handle requests for help functions.
 */

import('classes.handler.Handler');

class HelpHandler extends Handler {
	/**
	 * Constructor
	 */
	function HelpHandler() {
		parent::Handler();
		AppLocale::requireComponents(LOCALE_COMPONENT_APP_COMMON);
	}

	/**
	 * Display help.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function index($args, $request) {
		require_once('lib/pkp/lib/vendor/michelf/php-markdown/Michelf/Markdown.inc.php');
		$path = 'docs/manual/';
		$filename = join('/', $request->getRequestedArgs());

		// If a hash (anchor) was specified, discard it -- we don't need it here.
		if ($hashIndex = strpos($filename, '#')) {
			$hash = substr($filename, $hashIndex+1);
			$filename = substr($filename, 0, $hashIndex);
		} else {
			$hash = null;
		}

		if (!$filename || !preg_match('#^(\w+/){1,2}\w+\.\w+$#', $filename) || !file_exists($path . $filename)) {
			$language = AppLocale::getIso1FromLocale(AppLocale::getLocale());
			if (!file_exists($path . $language)) $language = 'en'; // Default
			$request->redirect(null, null, null, array($language, 'SUMMARY.md'));
		}
		$parser = new \Michelf\Markdown;

		// Use a URL filter to prepend the current path to relative URLs.
		$parser->url_filter_func = function ($url) use ($filename) {
			return dirname($filename) . '/' . $url;
		};

		$returner = new JSONMessage(true, $parser->transform(file_get_contents($path . $filename)));
		return $returner;
	}
}

?>
