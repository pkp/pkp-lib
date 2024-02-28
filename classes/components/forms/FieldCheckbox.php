<?php
/**
 * @file classes/components/form/FieldCheckbox.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FieldCheckbox
 *
 * @ingroup 
 *
 * @brief A field to select from a checkbox viewd as checkbox or button
 */

namespace PKP\components\forms;

class FieldCheckbox extends Field
{
    /** 
     * @copydoc Field::$component
     */
    public $component = 'field-checkbox';

    /** 
     * Should show the checkbox as a button
     */
    public $viewAsButton = false;

    /** 
     * Should the checkbox be disable
     */
    public $disable = false;

    /** 
     * Show the label text when checkbox is checked
     */
    public $checkedLabel = '';

    /** 
     * Show the label text when checkbox is unchecked
     */
    public $uncheckedLabel = '';

    /**
     * The options which can be selected
     * 
     * @var array 
     */
    public $options = [];

    /**
     * @copydoc Field::getConfig()
     */
    public function getConfig()
    {
        $config = parent::getConfig();
        $config = array_merge($config, [
            'viewAsButton'      => $this->viewAsButton,
            'disable'           => $this->disable,
            'checkedLabel'      => $this->checkedLabel,
            'uncheckedLabel'    => $this->uncheckedLabel,
            'options'           => $this->options,
        ]);
        return $config;
    }
}
