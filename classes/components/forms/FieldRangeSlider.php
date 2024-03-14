<?php
/**
 * @file classes/components/form/FieldRangeSlider.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FieldRangeSlider
 *
 * @brief A basic range slider field implementation.
 */

namespace PKP\components\forms;

use Exception;

class FieldRangeSlider extends Field
{
    public const RANGE_SLIDER_SIZES = ['small', 'normal', 'large'];
    public const RANGE_SLIDER_VALUE_POSITION_IN_UPDATE_LABEL = ['before', 'after'];

    /** 
     * @copydoc Field::$component
     */
    public $component = 'field-range-slider';

    /** 
     * Should the range slider be disable
     * 
     * @var bool
     */
    public $disable = false;

    /** 
     * Should the range slider be disable if input field value set to NULL on render
     * 
     * @var bool
     */
    public $disableOnNull = true;

    /** 
     * Range min value
     * 
     * @var int|float
     */
    public $max;

    /** 
     * Range max value
     * 
     * @var int|float
     */
    public $min;

    /** 
     * Range step value
     * 
     * @var int|float
     */
    public $step = 1;

    /** 
     * Range value on reset
     * 
     * @var int|float|null
     */
    public $onResetValue;

    /**
     * Range input size
     * 
     * @var string Accepts: `small`, `normal` or `large`
     */
    public $size = 'normal';

    /**
     * The options which can be selected
     * 
     * @var array 
     */
    public $options = [];

    /**
     * Range real time update label
     * 
     * @var string
     */
    public $updateLabel = '';

    /**
     * Position of updated value in slider range real time update label
     * 
     * @var string Accepts: `before`, or `after`
     */
    public $valuePositionInUpdateLabel = 'before';

    /**
     * Allow the slider range disbale/enable ability
     * 
     * @var bool
     */
    public $allowStateToggle = true;

    /** 
     * Show the label text when range slider is disable
     * 
     * @var string
     */
    public $controlLabelOnDisable;

    /** 
     * Show the label text when range slider is disable
     * 
     * @var string
     */
    public $controlLabelOnEnable;

    /**
     * @copydoc Field::getConfig()
     */
    public function getConfig()
    {
        if (!in_array($this->size, static::RANGE_SLIDER_SIZES)) {
            throw new Exception(
                sprintf(
                    'Invalid size %s give, must be among [%s]',
                    $this->size,
                    implode(static::RANGE_SLIDER_SIZES)
                )
            );
        }

        if (!in_array($this->valuePositionInUpdateLabel, static::RANGE_SLIDER_VALUE_POSITION_IN_UPDATE_LABEL)) {
            throw new Exception(
                sprintf(
                    'Invalid value position %s give, must be among [%s]',
                    $this->valuePositionInUpdateLabel,
                    implode(static::RANGE_SLIDER_VALUE_POSITION_IN_UPDATE_LABEL)
                )
            );
        }

        $config = parent::getConfig();

        if (!isset($this->controlLabelOnDisable)) {
            $this->controlLabelOnDisable = __('common.enable');
        }

        if (!isset($this->controlLabelOnEnable)) {
            $this->controlLabelOnEnable = __('common.disable');
        }

        if (!isset($this->onResetValue)) {
            $this->onResetValue = $this->value ?? $this->default ?? null;
        }

        return array_merge($config, [
            'disable'               => $this->disable,
            'disableOnNull'         => $this->disableOnNull,
            'options'               => $this->options,
            'max'                   => $this->max,
            'min'                   => $this->min,
            'size'                  => $this->size,
            'step'                  => $this->step,
            'onResetValue'          => $this->onResetValue,
            'updateLabel'           => $this->updateLabel,
            'allowStateToggle'      => $this->allowStateToggle,
            'controlLabelOnDisable' => $this->controlLabelOnDisable,
            'controlLabelOnEnable'  => $this->controlLabelOnEnable,
        ]);
    }
}
