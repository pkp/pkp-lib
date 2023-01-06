<?php

/**
 * @file classes/submission/PKPSubmissionMetadataFormImplementation.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionMetadataFormImplementation
 * @ingroup submission
 *
 * @deprecated 3.4
 *
 * @brief This can be used by other forms that want to
 * implement submission metadata data and form operations.
 */

namespace PKP\submission;

use APP\core\Application;

use APP\facades\Repo;
use APP\log\SubmissionEventLogEntry;
use PKP\context\Context;
use PKP\db\DAORegistry;
use PKP\log\SubmissionLog;

class PKPSubmissionMetadataFormImplementation
{
    /** @var Form Form that uses this implementation */
    public $_parentForm;

    /**
     * Constructor.
     *
     * @param Form $parentForm A form that can use this form.
     */
    public function __construct($parentForm = null)
    {
        assert($parentForm instanceof \PKP\form\Form);
        $this->_parentForm = $parentForm;
    }

    /**
     * Determine whether or not abstracts are required.
     *
     * @param Submission $submission
     *
     * @return bool
     */
    public function _getAbstractsRequired($submission)
    {
        return true; // Required by default
    }

    /**
     * Add checks to form.
     *
     * @param Submission $submission
     */
    public function addChecks($submission)
    {
        // Validation checks.
        $this->_parentForm->addCheck(new \PKP\form\validation\FormValidatorLocale($this->_parentForm, 'title', 'required', 'submission.submit.form.titleRequired', $submission->getCurrentPublication()->getData('locale')));
        if ($this->_getAbstractsRequired($submission)) {
            $this->_parentForm->addCheck(new \PKP\form\validation\FormValidatorLocale($this->_parentForm, 'abstract', 'required', 'submission.submit.form.abstractRequired', $submission->getCurrentPublication()->getData('locale')));
        }

        // Validates that at least one author has been added.
        $this->_parentForm->addCheck(new \PKP\form\validation\FormValidatorCustom(
            $this->_parentForm,
            'authors',
            'required',
            'submission.submit.form.authorRequired',
            function () use ($submission) {
                return !empty($submission->getCurrentPublication()->getData('authors'));
            }
        ));

        $contextDao = Application::getContextDao();
        $context = $contextDao->getById($submission->getContextId());
        $metadataFields = Application::getMetadataFields();
        foreach ($metadataFields as $field) {
            $requiredLocaleKey = 'submission.submit.form.' . $field . 'Required';
            if ($context->getData($field) === Context::METADATA_REQUIRE) {
                switch ($field) {
                    case in_array($field, $this->getLocaleFieldNames()):
                        $this->_parentForm->addCheck(new \PKP\form\validation\FormValidatorLocale($this->_parentForm, $field, 'required', $requiredLocaleKey, $submission->getCurrentPublication()->getData('locale')));
                        break;
                    case in_array($field, $this->getTagitFieldNames()):
                        $this->_parentForm->addCheck(new \PKP\form\validation\FormValidatorCustom($this->_parentForm, $field, 'required', $requiredLocaleKey, function ($field, $form, $name) {
                            $data = (array) $form->getData('keywords');
                            return array_key_exists($name, $data);
                        }, [$this->_parentForm, $submission->getCurrentPublication()->getData('locale') . '-' . $field]));
                        break;
                    case 'citations':
                        $form = $this->_parentForm;
                        $this->_parentForm->addCheck(new \PKP\form\validation\FormValidatorCustom($this->_parentForm, 'citationsRaw', 'required', $requiredLocaleKey, function ($key) use ($form) {
                            return !empty($form->getData('citationsRaw'));
                        }));
                        break;
                    default:
                        $this->_parentForm->addCheck(new \PKP\form\validation\FormValidator($this->_parentForm, $field, 'required', $requiredLocaleKey));
                }
            }
        }
    }

    /**
     * Initialize form data from current submission.
     *
     * @param Submission $submission
     */
    public function initData($submission)
    {
        if (isset($submission)) {
            $publication = $submission->getCurrentPublication();
            $formData = [
                'title' => $publication->getData('title'),
                'prefix' => $publication->getData('prefix'),
                'subtitle' => $publication->getData('subtitle'),
                'abstract' => $publication->getData('abstract'),
                'coverage' => $publication->getData('coverage'),
                'type' => $publication->getData('type'),
                'source' => $publication->getData('source'),
                'rights' => $publication->getData('rights'),
                'citationsRaw' => $publication->getData('citationsRaw'),
                'locale' => $publication->getData('locale'),
            ];

            foreach ($formData as $key => $data) {
                $this->_parentForm->setData($key, $data);
            }

            // get the supported locale keys
            $locales = array_keys($this->_parentForm->supportedLocales);

            // load the persisted metadata controlled vocabularies
            $submissionKeywordDao = DAORegistry::getDAO('SubmissionKeywordDAO'); /** @var SubmissionKeywordDAO $submissionKeywordDao */
            $submissionSubjectDao = DAORegistry::getDAO('SubmissionSubjectDAO'); /** @var SubmissionSubjectDAO $submissionSubjectDao */
            $submissionDisciplineDao = DAORegistry::getDAO('SubmissionDisciplineDAO'); /** @var SubmissionDisciplineDAO $submissionDisciplineDao */
            $submissionAgencyDao = DAORegistry::getDAO('SubmissionAgencyDAO'); /** @var SubmissionAgencyDAO $submissionAgencyDao */
            $submissionLanguageDao = DAORegistry::getDAO('SubmissionLanguageDAO'); /** @var SubmissionLanguageDAO $submissionLanguageDao */

            $this->_parentForm->setData('subjects', $submissionSubjectDao->getSubjects($publication->getId(), $locales));
            $this->_parentForm->setData('keywords', $submissionKeywordDao->getKeywords($publication->getId(), $locales));
            $this->_parentForm->setData('disciplines', $submissionDisciplineDao->getDisciplines($publication->getId(), $locales));
            $this->_parentForm->setData('agencies', $submissionAgencyDao->getAgencies($publication->getId(), $locales));
            $this->_parentForm->setData('languages', $submissionLanguageDao->getLanguages($publication->getId(), $locales));
            $this->_parentForm->setData('abstractsRequired', $this->_getAbstractsRequired($submission));
        }
    }

    /**
     * Assign form data to user-submitted data.
     */
    public function readInputData()
    {
        // 'keywords' is a tagit catchall that contains an array of values for each keyword/locale combination on the form.
        $userVars = ['title', 'prefix', 'subtitle', 'abstract', 'coverage', 'type', 'source', 'rights', 'keywords', 'citationsRaw', 'locale'];
        $this->_parentForm->readUserVars($userVars);
    }

    /**
     * Get the names of fields for which data should be localized
     *
     * @return array
     */
    public function getLocaleFieldNames()
    {
        return ['title', 'prefix', 'subtitle', 'abstract', 'coverage', 'type', 'source', 'rights'];
    }

    /**
     * Get the names of fields for which tagit is used
     *
     * @return array
     */
    public function getTagitFieldNames()
    {
        return ['subjects', 'keywords', 'disciplines', 'agencies', 'languages'];
    }

    /**
     * Save changes to submission.
     *
     * @param Submission $submission
     * @param PKPRequest $request
     *
     * @return Submission
     */
    public function execute($submission, $request)
    {
        $publication = $submission->getCurrentPublication();
        $context = $request->getContext();

        // Get params to update
        $params = [
            'title' => $this->_parentForm->getData('title'),
            'prefix' => $this->_parentForm->getData('prefix'),
            'subtitle' => $this->_parentForm->getData('subtitle'),
            'abstract' => $this->_parentForm->getData('abstract'),
            'coverage' => $this->_parentForm->getData('coverage'),
            'type' => $this->_parentForm->getData('type'),
            'rights' => $this->_parentForm->getData('rights'),
            'source' => $this->_parentForm->getData('source'),
            'citationsRaw' => $this->_parentForm->getData('citationsRaw'),
        ];

        // Update locale
        $newLocale = $this->_parentForm->getData('locale');
        if ($newLocale) {
            $oldLocale = $publication->getData('locale');
            if (in_array($newLocale, $context->getData('supportedSubmissionLocales'))) {
                $params['locale'] = $newLocale;
            }
            if ($newLocale !== $oldLocale) {
                Repo::author()->changePublicationLocale($publication->getId(), $oldLocale, $newLocale);
            }
        }

        // Save the publication
        Repo::publication()->edit($publication, $params);
        $publication = Repo::publication()->get($publication->getId());

        // get the supported locale keys
        $locales = array_keys($this->_parentForm->supportedLocales);

        // persist the metadata/keyword fields.
        $submissionKeywordDao = DAORegistry::getDAO('SubmissionKeywordDAO'); /** @var SubmissionKeywordDAO $submissionKeywordDao */
        $submissionSubjectDao = DAORegistry::getDAO('SubmissionSubjectDAO'); /** @var SubmissionSubjectDAO $submissionSubjectDao */
        $submissionDisciplineDao = DAORegistry::getDAO('SubmissionDisciplineDAO'); /** @var SubmissionDisciplineDAO $submissionDisciplineDao */
        $submissionAgencyDao = DAORegistry::getDAO('SubmissionAgencyDAO'); /** @var SubmissionAgencyDAO $submissionAgencyDao */
        $submissionLanguageDao = DAORegistry::getDAO('SubmissionLanguageDAO'); /** @var SubmissionLanguageDAO $submissionLanguageDao */

        $keywords = [];
        $agencies = [];
        $disciplines = [];
        $languages = [];
        $subjects = [];

        $tagitKeywords = $this->_parentForm->getData('keywords');

        if (is_array($tagitKeywords)) {
            foreach ($locales as $locale) {
                $keywords[$locale] = array_key_exists($locale . '-keywords', $tagitKeywords) ? $tagitKeywords[$locale . '-keywords'] : [];
                $agencies[$locale] = array_key_exists($locale . '-agencies', $tagitKeywords) ? $tagitKeywords[$locale . '-agencies'] : [];
                $disciplines[$locale] = array_key_exists($locale . '-disciplines', $tagitKeywords) ? $tagitKeywords[$locale . '-disciplines'] : [];
                $languages[$locale] = array_key_exists($locale . '-languages', $tagitKeywords) ? $tagitKeywords[$locale . '-languages'] : [];
                $subjects[$locale] = array_key_exists($locale . '-subjects', $tagitKeywords) ? $tagitKeywords[$locale . '-subjects'] : [];
            }
        }

        // persist the controlled vocabs
        $submissionKeywordDao->insertKeywords($keywords, $submission->getCurrentPublication()->getId());
        $submissionAgencyDao->insertAgencies($agencies, $submission->getCurrentPublication()->getId());
        $submissionDisciplineDao->insertDisciplines($disciplines, $submission->getCurrentPublication()->getId());
        $submissionLanguageDao->insertLanguages($languages, $submission->getCurrentPublication()->getId());
        $submissionSubjectDao->insertSubjects($subjects, $submission->getCurrentPublication()->getId());

        // Only log modifications on completed submissions
        if ($submission->getSubmissionProgress() == 0) {
            // Log the metadata modification event.
            SubmissionLog::logEvent($request, $submission, SubmissionEventLogEntry::SUBMISSION_LOG_METADATA_UPDATE, 'submission.event.general.metadataUpdated');
        }
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\submission\PKPSubmissionMetadataFormImplementation', '\PKPSubmissionMetadataFormImplementation');
}
