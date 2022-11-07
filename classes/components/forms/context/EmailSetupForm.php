<?php
/**
 * @file classes/components/form/context/EmailSetupForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EmailSetupForm
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for configuring a context's email settings.
 */

namespace APP\components\forms\context;

use PKP\components\forms\context\PKPEmailSetupForm;
use PKP\components\forms\FieldOptions;
use PKP\context\Context;

class EmailSetupForm extends PKPEmailSetupForm
{
    public const GROUP_POSTED = 'posted';
    public const FIELD_POSTED_ACK = 'postedAcknowledgement';

    public function __construct(string $action, array $locales, Context $context)
    {
        parent::__construct($action, $locales, $context);

        $this->addGroup([
            'id' => self::GROUP_POSTED,
            'label' => __('manager.preprintPosted'),
            'description' => __('manager.preprintPosted.description'),
        ], [FIELD_POSITION_AFTER, self::GROUP_NEW_SUBMISSION])
            ->addPostedAcknowledgementField();
    }

    protected function addPostedAcknowledgementField(): self
    {
        return $this->addField(new FieldOptions(self::FIELD_POSTED_ACK, [
            'label' => __('manager.preprintPosted'),
            'description' => __('manager.preprintPosted.fieldDescription'),
            'type' => 'radio',
            'options' => [
                ['value' => true, 'label' => __('manager.submissionAck.allAuthors')],
                ['value' => false, 'label' => __('manager.submissionAck.off')],
            ],
            'value' => $this->context->getData(self::FIELD_POSTED_ACK),
            'groupId' => self::GROUP_POSTED,
        ]));
    }
}
