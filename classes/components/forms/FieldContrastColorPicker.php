<?php
/**
 * @file classes/components/form/FieldContrastColorPicker.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2000-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FieldContrastColorPicker
 *
 * @ingroup classes_controllers_form
 *
 * @brief A color picker field with automatic contrast color suggestions.
 */

namespace PKP\components\forms;

class FieldContrastColorPicker extends Field
{
    /** @copydoc Field::$component */
    public $component = 'field-contrast-color-picker';

    /** @var string The selected contrast color */
    public $contrastColor = '';

    /**
     * @copydoc Field::getConfig()
     */
    public function getConfig()
    {
        $config = parent::getConfig();
        $config['contrastColor'] = $this->contrastColor;

        return $config;
    }

    /**
     * Set the contrast color
     *
     * @param string $contrastColor
     * @return FieldContrastColorPicker
     */
    public function setContrastColor($contrastColor)
    {
        $this->contrastColor = $contrastColor;
        return $this;
    }
}
