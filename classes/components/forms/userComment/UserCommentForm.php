<?php

namespace PKP\components\forms\userComment;

use PKP\components\forms\FieldText;
use PKP\components\forms\FormComponent;

class UserCommentForm extends FormComponent
{
    public const FORM_USER_COMMENT = 'addComment';
    public $id = self::FORM_USER_COMMENT;

    public function __construct(string $action, ?array $locales = [])
    {
        $this->action = $action;
        $this->method = 'POST';
        $this->locales = $locales;

        $this->addField(
            new FieldText(
                'commentText',
                [
                    'label' => 'Comment', // TODO: Localize this label
                    'description' => 'Enter your comment here.', // TODO: Localize this description
                    'isRequired' => true,
                    'value' => '',
                ]
            )
        )
            ->addHiddenField('publicationId', null);
    }
}
