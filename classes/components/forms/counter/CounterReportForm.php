<?php
/**
 * @file classes/components/form/counter/CounterReportForm.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CounterReportForm
 *
 * @ingroup classes_controllers_form
 *
 * @brief A form for setting a counter report
 */

namespace APP\components\forms\counter;

use APP\sushi\IR;
use APP\sushi\PR;
use APP\sushi\PR_P1;
use PKP\components\forms\counter\PKPCounterReportForm;

class CounterReportForm extends PKPCounterReportForm
{
    public function setReportFields(): void
    {
        $formFieldsPR = PR::getReportSettingsFormFields();
        $this->reportFields['PR'] = array_map(function ($field) {
            $field->groupId = 'default';
            return $field;
        }, $formFieldsPR);

        $formFieldsPR_P1 = PR_P1::getReportSettingsFormFields();
        $this->reportFields['PR_P1'] = array_map(function ($field) {
            $field->groupId = 'default';
            return $field;
        }, $formFieldsPR_P1);

        $formFieldsIR = IR::getReportSettingsFormFields();
        $this->reportFields['IR'] = array_map(function ($field) {
            $field->groupId = 'default';
            return $field;
        }, $formFieldsIR);
    }
}
