<?php
/**
 * @file classes/components/form/context/PKPReviewSetupForm.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPReviewSetupForm
 *
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for configuring review options, such as the default
 *  review type and deadlines.
 */

namespace PKP\components\forms\context;

use PKP\components\forms\FieldHTML;
use PKP\context\Context;
use PKP\components\forms\FieldOptions;
use PKP\components\forms\FieldSlider;
use PKP\components\forms\FieldText;
use PKP\components\forms\FormComponent;
use PKP\submission\reviewAssignment\ReviewAssignment;

class PKPReviewSetupForm extends FormComponent
{
    public const FORM_REVIEW_SETUP = 'reviewSetup';
    public $id = self::FORM_REVIEW_SETUP;
    public $method = 'PUT';

    protected const REVIEW_SETTINGS_GROUP = 'reviewSettingsGroup';
    protected const REVIEW_REMINDER_GROUP = 'reviewReminderGroup';

    public const MIN_REMINDER_NOTIFICATION_SEND_IN_DAYS = 0;
    public const MAX_REMINDER_NOTIFICATION_SEND_IN_DAYS = 14;

    /**
     * Constructor
     *
     * @param string $action URL to submit the form to
     * @param array $locales Supported locales
     * @param \PKP\context\Context $context Journal or Press to change settings for
     */
    public function __construct($action, $locales, $context)
    {
        $this->action = $action;
        $this->locales = $locales;

        $this
            ->addDefaultFields($context)
            ->addReminderFields($context);
    }

    /**
     * Add the default review control fields
     */
    protected function addDefaultFields(Context $context): static
    {
        $this
            ->addGroup([
                'id' => self::REVIEW_SETTINGS_GROUP
            ])
            ->addField(new FieldOptions('defaultReviewMode', [
                'label' => __('manager.setup.reviewOptions.reviewMode'),
                'type' => 'radio',
                'value' => $context->getData('defaultReviewMode'),
                'options' => [
                    ['value' => ReviewAssignment::SUBMISSION_REVIEW_METHOD_DOUBLEANONYMOUS, 'label' => __('editor.submissionReview.doubleAnonymous')],
                    ['value' => ReviewAssignment::SUBMISSION_REVIEW_METHOD_ANONYMOUS, 'label' => __('editor.submissionReview.anonymous')],
                    ['value' => ReviewAssignment::SUBMISSION_REVIEW_METHOD_OPEN, 'label' => __('editor.submissionReview.open')],
                ],
                'groupId' => self::REVIEW_SETTINGS_GROUP,
            ]))
            ->addField(new FieldOptions('restrictReviewerFileAccess', [
                'label' => __('manager.setup.reviewOptions.restrictReviewerFileAccess'),
                'type' => 'checkbox',
                'value' => $context->getData('restrictReviewerFileAccess'),
                'options' => [
                    ['value' => true, 'label' => __('manager.setup.reviewOptions.restrictReviewerFileAccess.description')],
                ],
                'groupId' => self::REVIEW_SETTINGS_GROUP,
            ]))
            ->addField(new FieldOptions('reviewerAccessKeysEnabled', [
                'label' => __('manager.setup.reviewOptions.reviewerAccessKeysEnabled'),
                'description' => __('manager.setup.reviewOptions.reviewerAccessKeysEnabled.description'),
                'type' => 'checkbox',
                'value' => $context->getData('reviewerAccessKeysEnabled'),
                'options' => [
                    ['value' => true, 'label' => __('manager.setup.reviewOptions.reviewerAccessKeysEnabled.label')],
                ],
                'groupId' => self::REVIEW_SETTINGS_GROUP,
            ]))
            ->addField(new FieldText('numWeeksPerResponse', [
                'label' => __('manager.setup.reviewOptions.defaultReviewResponseTime'),
                'description' => __('manager.setup.reviewOptions.numWeeksPerResponse'),
                'value' => $context->getData('numWeeksPerResponse'),
                'size' => 'small',
                'groupId' => self::REVIEW_SETTINGS_GROUP,
            ]))
            ->addField(new FieldText('numWeeksPerReview', [
                'label' => __('manager.setup.reviewOptions.defaultReviewCompletionTime'),
                'description' => __('manager.setup.reviewOptions.numWeeksPerReview'),
                'value' => $context->getData('numWeeksPerReview'),
                'size' => 'small',
                'groupId' => self::REVIEW_SETTINGS_GROUP,
            ]))
            ->addField(new FieldText('numReviewersPerSubmission', [
                'label' => __('manager.setup.reviewOptions.numReviewersPerSubmission'),
                'description' => __('manager.setup.reviewOptions.numReviewersPerSubmission.description'),
                'value' => $context->getData('numReviewersPerSubmission'),
                'size' => 'small',
                'groupId' => self::REVIEW_SETTINGS_GROUP,
            ]));

        return $this;
    }

    /**
     * Add the review reminder control fields
     */
    protected function addReminderFields(Context $context): static
    {
        $this
            ->addGroup([
                'id' => self::REVIEW_REMINDER_GROUP,
            ])
            ->addField(new FieldHTML('reminderForReview', [
                'label' => __('manager.setup.reviewOptions.reminders'),
                'description' => __('manager.setup.reviewOptions.reminders.description'),
                'groupId' => self::REVIEW_REMINDER_GROUP,
            ]))
            ->addField(new FieldSlider('numDaysBeforeReviewResponseReminderDue', [
                'label' => __('manager.setup.reviewOptions.reminders.response.before'),
                'value' => $context->getData('numDaysBeforeReviewResponseReminderDue'),
                'min' => static::MIN_REMINDER_NOTIFICATION_SEND_IN_DAYS,
                'max' => static::MAX_REMINDER_NOTIFICATION_SEND_IN_DAYS,
                'minLabel' => __('manager.setup.reviewOptions.reminders.min.label'),
                'valueLabel' => __('manager.setup.reviewOptions.reminders.label.before.days'),
                'valueLabelMin' => __('manager.setup.reviewOptions.reminders.disbale.label'),
                'groupId' => self::REVIEW_REMINDER_GROUP,
            ]))
            ->addField(new FieldSlider('numDaysAfterReviewResponseReminderDue', [
                'label' => __('manager.setup.reviewOptions.reminders.response.after'),
                'value' => $context->getData('numDaysAfterReviewResponseReminderDue'),
                'min' => static::MIN_REMINDER_NOTIFICATION_SEND_IN_DAYS,
                'max' => static::MAX_REMINDER_NOTIFICATION_SEND_IN_DAYS,
                'minLabel' => __('manager.setup.reviewOptions.reminders.min.label'),
                'valueLabel' => __('manager.setup.reviewOptions.reminders.label.after.days'),
                'valueLabelMin' => __('manager.setup.reviewOptions.reminders.disbale.label'),
                'groupId' => self::REVIEW_REMINDER_GROUP,
            ]))
            ->addField(new FieldSlider('numDaysBeforeReviewSubmitReminderDue', [
                'label' => __('manager.setup.reviewOptions.reminders.submit.before'),
                'value' => $context->getData('numDaysBeforeReviewSubmitReminderDue'),
                'min' => static::MIN_REMINDER_NOTIFICATION_SEND_IN_DAYS,
                'max' => static::MAX_REMINDER_NOTIFICATION_SEND_IN_DAYS,
                'minLabel' => __('manager.setup.reviewOptions.reminders.min.label'),
                'valueLabel' => __('manager.setup.reviewOptions.reminders.label.before.days'),
                'valueLabelMin' => __('manager.setup.reviewOptions.reminders.disbale.label'),
                'groupId' => self::REVIEW_REMINDER_GROUP,
            ]))
            ->addField(new FieldSlider('numDaysAfterReviewSubmitReminderDue', [
                'label' => __('manager.setup.reviewOptions.reminders.submit.after'),
                'value' => $context->getData('numDaysAfterReviewSubmitReminderDue'),
                'min' => static::MIN_REMINDER_NOTIFICATION_SEND_IN_DAYS,
                'max' => static::MAX_REMINDER_NOTIFICATION_SEND_IN_DAYS,
                'minLabel' => __('manager.setup.reviewOptions.reminders.min.label'),
                'valueLabel' => __('manager.setup.reviewOptions.reminders.label.after.days'),
                'valueLabelMin' => __('manager.setup.reviewOptions.reminders.disbale.label'),
                'groupId' => self::REVIEW_REMINDER_GROUP,
            ]));
        
        return $this;
    }
}
