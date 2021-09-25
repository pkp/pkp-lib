<?php

/**
 * @file pages/about/HelpHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class HelpHandler
 * @ingroup pages_help
 *
 * @brief Handle requests for help functions.
 */

use APP\handler\Handler;

use PKP\core\JSONMessage;
use PKP\facades\Locale;
use PKP\i18n\LocaleConversion;

class HelpHandler extends Handler
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        AppLocale::requireComponents(LOCALE_COMPONENT_APP_COMMON);
    }

    /**
     * Display help.
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function index($args, $request)
    {
        $path = 'docs/manual/';
        $urlPart = join('/', $request->getRequestedArgs());
        $filename = $urlPart . '.md';

        $language = LocaleConversion::getIso1FromLocale(Locale::getLocale());
        $summaryFile = $path . $language . '/SUMMARY.md';

        // Default to English
        if (!file_exists($path . $language) || !file_exists($summaryFile) || filesize($summaryFile) == 0) {
            $language = 'en';
        }

        if (!preg_match('#^([[a-zA-Z0-9_-]+/)+[a-zA-Z0-9_-]+\.\w+$#', $filename) || !file_exists($path . $filename)) {
            $request->redirect(null, null, null, [$language, 'SUMMARY']);
        }

        // Use the summary document to find next/previous links.
        // (Yes, we're grepping markdown outside the parser, but this is much faster.)
        $previousLink = $nextLink = null;
        if (preg_match_all('/\(([^)]+)\)/sm', file_get_contents($summaryFile), $matches)) {
            $matches = $matches[1];
            if (($i = array_search(substr($urlPart, strpos($urlPart, '/') + 1), $matches)) !== false) {
                if ($i > 0) {
                    $previousLink = $matches[$i - 1];
                }
                if ($i < count($matches) - 1) {
                    $nextLink = $matches[$i + 1];
                }
            }
        }

        // Use a URL filter to prepend the current path to relative URLs.
        $parser = new \Michelf\Markdown();
        $parser->url_filter_func = function ($url) use ($filename) {
            return (empty(parse_url($url)['host']) ? dirname($filename) . '/' : '') . $url;
        };
        return new JSONMessage(
            true,
            [
                'content' => $parser->transform(file_get_contents($path . $filename)),
                'previous' => $previousLink,
                'next' => $nextLink,
            ]
        );
    }
}
