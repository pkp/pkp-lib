<?php
/**
 * @file classes/components/fileAttachers/Base.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Base
 *
 * @ingroup classes_controllers_form
 *
 * @brief A base class for FileAttacher components.
 */

namespace PKP\components\fileAttachers;

abstract class BaseAttacher
{
    public string $component;
    public string $label;
    public string $description;
    public string $button;

    /**
     * Initialize the file attacher
     *
     * @param string $label The label to display for this file attacher
     * @param string $description A description of this file attacher
     * @param string $button The label for the button to activate this file attacher
     */
    public function __construct(string $label, string $description, string $button)
    {
        $this->label = $label;
        $this->description = $description;
        $this->button = $button;
    }

    /**
     * Compile the initial state for this file attacher
     */
    public function getState(): array
    {
        return [
            'component' => $this->component,
            'label' => $this->label,
            'description' => $this->description,
            'button' => $this->button,
        ];
    }
}
