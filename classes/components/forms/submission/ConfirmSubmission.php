<?php
/**
 * @file classes/components/form/submission/ConfirmSubmission.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ConfirmSubmission
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for the confirm step in the submission wizard
 */

namespace PKP\components\forms\submission;

use PKP\components\forms\FieldOptions;
use PKP\components\forms\FormComponent;
use PKP\context\Context;

define('FORM_CONFIRM_SUBMISSION', 'confirmSubmission');

class ConfirmSubmission extends FormComponent
{
    /** @copydoc FormComponent::$id */
    public $id = FORM_CONFIRM_SUBMISSION;

    /** @copydoc FormComponent::$method */
    public $method = 'PUT';

    public function __construct(string $action, Context $context)
    {
        $this->action = $action;

        if ($context->getLocalizedData('copyrightNotice')) {
            $this->addField(new FieldOptions('confirmCopyright', [
                'label' => __('submission.copyright'),
                'description' => $this->getCopyrightDescription($context),
                'options' => [
                    [
                        'value' => true,
                        'label' => __('submission.copyright.agree'),
                    ],
                ],
                'value' => false,
            ]));
        }
    }

    protected function getCopyrightDescription(Context $context)
    {
        return __('submission.copyright.description')
            . '<blockquote>'
            . $context->getLocalizedData('copyrightNotice')
            . '</blockquote>';
    }
}
