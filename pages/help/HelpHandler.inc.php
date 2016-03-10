<?php

/**
 * @file pages/about/HelpHandler.inc.php
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
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

		$language = AppLocale::getIso1FromLocale(AppLocale::getLocale());
		if (!file_exists($path . $language)) $language = 'en'; // Default

		if (!$filename || !preg_match('#^([[a-zA-Z0-9_-]+/)+[a-zA-Z0-9_-]+\.\w+$#', $filename) || !file_exists($path . $filename)) {
			$request->redirect(null, null, null, array($language, 'SUMMARY.md'));
		}

		$parser = new \Michelf\Markdown;

		// Use the summary document to find next/previous links.
		$previousLink = $nextLink = null;
		$found = false;
		// Use a URL filter to find previous and next links from the summary.
		$parser->url_filter_func = function ($url) use (&$found, &$previousLink, &$nextLink, $filename, $language) {
			if (!$found) {
				if ($language . '/' . $url == $filename) $found = true;
				else $previousLink = $url;
			} elseif (!$nextLink) $nextLink = $url;
			return $url;
		};
		$parser->transform(file_get_contents($path . $language . '/SUMMARY.md'));

		// Use a URL filter to prepend the current path to relative URLs.
		$parser->url_filter_func = function ($url) use ($filename) {
			return dirname($filename) . '/' . $url;
		};

		return new JSONMessage(
			true,
			array(
				'content' => $parser->transform(file_get_contents($path . $filename)),
				'previous' => $previousLink,
				'next' => $nextLink,
			)
		);
	}
}

?>
