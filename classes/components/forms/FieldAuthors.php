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
     * Label for the add button.
     */
    public string $addButtonLabel = '';

    /**
     * @copydoc Field::getConfig()
     */
    public function getConfig(): array
    {
        $config = parent::getConfig();
        $config['addButtonLabel'] = $this->addButtonLabel;
        return $config;
    }
}
