<?php
/**
 * @file classes/components/form/context/PKPSubmissionsListSettingsForm.php
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2000-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPSubmissionsListSettingsForm
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for presenting submission as a table on the backend
 */

namespace PKP\components\forms\context;

use PKP\submission\maps\Schema;
use PKP\components\forms\FormComponent;
use PKP\components\forms\FieldOptions;

define('FORM_SUBMISSIONS_LIST_SETTINGS', 'submissionsListSettings');

class PKPSubmissionsListSettingsForm extends FormComponent
{
    /** @copydoc FormComponent::$id */
    public $id = FORM_SUBMISSIONS_LIST_SETTINGS;

    /** @copydoc FormComponent::$method */
    public $method = 'PUT';

    /**
     * Constructor
     *
     * @param string $action URL to submit the form to
     * @param array $locales Supported locales
     * @param \Context $context Journal or Press to change settings for
     */
    public function __construct($action, $locales, $context)
    {
        $this->action = $action;
        $this->locales = $locales;

        $localizedOptions = []; // template for localized options to be used for date and time format
        foreach ($this->locales as $key => $localeValue) {
            $localizedOptions[$localeValue['key']] = $key;
        }
        
        $availableColumns = Schema::getPropertyColumnsName();
        
        $enableColumns = [];
        $disableColumns = [];
        $currentColumns = (array) $context->getData('submissionsListSettings');
        if (sizeof($currentColumns) > 0) {
            // sort available columns by choosen order
            foreach ($currentColumns as $value) {
                $enableColumns[] = $availableColumns[$value];
                unset($availableColumns[$value]);
            }
            $disableColumns = array_values($availableColumns);
        }
        else {
            //never configured: enable all columns by default
            $disableColumns = array_values($availableColumns);
            $currentColumns = array_keys($availableColumns);
        }
        $columnsOptions = array_merge($enableColumns, $disableColumns);
        
        $this->addGroup([
            'id' => 'descriptions',
            'label' => __('manager.setup.submissionsListSettings.descriptionTitle'),
            'description' => __('manager.setup.submissionsListSettings.description'),
        ])->addField(new FieldOptions('openSubmissionsInANewTab', [
            'label' => __('manager.setup.openSubmissionsInANewTab'),
            'groupId' => 'descriptions',
            'description' => __('manager.setup.openSubmissionsInANewTab.description'),
            'options' => [
                ['value' => true, 'label' => __('manager.setup.openSubmissionsInANewTab.enable')]
            ],
            'value' => (bool) $context->getData('openSubmissionsInANewTab'),
        ]))->addField(new FieldOptions('enableCustomSubmissionsList', [
            'label' => __('manager.setup.enableCustomSubmissionsList'),
            'groupId' => 'descriptions',
            'description' => __('manager.setup.enableCustomSubmissionsList.description'),
            'options' => [
                ['value' => true, 'label' => __('manager.setup.enableCustomSubmissionsList.enable')]
            ],
            'value' => (bool) $context->getData('enableCustomSubmissionsList'),
        ]))->addField(
                new FieldOptions('submissionsListSettings', 
                        [
                            'label' => __('manager.setup.submissionsListSetup'),
                            'isMultilingual' => false,
                            'groupId' => 'descriptions',
                            'options' => $columnsOptions,
                            'isOrderable' => true,
                            'value' => $currentColumns,
                       ])
            );
    }
    
    
}
