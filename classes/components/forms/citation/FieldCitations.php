<?php

/**
 * @file classes/components/form/FieldCitations.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FieldCitations
 *
 * @ingroup classes_controllers_form
 *
 * @brief A field for structured citations.
 */

namespace PKP\components\forms\citation;

use PKP\components\forms\Field;

class FieldCitations extends Field
{
    /** @copydoc Field::$component */
    public $component = 'field-citations';

    /** @copydoc Field::$component */
    public $default = [];

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
