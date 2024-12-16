<?php
/**
 * @file classes/components/form/context/PKPAppearanceMastheadForm.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPAppearanceMastheadForm
 *
 * @ingroup classes_controllers_form
 *
 * @brief A form for defining in which order the editorial masthead roles should be displayed.
 */

namespace PKP\components\forms\context;

use PKP\components\forms\FieldHTML;
use PKP\components\forms\FieldOptions;
use PKP\components\forms\FormComponent;
use PKP\security\Role;
use PKP\userGroup\UserGroup;


class PKPAppearanceMastheadForm extends FormComponent
{
    public const FORM_APPEARANCE_MASTHEAD = 'appearanceMasthead';
    public $id = self::FORM_APPEARANCE_MASTHEAD;
    public $method = 'PUT';

    /**
     * Constructor
     *
     * @param string $action URL to submit the form to
     * @param array $locales Supported locales
     * @param \PKP\context\Context $context Journal or Press to change settings for
     */
    public function __construct($action, $locales, $context)
    {
        $this->action = $action;
        $this->locales = $locales;
        $mastheadOptions = [];

        $savedMastheadUserGroupIdsOrder = (array) $context->getData('mastheadUserGroupIds');

        $allMastheadUserGroups = UserGroup::withContextIds($context->getId())
            ->masthead(true)
            ->excludeRoleIds(Role::ROLE_ID_REVIEWER)
            ->orderByRoleId()
            ->get();
    
        // Sort the masthead user groups in their saved order
        $sortedAllMastheadUserGroups = $allMastheadUserGroups->sortBy(function ($userGroup) use ($savedMastheadUserGroupIdsOrder) {
            return array_search($userGroup->id, $savedMastheadUserGroupIdsOrder);
        })->values();
        
        $mastheadOptions = [];
        foreach ($sortedAllMastheadUserGroups as $userGroup) {
            $mastheadOptions[] = [
                'value' => $userGroup->id,
                'label' => $userGroup->getLocalizedData('name')
            ];
        }

        $this->addField(new FieldOptions('mastheadUserGroupIds', [
            'label' => __('common.editorialMasthead'),
            'description' => __('manager.setup.editorialMasthead.order.description'),
            'isOrderable' => true,
            'value' => array_column($mastheadOptions, 'value'),
            'options' => $mastheadOptions,
            'allowOnlySorting' => true
        ]))
        ->addField(new FieldHTML('reviewer', [
            'label' => __('user.role.reviewers'),
            'description' => __('manager.setup.editorialMasthead.order.reviewers.description')
        ]));
    }
}
