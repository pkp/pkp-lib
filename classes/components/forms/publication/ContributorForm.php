<?php

/**
 * @file classes/components/form/publication/ContributorForm.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2000-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ContributorForm
 *
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for adding and editing a contributor for a publication.
 */

namespace PKP\components\forms\publication;

use APP\submission\Submission;
use PKP\components\forms\FieldAffiliations;
use PKP\components\forms\FieldOptions;
use PKP\components\forms\FieldOrcid;
use PKP\components\forms\FieldRichTextarea;
use PKP\components\forms\FieldSelect;
use PKP\components\forms\FieldText;
use PKP\components\forms\FormComponent;
use PKP\context\Context;
use PKP\orcid\OrcidManager;
use PKP\security\Role;
use PKP\userGroup\UserGroup;
use Sokil\IsoCodes\IsoCodesFactory;

class ContributorForm extends FormComponent
{
    public const FORM_CONTRIBUTOR = 'contributor';
    /** @copydoc FormComponent::$id */
    public $id = self::FORM_CONTRIBUTOR;

    /** @copydoc FormComponent::$method */
    public $method = 'POST';

    public ?Submission $submission;
    public Context $context;

    public function __construct(string $action, array $locales, ?Submission $submission, Context $context)
    {
        $this->action = $action;
        $this->locales = $locales;
        $this->submission = $submission;
        $this->context = $context;

        $authorUserGroupsOptions = UserGroup::withRoleIds([Role::ROLE_ID_AUTHOR])
            ->withContextIds([$context->getId()])
            ->get()
            ->map(fn (UserGroup $authorUserGroup) => [
                'value' => (int) $authorUserGroup->id,
                'label' => $authorUserGroup->getLocalizedData('name'),
            ]);
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
            ]));

        if (OrcidManager::isEnabled()) {
            $this->addField(new FieldOrcid('orcid', [
                'label' => __('user.orcid'),
                'tooltip' => __('orcid.about.orcidExplanation'),
            ]), [FIELD_POSITION_AFTER, 'url']);
        }


        if ($context->getSetting('requireAuthorCompetingInterests')) {
            $this->addField(new FieldRichTextarea('competingInterests', [
                'label' => __('author.competingInterests'),
                'description' => __('author.competingInterests.description'),
                'isMultilingual' => true,
            ]));
        }
        $this->addField(new FieldRichTextarea('biography', [
            'label' => __('user.biography'),
            'isMultilingual' => true,
        ]));

        $this->addField(new FieldAffiliations('affiliations', [
            'label' => __('user.affiliations'),
            'description' => __('user.affiliations.description'),
            'isMultilingual' => false,
        ]));


        if ($authorUserGroupsOptions->count() > 1) {
            $this->addField(new FieldOptions('userGroupId', [
                'label' => __('submission.submit.contributorRole'),
                'type' => 'radio',
                'value' => $authorUserGroupsOptions->first()['value'],
                'options' => $authorUserGroupsOptions->values(),
            ]));
        } else {
            $this->addHiddenField('userGroupId', $authorUserGroupsOptions->first()['value']);
        }

        $this->addField(new FieldOptions('includeInBrowse', [
            'label' => __('submission.submit.includeInBrowse.title'),
            'type' => 'checkbox',
            'value' => true,
            'options' => [
                ['value' => true, 'label' => __('submission.submit.includeInBrowse')],
            ]
        ]));
    }
}
