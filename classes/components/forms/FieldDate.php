<?php
/**
 * @file classes/components/form/FieldDate.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FieldDate
 *
 * @ingroup classes_controllers_form
 *
 * @brief A basic date field in a form.
 */

namespace PKP\components\forms;

class FieldDate extends Field
{
    /** @copydoc Field::$component */
    public $component = 'field-date';

    /** @var string Minimum date, can be 'today', 'tomorrow', 'yesterday', or YYYY-MM-DD */
    public $min = '';

    /** @var string Maximum date, can be 'today', 'tomorrow', 'yesterday', or YYYY-MM-DD */
    public $max = '';

    /** @var boolean disabled */
    public $disabled = false;

    /**
     * @copydoc Field::getConfig()
     */
    public function getConfig()
    {
        $config = parent::getConfig();
        $config['min'] = $this->min;
        $config['max'] = $this->max;
        $config['disabled'] = $this->disabled;

        return $config;
    }
}
