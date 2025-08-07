<?php

namespace PKP\components\forms\userComment;

use PKP\components\forms\FieldText;
use PKP\components\forms\FormComponent;

class UserCommentReportForm extends FormComponent
{
    public const FORM_USER_COMMENT_REPORT = 'reportComment';
    public $id = self::FORM_USER_COMMENT_REPORT;
    public function __construct(string $action)
    {
        $this->action = $action;
        $this->method = 'POST';

        $this->addField(new FieldText('note', [
            'label' => 'Report Note',
            'description' => 'Enter a note to explain why you are reporting this comment.',
            'isRequired' => true,
            'value' => '',
        ]));
    }
}
