<?php

/**
 * @file classes/components/form/FieldFunder.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FieldFunder
 *
 * @ingroup classes_controllers_form
 *
 * @brief A field for funder information.
 */

namespace PKP\components\forms;

class FieldFunder extends Field
{
    /** @copydoc Field::$component */
    public $component = 'field-funder';

    /** @copydoc Field::$component */
    public $default = [];

    /**
     * Submission ID associated with the funder
     */
    public int $submissionId = 0;

    /**
     * Primary language
     */
    public string $primaryLocale = '';

    /**
     * Supported locales for forms
     */
    public array $supportedFormLocales = [];

    /**
     * @copydoc Field::getConfig()
     */
    public function getConfig()
    {
        $config = parent::getConfig();

        $config['submissionId'] = $this->submissionId;
        $config['value'] = $this->value ?? $this->default ?? null;

        return $config;
    }
}
