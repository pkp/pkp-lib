<?php
/**
 * @file classes/components/form/submission/ChangeSubmissionLanguageMetadataForm.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ChangeSubmissionLanguageMetadataForm
 *
 * @brief A form for changing submission's metadata after language change.
 */

namespace PKP\components\forms\submission;

use APP\facades\Repo;
use APP\publication\Publication;
use APP\submission\Submission;
use PKP\components\forms\FieldOptions;
use PKP\components\forms\FieldRadioInput;
use PKP\components\forms\FormComponent;
use PKP\components\forms\publication\PKPCitationsForm;
use PKP\components\forms\publication\PKPMetadataForm;
use PKP\components\forms\publication\TitleAbstractForm;
use PKP\context\Context;
use PKP\facades\Locale;

define('FORM_CHANGE_SUBMISSION_LANGUAGE_METADATA', 'changeSubmissionLanguageMetadata');

class ChangeSubmissionLanguageMetadataForm extends FormComponent
{
    public $id = FORM_CHANGE_SUBMISSION_LANGUAGE_METADATA;
    public $method = 'PUT';

    public function __construct(string $action, Submission $submission, Publication $publication, Context $context, array $locales, ?string $metadataVocabSuggestionUrlBase)
    {
        $this->action = $action;
        $this->locales = $locales;

        $this->addGroup([
            'id' => 'lang_lang',
        ]);

        $showWhen = ['locale', array_values(array_filter($context->getSupportedSubmissionLocales(), fn ($l) => $l !== $submission->getData('locale')))];

        $this->addGroup([
            'id' => 'lang_metadata',
            'showWhen' => $showWhen,
        ]);

        $this->addGroup([
            'id' => 'lang_contributorsText',
            'showWhen' => $showWhen,
        ]);

        // Language
        $localeOptions = collect($context->getSupportedSubmissionLocaleNames() + Locale::getSubmissionLocaleDisplayNames([$submission->getData('locale')]))
            ->sortKeys()
            ->map(fn ($name, $key) => ['value' => $key, 'label' => $name])
            ->values()
            ->toArray();
        $this->addField(new FieldRadioInput('locale', [
            'label' => __('submission.submit.submissionLocale'),
            'description' => __('submission.submit.submissionLocaleDescription'),
            'groupId' => 'lang_lang',
            'type' => 'radio',
            'options' => $localeOptions,
            'isRequired' => true,
            'value' => $submission->getData('locale'),
        ]));

        // Title and abstaract
        $titleAbstractForm = $this->getTitleAbstractForm(FormComponent::ACTION_EMIT, $locales, $publication, $context);
        $this->addReqField($titleAbstractForm, 'prefix');
        $this->addReqField($titleAbstractForm, 'title');
        $this->addReqField($titleAbstractForm, 'subtitle');
        $this->addReqField($titleAbstractForm, 'abstract');

        // Metadata
        $metadataForm = new PKPMetadataForm(FormComponent::ACTION_EMIT, $locales, $publication, $context, $metadataVocabSuggestionUrlBase);
        foreach($metadataForm->fields as $field) {
            if ($context->getData($field->name) === Context::METADATA_REQUIRE) {
                $field->isRequired = true;
                $this->addField($field);
            }
        }

        // Citations
        if (!!$context->getData('citations') || !!$publication->getData('citationsRaw')) {
            $this->addField((new PKPCitationsForm(FormComponent::ACTION_EMIT, $publication, true))->getField('citationsRaw'));
        }

        // Contributors notify
        $this->addField(new FieldOptions('contributors', [
            'label' => __('submission.list.changeLangContributorsTitle'),
            'description' => __('submission.list.changeLangContributorsDescription'),
            'groupId' => 'lang_contributorsText',
            'value' => '',
        ]));
    }

    /**
     * Add required fileds only
     */
    protected function addReqField(FormComponent $form, string $fieldName): void
    {
        $field = $form->getField($fieldName);
        if ($field->isRequired) {
            $this->addField($field);
        }
    }

    protected function getTitleAbstractForm(string $publicationApiUrl, array $locales, Publication $publication, Context $context): TitleAbstractForm
    {
        $pubSecId = $publication->getData('sectionId');
        $section = $pubSecId ? Repo::section()->get($pubSecId, $context->getId()) : null;
        return new TitleAbstractForm(
            $publicationApiUrl,
            $locales,
            $publication,
            $section ? (int) $section->getData('wordCount') : 0,
            $section && !$section->getData('abstractsNotRequired')
        );
    }

    public function getConfig(): array
    {
        $config = parent::getConfig();
        
        foreach ($config['fields'] as $i => $field) {
            if (!isset($field['groupId'])) {
                $config['fields'][$i]['groupId'] = 'lang_metadata';
            }
        }

        return $config;
    }
}
