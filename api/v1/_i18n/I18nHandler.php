<?php

/**
 * @file api/v1/_18n/I18nHandler.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I18nHandler
 *
 * @ingroup api_v1_backend
 *
 * @brief Handle API requests for backend operations.
 *
 */

namespace PKP\API\v1\_i18n;

use PKP\core\APIResponse;
use PKP\facades\Locale;
use PKP\handler\APIHandler;
use Slim\Http\Request as SlimRequest;
use Slim\Http\Response;

class I18nHandler extends APIHandler
{
    /**
     * Constructor
     */

    public function __construct()
    {
        $this->_handlerPath = '_i18n';
        $endpoints = [
            'GET' => [
                [
                    'pattern' => $this->getEndpointPattern() . '/ui.js',
                    'handler' => [$this, 'getTranslations'],
                ]
            ]
        ];

        $this->_endpoints = $endpoints;

        parent::__construct();
    }

    /**
     * Provides javascript file which includes all translations used in Vue.js UI.
     */
    public function getTranslations(SlimRequest $slimRequest, APIResponse $response, array $args): Response
    {

        $translations = Locale::getUiTranslator()->getTranslationStrings();

        $jsContent = 'pkp.localeKeys = ' . json_encode($translations) . ';';

        $response->getBody()->write($jsContent);

        return $response
            ->withHeader('Content-Type', 'application/javascript')
            // cache for one year, hash is provided as query param, which ensures fetching updated version when needed
            ->withHeader('Cache-Control', 'public, max-age=31536000');

    }
}
