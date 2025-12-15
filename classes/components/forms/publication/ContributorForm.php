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
use PKP\author\contributorRole\ContributorRole;
use PKP\author\contributorRole\ContributorType;
use PKP\components\forms\FieldAffiliations;
use PKP\components\forms\FieldCreditRoles;
use PKP\components\forms\FieldOptions;
use PKP\components\forms\FieldOrcid;
use PKP\components\forms\FieldRichTextarea;
use PKP\components\forms\FieldSelect;
use PKP\components\forms\FieldText;
use PKP\components\forms\FormComponent;
use PKP\context\Context;
use PKP\orcid\OrcidManager;
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
        array_unshift($countries, "");

        $contributorRoles = ContributorRole::query()
            ->withContextId($context->getId())
            ->get()
            ->map(fn (ContributorRole $role): array => ['value' => $role->id, 'label' => $role->getLocalizedData('name')])
            ->values()
            ->toArray();

        $showWhenPerson = ['contributorType', [ContributorType::PERSON->getName()]];
        $showWhenOrganization = ['contributorType', [ContributorType::ORGANIZATION->getName()]];
        $showWhenPersOrg = ['contributorType', [ContributorType::PERSON->getName(), ContributorType::ORGANIZATION->getName()]];

        $this->addField(new FieldOptions('contributorType', [
            'label' => __('submission.submit.contributorType.title'),
            'description' => __('submission.submit.contributorType.description'),
            'isRequired' => true,
            'type' => 'radio',
            'value' => ContributorType::PERSON->getName(),
            'options' => array_map(
                fn (string $name): array => ['value' => $name, 'label' => __("submission.submit.contributorType." . strtolower($name))],
                ContributorType::getTypes()
            ),
        ]));
        $this->addField(new FieldText('givenName', [
                'label' => __('user.givenName'),
                'showWhen' => $showWhenPerson,
                'isMultilingual' => true,
                'isRequired' => $showWhenPerson
            ]))
            ->addField(new FieldText('familyName', [
                'label' => __('user.familyName'),
                'showWhen' => $showWhenPerson,
                'isMultilingual' => true,
            ]))
            ->addField(new FieldText('preferredPublicName', [
                'label' => __('user.preferredPublicName'),
                'showWhen' => $showWhenPerson,
                'description' => __('user.preferredPublicName.description'),
                'isMultilingual' => true,
            ]));
        $this->addField(new FieldText('organizationName', [
            'label' => __('submission.submit.contributor.organizationName'),
            'showWhen' => $showWhenOrganization,
            'isMultilingual' => true,
            'isRequired' => $showWhenOrganization
        ]));
        $this->addField(new FieldText('email', [
                'label' => __('user.email'),
                'isRequired' => $showWhenPersOrg,
            ]))
            ->addField(new FieldSelect('country', [
                'label' => __('common.country'),
                'options' => $countries,
                'isRequired' => $showWhenPersOrg,
            ]));
        $this->addField(new FieldText('rorId', [
                'label' => __('submission.submit.contributor.rorId'),
                'showWhen' => $showWhenOrganization,
                'description' => __('submission.submit.contributor.rorId.description'),
        ]));
        $this->addField(new FieldText('url', [
            'label' => __('user.url'),
            'showWhen' => $showWhenPersOrg,
        ]));

        if (OrcidManager::isEnabled()) {
            $this->addField(new FieldOrcid('orcid', [
                'label' => __('user.orcid'),
                'showWhen' => $showWhenPerson,
                'tooltip' => __('orcid.about.orcidExplanation'),
            ]), [FIELD_POSITION_AFTER, 'url']);
        }


        if ($context->getSetting('requireAuthorCompetingInterests')) {
            $this->addField(new FieldRichTextarea('competingInterests', [
                'label' => __('author.competingInterests'),
                'description' => __('author.competingInterests.description'),
                'isMultilingual' => true,
                'isRequired' => true,
            ]));
        }
        $this->addField(new FieldRichTextarea('biography', [
            'label' => __('user.biography'),
            'showWhen' => $showWhenPersOrg,
            'isMultilingual' => true,
        ]));

        $this->addField(new FieldAffiliations('affiliations', [
            'label' => __('user.affiliations'),
            'showWhen' => $showWhenPersOrg,
            'description' => __('user.affiliations.description'),
            'isMultilingual' => false,
        ]));


        if (count($contributorRoles) > 1) {
            $this->addField(new FieldOptions('contributorRoles', [
                'label' => __('submission.submit.contributorRoles.label'),
                'type' => 'checkbox',
                'isRequired' => true,
                'value' => [],
                'options' => $contributorRoles,
            ]));
        } else {
            $this->addHiddenField('contributorRoles', $contributorRoles[0]['value'] ?? []);
        }

        $this->addField(new FieldCreditRoles('creditRoles', [
            'label' => __('submission.submit.creditRoles.label'),
            'description' => __('submission.submit.creditRoles.description'),
        ]));

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
