<?php

/**
 * @file classes/controllers/listbuilder/MultilingualListbuilderGridColumn.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class MultilingualListbuilderGridColumn
 * @ingroup controllers_listbuilder
 *
 * @brief Represents a multilingual text column within a listbuilder.
 */

namespace PKP\controllers\listbuilder;

use PKP\facades\Locale;

class MultilingualListbuilderGridColumn extends ListbuilderGridColumn
{
    /**
     * Constructor
     *
     * @param null|mixed $title
     * @param null|mixed $titleTranslated
     * @param null|mixed $template
     * @param null|mixed $cellProvider
     * @param null|mixed $availableLocales
     */
    public function __construct(
        $listbuilder,
        $id = '',
        $title = null,
        $titleTranslated = null,
        $template = null,
        $cellProvider = null,
        $availableLocales = null,
        $flags = []
    ) {

        // Make sure this is a text input
        assert($listbuilder->getSourceType() == ListbuilderHandler::LISTBUILDER_SOURCE_TYPE_TEXT);

        // Provide a default set of available locales if not specified
        if (!$availableLocales) {
            $availableLocales = Locale::getSupportedFormLocales();
        }

        // Set some flags for multilingual support
        $flags['multilingual'] = true; // This is a multilingual column.
        $flags['availableLocales'] = $availableLocales; // Provide available locales

        parent::__construct($listbuilder, $id, $title, $titleTranslated, $template, $cellProvider, $flags);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\controllers\listbuilder\MultilingualListbuilderGridColumn', '\MultilingualListbuilderGridColumn');
}
