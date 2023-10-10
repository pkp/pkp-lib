<?php
/**
 * @file classes/components/form/publication/PKPMetadataForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPMetadataForm
 *
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for setting a publication's metadata fields
 */

namespace PKP\components\forms\publication;

use APP\publication\Publication;
use PKP\components\forms\FieldControlledVocab;
use PKP\components\forms\FieldRichTextarea;
use PKP\components\forms\FieldText;
use PKP\components\forms\FormComponent;
use PKP\context\Context;
use PKP\submission\SubmissionAgencyDAO;
use PKP\submission\SubmissionDisciplineDAO;
use PKP\submission\SubmissionKeywordDAO;
use PKP\submission\SubmissionLanguageDAO;
use PKP\submission\SubmissionSubjectDAO;

define('FORM_METADATA', 'metadata');

class PKPMetadataForm extends FormComponent
{
    public $id = FORM_METADATA;
    public $method = 'PUT';
    public Context $context;
    public Publication $publication;

    /**
     * Constructor
     *
     * @param string $action URL to submit the form to
     * @param array $locales Supported locales
     * @param Publication $publication The publication to change settings for
     * @param Context $context The journal or press of the submission.
     * @param string $suggestionUrlBase The base URL to get suggestions for controlled vocab.
     */
    public function __construct(string $action, array $locales, Publication $publication, Context $context, string $suggestionUrlBase)
    {
        $this->action = $action;
        $this->locales = $locales;
        $this->context = $context;
        $this->publication = $publication;

        if ($this->enabled('keywords')) {
            $this->addField(new FieldControlledVocab('keywords', [
                'label' => __('common.keywords'),
                'tooltip' => __('manager.setup.metadata.keywords.description'),
                'isMultilingual' => true,
                'apiUrl' => str_replace('__vocab__', SubmissionKeywordDAO::CONTROLLED_VOCAB_SUBMISSION_KEYWORD, $suggestionUrlBase),
                'locales' => $this->locales,
                'value' => (array) $publication->getData('keywords'),
            ]));
        }

        if ($this->enabled('subjects')) {
            $this->addField(new FieldControlledVocab('subjects', [
                'label' => __('common.subjects'),
                'tooltip' => __('manager.setup.metadata.subjects.description'),
                'isMultilingual' => true,
                'apiUrl' => str_replace('__vocab__', SubmissionSubjectDAO::CONTROLLED_VOCAB_SUBMISSION_SUBJECT, $suggestionUrlBase),
                'locales' => $this->locales,
                'value' => (array) $publication->getData('subjects'),
            ]));
        }

        if ($this->enabled('disciplines')) {
            $this->addField(new FieldControlledVocab('disciplines', [
                'label' => __('search.discipline'),
                'tooltip' => __('manager.setup.metadata.disciplines.description'),
                'isMultilingual' => true,
                'apiUrl' => str_replace('__vocab__', SubmissionDisciplineDAO::CONTROLLED_VOCAB_SUBMISSION_DISCIPLINE, $suggestionUrlBase),
                'locales' => $this->locales,
                'value' => (array) $publication->getData('disciplines'),
            ]));
        }

        if ($this->enabled('languages')) {
            $this->addField(new FieldControlledVocab('languages', [
                'label' => __('common.languages'),
                'tooltip' => __('manager.setup.metadata.languages.description'),
                'isMultilingual' => true,
                'apiUrl' => str_replace('__vocab__', SubmissionLanguageDAO::CONTROLLED_VOCAB_SUBMISSION_LANGUAGE, $suggestionUrlBase),
                'locales' => $this->locales,
                'value' => (array) $publication->getData('languages'),
            ]));
        }

        if ($this->enabled('agencies')) {
            $this->addField(new FieldControlledVocab('supportingAgencies', [
                'label' => __('submission.supportingAgencies'),
                'tooltip' => __('manager.setup.metadata.agencies.description'),
                'isMultilingual' => true,
                'apiUrl' => str_replace('__vocab__', SubmissionAgencyDAO::CONTROLLED_VOCAB_SUBMISSION_AGENCY, $suggestionUrlBase),
                'locales' => $this->locales,
                'value' => (array) $publication->getData('supportingAgencies'),
            ]));
        }

        if ($this->enabled('coverage')) {
            $this->addField(new FieldText('coverage', [
                'label' => __('manager.setup.metadata.coverage'),
                'tooltip' => __('manager.setup.metadata.coverage.description'),
                'isMultilingual' => true,
                'value' => $publication->getData('coverage'),
            ]));
        }

        if ($this->enabled('rights')) {
            $this->addField(new FieldText('rights', [
                'label' => __('submission.rights'),
                'tooltip' => __('manager.setup.metadata.rights.description'),
                'isMultilingual' => true,
                'value' => $publication->getData('rights'),
            ]));
        }

        if ($this->enabled('source')) {
            $this->addField(new FieldText('source', [
                'label' => __('common.source'),
                'tooltip' => __('manager.setup.metadata.source.description'),
                'isMultilingual' => true,
                'value' => $publication->getData('source'),
            ]));
        }

        if ($this->enabled('type')) {
            $this->addField(new FieldText('type', [
                'label' => __('common.type'),
                'tooltip' => __('manager.setup.metadata.type.description'),
                'isMultilingual' => true,
                'value' => $publication->getData('type'),
            ]));
        }

        if ($this->enabled('dataAvailability')) {
            $this->addField(new FieldRichTextarea('dataAvailability', [
                'label' => __('submission.dataAvailability'),
                'tooltip' => __('manager.setup.metadata.dataAvailability.description'),
                'isMultilingual' => true,
                'value' => $publication->getData('dataAvailability'),
            ]));
        }

        if ($this->enabled('pub-id::publisher-id')) {
            $this->addField(new FieldText('pub-id::publisher-id', [
                'label' => __('submission.publisherId'),
                'tooltip' => __('submission.publisherId.description'),
                'value' => $publication->getData('pub-id::publisher-id'),
            ]));
        }
    }

    /**
     * Whether or not a metadata field is enabled in this form
     */
    protected function enabled(string $setting): bool
    {
        if ($setting === 'pub-id::publisher-id') {
            return in_array('publication', (array) $this->context->getData('enablePublisherId'));
        }
        return (bool) $this->context->getData($setting);
    }
}
