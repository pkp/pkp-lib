<?php

/**
 * @file pages/about/HelpHandler.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
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
	function __construct() {
		parent::__construct();
		AppLocale::requireComponents(LOCALE_COMPONENT_APP_COMMON);
	}

	/**
	 * Display help.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function index($args, $request) {
		$path = 'docs/manual/';
		$urlPart = join('/', $request->getRequestedArgs());
		$filename = $urlPart . '.md';

		$language = AppLocale::getIso1FromLocale(AppLocale::getLocale());
		$summaryFile = $path . $language . '/SUMMARY.md';

		// Default to English
		if (!file_exists($path . $language) || !file_exists($summaryFile) || filesize($summaryFile)==0) $language = 'en';

		if (!preg_match('#^([[a-zA-Z0-9_-]+/)+[a-zA-Z0-9_-]+\.\w+$#', $filename) || !file_exists($path . $filename)) {
			$request->redirect(null, null, null, array($language, 'SUMMARY'));
		}

		// Use the summary document to find next/previous links.
		// (Yes, we're grepping markdown outside the parser, but this is much faster.)
		$previousLink = $nextLink = null;
		if (preg_match_all('/\(([^)]+)\)/sm', file_get_contents($summaryFile), $matches)) {
			$matches = $matches[1];
			if (($i = array_search(substr($urlPart, strpos($urlPart, '/')+1), $matches)) !== false) {
				if ($i>0) $previousLink = $matches[$i-1];
				if ($i<count($matches)-1) $nextLink = $matches[$i+1];
			}
		}

		// Use a URL filter to prepend the current path to relative URLs.
		$parser = new \Michelf\Markdown;
		$parser->url_filter_func = function ($url) use ($filename) {
			return (empty(parse_url($url)['host']) ? dirname($filename) . '/' : '') . $url;
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
