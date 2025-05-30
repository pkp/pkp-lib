<?php

/**
 * @file classes/components/form/FieldAuthors.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FieldAuthors
 *
 * @ingroup classes_controllers_form
 *
 * @brief A field for authors.
 */

namespace PKP\components\forms;

class FieldAuthors extends Field
{
    /** @copydoc Field::$component */
    public $component = 'field-authors';

    /** @copydoc Field::$component */
    public $default = [];
    /**
     * Primary language
     * Filled in components/ListPanel/ContributorsListPanel.vue
     */
    public string $primaryLocale = '';

    /**
     * Supported locales for forms
     * Filled in components/ListPanel/ContributorsListPanel.vue
     */
    public array $supportedFormLocales = [];

    /**
     * @copydoc Field::getConfig()
     */
    public function getConfig()
    {
        $config = parent::getConfig();
        $config['value'] = $this->value ?? $this->default ?? null;
        return $config;
    }
}
