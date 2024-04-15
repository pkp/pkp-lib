<?php
/**
 * @file classes/components/form/FieldSlider.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FieldSlider
 *
 * @ingroup classes_controllers_form
 *
 * @brief A color picker field in a form.
 */

namespace PKP\components\forms;

class FieldSlider extends Field
{
    /** @copydoc Field::$component */
    public $component = 'field-slider';

    /**
     * Range min value
     */
    public int|float $min;

    /**
     * Range max value
     */
    public int|float $max;

    /**
     * Range step value
     */
    public int|float $step = 1;

    /**
     * Label for min value, it displays actual value when not present
     */
    public ?string $minLabel = null;

    /**
     * Label for max value, it displays actual value when not present
     */
    public ?string $maxLabel = null;

    /**
     * Expects translation string, which should contain {$value} placeholder. It displays actual value if not present.
     */
    public ?string $valueLabel = null;

    /**
     * Expects translation string, which might contain {$value} placeholder. It fallback to valueLabel is not present..
     */
    public ?string $valueLabelMin = null;

    /**
     * Expects translation string, which might contain {$value} placeholder. It fallback to valueLabel is not present..
     */
    public ?string $valueLabelMax = null;

    /**
     * @copydoc Field::getConfig()
     */
    public function getConfig()
    {
        $config = parent::getConfig();
        $config['min'] = $this->min;
        $config['max'] = $this->max;
        $config['step'] = $this->step;
        $config['minLabel'] = $this->minLabel;
        $config['maxLabel'] = $this->maxLabel;
        $config['valueLabel'] = $this->valueLabel;
        $config['valueLabelMin'] = $this->valueLabelMin;
        $config['valueLabelMax'] = $this->valueLabelMax;

        return $config;
    }

}
