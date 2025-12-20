<?php
/**
 * @file classes/components/form/counter/PKPCounterReportForm.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPCounterReportForm
 *
 * @ingroup classes_controllers_form
 *
 * @brief A form for setting a COUNTER R5 report
 */

namespace PKP\components\forms\counter;

use PKP\components\forms\FormComponent;

define('FORM_COUNTER', 'counter');

abstract class PKPCounterReportForm extends FormComponent
{
    /** @copydoc FormComponent::$id */
    public $id = FORM_COUNTER;

    /** @copydoc FormComponent::$method */
    public $method = 'GET';

    /** Form fields for each COUNTER R5 report */
    public $reportFields = [];

    /** Set reportFields, that will contain form fields for each COUNTER R5 report */
    abstract public function setReportFields(): void;

    /**
     * Constructor
     *
     * @param string $action URL to submit the form to
     * @param array $locales Supported locales
     */
    public function __construct(string $action, array $locales)
    {
        $this->action = $action;
        $this->locales = $locales;

        $this->addPage(['id' => 'default', 'submitButton' => ['label' => __('common.download')]]);
        $this->addGroup(['id' => 'default', 'pageId' => 'default']);

        $this->setReportFields();
    }

    public function getConfig()
    {
        $config = parent::getConfig();
        $config['reportFields'] = array_map(function ($reportFields) {
            return array_map(function ($reportField) {
                $field = $this->getFieldConfig($reportField);
                $field['groupId'] = 'default';
                return $field;
            }, $reportFields);
        }, $this->reportFields);

        return $config;
    }
}
