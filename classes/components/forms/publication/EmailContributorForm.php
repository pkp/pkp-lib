<?php

/**
 * @file classes/components/form/publication/EmailContributorForm.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2000-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EmailContributorForm
 *
 * @ingroup classes_email_controllers_form
 *
 * @brief A preset form for emailing contributors.
 */

namespace PKP\components\forms\publication;

use APP\submission\Submission;
use PKP\components\forms\FieldText;
use PKP\components\forms\FieldRichTextarea;
use PKP\components\forms\FormComponent;
use PKP\context\Context;

class EmailContributorForm extends FormComponent
{
    public const FORM_EMAIL_CONTRIBUTOR = 'email_contributor';
    /** @copydoc FormComponent::$id */
    public $id = self::FORM_EMAIL_CONTRIBUTOR;

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

        $this->addField(new FieldText('to', [
				'label' => __('email.to'),
				'value' => '',
                'size' => 'large',
				'isRequired' => true,
			]))->addField(new FieldText('cc', [
				'label' => __('email.cc'),
				'value' => '',
                'size' => 'large',
			]))->addField(new FieldText('bcc', [
				'label' => __('email.bcc'),
				'value' => '',
                'size' => 'large',
			]))->addField(new FieldText('subject', [
				'label' => __('email.subject'),
				'value' => '',
                'size' => 'large',
				'isRequired' => true,
			]))
			->addField(new FieldRichTextarea('body', [
				'label' => __('email.email'),
				'size' => 'large',
				'value' => '',
				'isRequired' => true,
            ]));
    }
}
