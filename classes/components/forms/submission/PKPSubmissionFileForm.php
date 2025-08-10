<?php
/**
 * @file classes/components/form/context/PKPSubmissionFileForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPSubmissionFileForm
 *
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for editing a submission file
 */

namespace PKP\components\forms\submission;

use PKP\components\forms\FieldOptions;
use PKP\components\forms\FormComponent;

class PKPSubmissionFileForm extends FormComponent
{
    public const FORM_SUBMISSION_FILE = 'submissionFile';
    public $id = self::FORM_SUBMISSION_FILE;
    public $method = 'PUT';

    /**
     * Constructor
     *
     * @param string $action URL to submit the form to
     * @param array $genres List of genres to use as options
     */
    public function __construct($action, $genres)
    {
        $this->action = $action;

        $this->addField(new FieldOptions('genreId', [
            'label' => __('submission.submit.genre.label'),
            'description' => __('submission.submit.genre.description'),
            'type' => 'radio',
            'options' => array_map(function ($genre) {
                return [
                    'value' => (int) $genre->id,

                    'label' => htmlspecialchars($genre->getLocalizedData('name')),
                ];
            }, $genres),
            'value' => 0,
        ]));
    }
}
