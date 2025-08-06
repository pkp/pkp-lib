<?php

/**
 * @file classes/components/form/FieldCitationAuthors.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FieldCitationAuthors
 *
 * @ingroup classes_controllers_form
 *
 * @brief A field for authors of a citation.
 */

namespace PKP\components\forms\citation;

use PKP\components\forms\Field;

class FieldCitationAuthors extends Field
{
    /** @copydoc Field::$component */
    public $component = 'field-citation-authors';

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
