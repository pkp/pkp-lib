<?php

/**
 * @file classes/components/form/FieldFunderGrants.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FieldFunderGrants
 *
 * @ingroup classes_controllers_form
 *
 * @brief A field for funder grants.
 */

namespace PKP\components\forms;

class FieldFunderGrants extends Field
{
    /** @copydoc Field::$component */
    public $component = 'field-funder-grants';

    /** @copydoc Field::$component */
    public $default = [];

    /**
     * Primary language
     */
    public string $primaryLocale = '';

    /**
     * Supported locales for forms
     */
    public array $supportedFormLocales = [];

    /**
     * This value overrides the common.add button text if given.
     */
    private string $addButtonLabel = '';

    /**
     * @copydoc Field::getConfig()
     */
    public function getConfig()
    {
        $config = parent::getConfig();
        $config['value'] = $this->value ?? $this->default ?? null;
        $config['addButtonLabel'] = $this->addButtonLabel;
        return $config;
    }
}
