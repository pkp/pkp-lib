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

use APP\facades\Repo;
use PKP\components\forms\FieldOptions;
use PKP\components\forms\FormComponent;

define('FORM_APPEARANCE_MASTHEAD', 'appearanceMasthead');

class PKPAppearanceMastheadForm extends FormComponent
{
    /** @copydoc FormComponent::$id */
    public $id = FORM_APPEARANCE_SETUP;

    /** @copydoc FormComponent::$method */
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

        $collector = Repo::userGroup()->getCollector();
        $allMastheadUserGroups = $collector
            ->filterByContextIds([$context->getId()])
            ->filterByMasthead(true)
            ->orderBy($collector::ORDERBY_ROLE_ID)
            ->getMany()
            ->toArray();

        // sort the mashead roles in their saved order
        $sortedAllMastheadUserGroups = array_replace(array_flip($savedMastheadUserGroupIdsOrder), $allMastheadUserGroups);

        foreach ($sortedAllMastheadUserGroups as $userGroup) {
            $mastheadOptions[] = [
                'value' => $userGroup->getId(),
                'label' => $userGroup->getLocalizedName()
            ];
        }

        $this->addField(new FieldOptions('mastheadUserGroupIds', [
            'label' => __('common.editorialMasthead'),
            'description' => __('manager.setup.editorialMasthead.description'),
            'isOrderable' => true,
            'value' => array_column($mastheadOptions, 'value'),
            'options' => $mastheadOptions,
            'allowOnlySorting' => true
        ]));
    }
}
