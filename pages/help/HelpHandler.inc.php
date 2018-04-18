<?php

/**
 * @file pages/about/HelpHandler.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
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
		$filename = join('/', $request->getRequestedArgs());

		// Determine if a plugin help chapter was requested
		$isPluginHelpRequest = false;
		if (count($request->getRequestedArgs()) > 1 && $request->getRequestedArgs()[1] == "plugins") {
			$isPluginHelpRequest = true;
		}

		// If a hash (anchor) was specified, discard it -- we don't need it here.
		if ($hashIndex = strpos($filename, '#')) {
			$hash = substr($filename, $hashIndex+1);
			$filename = substr($filename, 0, $hashIndex);
		} else {
			$hash = null;
		}

		$language = AppLocale::getIso1FromLocale(AppLocale::getLocale());
		if (!file_exists($path . $language)) $language = 'en'; // Default

		if (!$isPluginHelpRequest || !file_exists($this->getPathForPluginHelpChapterFromRequest($request))) {
			if (!$filename || !preg_match('#^([[a-zA-Z0-9_-]+/)+[a-zA-Z0-9_-]+\.\w+$#', $filename) || !file_exists($path . $filename)) {
				$request->redirect(null, null, null, array($language, 'SUMMARY.md'));
			}
		}

		// Give plugins the opportunity to provide help chapters for the toc
		$pluginHelpChapters = array();
		HookRegistry::call('Help::Plugins', array(&$pluginHelpChapters));

		// Use the summary document to find next/previous links.
		// (Yes, we're grepping markdown outside the parser, but this is much faster.)
		$previousLink = $nextLink = null;
		if (preg_match_all('/\(([^)]+\.md)\)/sm', file_get_contents($path . $language . '/SUMMARY.md'), $matches)) {
			$matches = $matches[1];

			// Add the plugin help chapters to the chapters array
			foreach ($pluginHelpChapters as $chapter) {
				array_push($matches, $this->getPathFromHelpChapterArray($chapter, $language));
			}

			if (($i = array_search(substr($filename, strpos($filename, '/')+1), $matches)) !== false) {
				if ($i>0) $previousLink = $matches[$i-1];
				if ($i<count($matches)-1) $nextLink = $matches[$i+1];
			}
		}

		// Use a URL filter to prepend the current path to relative URLs.
		$parser = new \Michelf\Markdown;
		$parser->url_filter_func = function ($url) use ($filename) {
			return dirname($filename) . '/' . $url;
		};

		if (!$isPluginHelpRequest) {
			$content = file_get_contents($path . $filename);

			// If the toc is requested, add the additional help chapters
			if (preg_match('#../SUMMARY\.md#', $filename)) {
				$content = rtrim($content);
				$content .= "\n## Plugins\n";

				foreach ($pluginHelpChapters as $chapter) {
					$content .= sprintf("   * [%s](%s)", $chapter['label'], $this->getPathFromHelpChapterArray($chapter, $language));
				}
			}
		} else {
			$content = file_get_contents($this->getPathForPluginHelpChapterFromRequest($request));
		}

		return new JSONMessage(
			true,
			array(
				'content' => $parser->transform($content),
				'previous' => $previousLink,
				'next' => $nextLink,
			)
		);
	}

	/**
	 * Return the path of the plugin help chapter
	 * @param $request PKPRequest
	 * @return string Path of the help chapter
	 */
	function getPathForPluginHelpChapterFromRequest($request) {
		return join(DIRECTORY_SEPARATOR, array_splice($request->getRequestedArgs(), 1));
	}

	/**
	 * Return the path of the plugin help chapter
	 * @param $chapter array
	 * return string Path of the help chapter
	 */
	function getPathFromHelpChapterArray($chapter, $language) {
		return join(DIRECTORY_SEPARATOR, array(
			$chapter['path'],
			$language,
			$chapter['file']
		));
	}
}

?>
