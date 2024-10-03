<?php
/**
 * @file classes/components/form/submission/ChangeSubmissionLanguageMetadataForm.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ChangeSubmissionLanguageMetadataForm
 *
 * @brief A form for changing submission's metadata before language change.
 */

namespace PKP\components\forms\submission;

use APP\facades\Repo;
use APP\publication\Publication;
use APP\submission\Submission;
use PKP\components\forms\Field;
use PKP\components\forms\FieldHTML;
use PKP\components\forms\FieldRadioInput;
use PKP\components\forms\FormComponent;
use PKP\components\forms\publication\TitleAbstractForm;
use PKP\context\Context;
use PKP\facades\Locale;

class ChangeSubmissionLanguageMetadataForm extends FormComponent
{
    public const FORM_CHANGE_SUBMISSION_LANGUAGE_METADATA = 'changeSubmissionLanguageMetadata';
    public $id = self::FORM_CHANGE_SUBMISSION_LANGUAGE_METADATA;
    public $method = 'PUT';

    public function __construct(string $action, Submission $submission, Publication $publication, Context $context)
    {
        $this->action = $action;

        $this->addGroup([
            'id' => 'language',
        ]);

        $submissionLocale = $submission->getData('locale');
        $supportedLocaleNames = collect($context->getSupportedSubmissionLocaleNames());
        $submissionLocaleNames = collect(Locale::getSubmissionLocaleDisplayNames([$submissionLocale]));
        $showWhen = ['locale', $supportedLocaleNames->diffKeys($submissionLocaleNames)->keys()->toArray()];

        $this->addGroup([
            'id' => 'metadata',
            'showWhen' => $showWhen,
        ]);

        // Language
        $localeOptions = $supportedLocaleNames
            ->union($submissionLocaleNames)
            ->sortKeys()
            ->map(fn ($name, $key) => ['value' => $key, 'label' => $name])
            ->values()
            ->toArray();
        $this->addField(new FieldRadioInput('locale', [
            'label' => __('submission.submit.submissionLocale'),
            'description' => __('submission.list.changeSubmissionLanguage.languageDescription'),
            'groupId' => 'language',
            'type' => 'radio',
            'options' => $localeOptions,
            'isRequired' => true,
            'value' => $submissionLocale,
        ]));

        // Metadata description
        $this->addField(new FieldHTML('metadataDescription', [
            'description' => __('submission.list.changeSubmissionLanguage.metadataDescription'),
            'groupId' => 'metadata',
        ]));

        $submissionLocaleName = $submissionLocaleNames->get($submissionLocale);

        // Title and abstract
        $titleAbstractForm = $this->getTitleAbstractForm(FormComponent::ACTION_EMIT, [$submissionLocale], $publication, $context);
        $this->setField($titleAbstractForm->getField('title'), $submissionLocaleName, $submissionLocale);
        $this->setField($titleAbstractForm->getField('abstract'), $submissionLocaleName, $submissionLocale);

        // Add cancel button
        $this->addCancel();
    }

    protected function addCancel() {
        $this->addPage([
            'id' => 'default',
            'submitButton' => ['label' => __('common.confirm')],
            'cancelButton' => ['label' => __('common.cancel')],
        ]);
        collect($this->groups)->each(fn ($_, $i) => ($this->groups[$i]['pageId'] = 'default'));
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

    protected function setField(Field $field, string $submissionLocaleName, string $submissionLocale): void {
        if ($field->isRequired) {
            $field->groupId = 'metadata';
            $field->description = __("submission.list.changeSubmissionLanguage.metadataDescription.{$field->name}", ['language' => $submissionLocaleName]);
            if ($field->isMultilingual) {
                $field->isMultilingual = false;
                $field->value = $field->value[$submissionLocale];
            }
            $this->addField($field);
        }
    }
}
