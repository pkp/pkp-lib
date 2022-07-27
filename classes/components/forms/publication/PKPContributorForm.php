<?php
/**
 * @file classes/components/form/publication/PKPContributorForm.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPContributorForm
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for adding and editing a contributor for a publication.
 */

namespace PKP\components\forms\publication;

use PKP\components\forms\FieldOptions;
use PKP\components\forms\FieldRichTextarea;
use PKP\components\forms\FieldSelect;
use PKP\components\forms\FieldText;
use PKP\components\forms\FormComponent;
use PKP\security\Role;
use Sokil\IsoCodes\IsoCodesFactory;

define('FORM_CONTRIBUTOR', 'contributor');

class PKPContributorForm extends FormComponent
{
    /** @copydoc FormComponent::$id */
    public $id = FORM_CONTRIBUTOR;

    /** @copydoc FormComponent::$method */
    public $method = 'POST';

    /**
     * Constructor
     *
     * @param string $action URL to submit the form to
     * @param array $locales Supported locales
     * @param \Context $context The publication to change settings for
     */
    public function __construct($action, $locales, $context)
    {
        $this->action = $action;
        $this->locales = $locales;

        $userGroupDao = \DAORegistry::getDAO('UserGroupDAO'); /** @var \UserGroupDAO $userGroupDao */
        $authorUserGroups = $userGroupDao->getByRoleId($context->getId(), Role::ROLE_ID_AUTHOR)->toArray();

        $authorUserGroupsOptions = [];
        $firstAuthorUserGroupId = null;
        foreach ($authorUserGroups as $authorUserGroup) {
            if (!isset($firstAuthorUserGroupId)) {
                $firstAuthorUserGroupId = (int) $authorUserGroup->getId();
            }

            $authorUserGroupsOptions[] = [
                'value' => (int) $authorUserGroup->getId(),
                'label' => $authorUserGroup->getLocalizedName(),
            ];
        }

        $isoCodes = app(IsoCodesFactory::class);
        $countries = [];
        foreach ($isoCodes->getCountries() as $country) {
            $countries[] = [
                'value' => $country->getAlpha2(),
                'label' => $country->getLocalName()
            ];
        }
        usort($countries, function ($a, $b) {
            return strcmp($a['label'], $b['label']);
        });

        $this->addField(new FieldText('givenName', [
            'label' => __('user.givenName'),
            'isMultilingual' => true,
            'isRequired' => true
        ]))
            ->addField(new FieldText('familyName', [
                'label' => __('user.familyName'),
                'isMultilingual' => true,
            ]))
            ->addField(new FieldText('preferredPublicName', [
                'label' => __('user.preferredPublicName'),
                'description' => __('user.preferredPublicName.description'),
                'isMultilingual' => true,
            ]))
            ->addField(new FieldText('email', [
                'label' => __('user.email'),
                'isRequired' => true,
            ]))
            ->addField(new FieldSelect('country', [
                'label' => __('common.country'),
                'options' => $countries,
                'isRequired' => true,
            ]))
            ->addField(new FieldText('url', [
                'label' => __('user.url'),
            ]))
            ->addField(new FieldText('orcid', [
                'label' => __('user.orcid'),
            ]))
            ->addField(new FieldRichTextarea('biography', [
                'label' => __('user.biography'),
                'isMultilingual' => true,
            ]))
            ->addField(new FieldText('affiliation', [
                'label' => __('user.affiliation'),
                'isMultilingual' => true,
            ]))
            ->addField(new FieldOptions('userGroupId', [
                'label' => __('submission.submit.contributorRole'),
                'type' => 'radio',
                'value' => $firstAuthorUserGroupId,
                'options' => $authorUserGroupsOptions,
            ]))
            ->addField(new FieldOptions('includeInBrowse', [
                'label' => __('submission.submit.includeInBrowse.title'),
                'type' => 'checkbox',
                'value' => true,
                'options' => [
                    ['value' => true, 'label' => __('submission.submit.includeInBrowse')],
                ]
            ]));
    }
}
