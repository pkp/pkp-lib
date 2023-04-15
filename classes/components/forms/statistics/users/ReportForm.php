<?php
/**
 * @file classes/components/form/statistics/users/ReportForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReportForm
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for configuring the users report.
 */

namespace PKP\components\forms\statistics\users;

use PKP\components\forms\FieldOptions;
use PKP\components\forms\FormComponent;
use APP\facades\Repo;
use PKP\context\Context;
use PKP\userGroup\UserGroup;

class ReportForm extends FormComponent
{
    /**
     * Constructor
     *
     * @param string $action URL to submit the form to
     * @param \Context $context The context
     */
    public function __construct(string $action, Context $context)
    {
        $this->action = $action;
        $this->id = 'reportForm';
        $this->method = 'POST';

        $this->addPage(['id' => 'default', 'submitButton' => ['label' => __('common.export')]]);
        $this->addGroup(['id' => 'default', 'pageId' => 'default']);

        $userGroups = Repo::userGroup()->getCollector()
            ->filterByContextIds([$context->getId()])
            ->getMany();

        $this->addField(new FieldOptions('userGroupIds', [
            'groupId' => 'default',
            'label' => __('user.group'),
            'description' => __('manager.export.usersToCsv.description'),
            'options' => $userGroups->values()->map(function (UserGroup $userGroup) {
                return [
                    'value' => $userGroup->getId(),
                    'label' => $userGroup->getLocalizedName()
                ];
            }),
            'default' => $userGroups->keys(),
        ]));
    }
}
