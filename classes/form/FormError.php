<?php

/**
 * @file classes/form/FormError.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FormError
 *
 * @ingroup form
 *
 * @brief Class to represent a form validation error.
 */

namespace PKP\form;

class FormError
{
    /** @var string The name of the field */
    public $field;

    /** @var string The error message */
    public $message;

    /**
     * Constructor.
     *
     * @param string $field the name of the field
     * @param string $message the error message (i18n key)
     */
    public function __construct($field, $message)
    {
        $this->field = $field;
        $this->message = $message;
    }

    /**
     * Get the field associated with the error.
     *
     * @return string
     */
    public function getField()
    {
        return $this->field;
    }

    /**
     * Get the error message (i18n key).
     *
     * @return string
     */
    public function getMessage()
    {
        return $this->message;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\form\FormError', '\FormError');
}
