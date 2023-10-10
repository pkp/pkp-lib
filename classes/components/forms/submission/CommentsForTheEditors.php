<?php
/**
 * @file classes/components/form/submission/CommentsForTheEditors.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CommentsForTheEditors
 *
 * @ingroup classes_controllers_form
 *
 * @brief A form during the For the Editors step in the submission wizard
 */

namespace PKP\components\forms\submission;

use APP\submission\Submission;
use PKP\components\forms\FieldRichTextarea;
use PKP\components\forms\FormComponent;

class CommentsForTheEditors extends FormComponent
{
    public $id = 'commentsForTheEditors';
    public $method = 'PUT';
    public Submission $submission;

    public function __construct(string $action, Submission $submission)
    {
        $this->action = $action;
        $this->submission = $submission;

        $this->addField(new FieldRichTextarea('commentsForTheEditors', [
            'label' => __('submission.submit.coverNote'),
            'description' => __('submission.wizard.commentsForTheEditor.description'),
            'value' => $this->submission->getData('commentsForTheEditors'),
        ]));
    }
}
