<?php
/**
 * @file classes/components/form/submission/ReconfigureSubmission.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReconfigureSubmission
 *
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for configuring the submission wizard, such as the
 *   submission's section or language, after the submission was started.
 */

namespace PKP\components\forms\submission;

use APP\publication\Publication;
use APP\submission\Submission;
use PKP\components\forms\FieldOptions;
use PKP\components\forms\FormComponent;
use PKP\context\Context;

abstract class ReconfigureSubmission extends FormComponent
{
    public $id = 'reconfigureSubmission';
    public $method = 'PUT';
    public Submission $submission;
    public Publication $publication;
    public Context $context;

    public function __construct(string $action, Submission $submission, Publication $publication, Context $context)
    {
        $this->action = $action;
        $this->context = $context;
        $this->publication = $publication;
        $this->submission = $submission;

        $locales = $context->getSupportedSubmissionLocaleNames();
        if (count($locales) > 1) {
            $this->addLocaleField($locales, $submission);
        }
    }

    protected function addLocaleField(array $locales): void
    {
        $options = [];
        foreach ($locales as $locale => $name) {
            $options[] = [
                'value' => $locale,
                'label' => $name,
            ];
        }
        $this->addField(new FieldOptions('locale', [
            'label' => __('submission.submit.submissionLocale'),
            'description' => __('submission.submit.submissionLocaleDescription'),
            'type' => 'radio',
            'options' => $options,
            'isRequired' => true,
            'value' => $this->submission->getData('locale'),
        ]));
    }
}
