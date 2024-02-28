<?php
/**
 * @file classes/components/form/context/PKPReviewSetupForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
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

use PKP\components\forms\FieldCheckbox;
use PKP\components\forms\FieldHTML;
use PKP\context\Context;
use PKP\components\forms\FieldOptions;
use PKP\components\forms\FieldRangeSlider;
use PKP\components\forms\FieldText;
use PKP\components\forms\FormComponent;
use PKP\config\Config;
use PKP\submission\reviewAssignment\ReviewAssignment;

class PKPReviewSetupForm extends FormComponent
{
    public const FORM_REVIEW_SETUP = 'reviewSetup';
    public $id = self::FORM_REVIEW_SETUP;
    public $method = 'PUT';

    protected const REVIEW_SETTINGS_GROUP = 'reviewSettingsGroup';
    protected const REVIEW_REMINDER_GROUP = 'reviewReminderGroup';

    public const MIN_REMINDER_NOTIFICATION_SEND_IN_DAYS = 1;
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
            ->addReminderFields($context)
            ->addReminderDisbaleNoticeField($context);
    }

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

    protected function addReminderFields(Context $context): static
    {
        if (!Config::getVar('general', 'scheduled_tasks')) {
            return $this;
        }

        $this
            ->addGroup([
                'id' => self::REVIEW_REMINDER_GROUP
            ])
            ->addField(new FieldHTML('reminderForReview', [
                'label' => __('manager.setup.reviewOptions.reminders'),
                'description' => __('manager.setup.reviewOptions.reminders.description'),
                'groupId' => self::REVIEW_REMINDER_GROUP,
            ]))
            ->addField(new FieldHTML('reviewRequestResponseReminder', [
                'label' => __('manager.setup.reviewOptions.reminders.response'),
                'groupId' => self::REVIEW_REMINDER_GROUP,
            ]))
            ->addField(new FieldRangeSlider('numDaysBeforeReviewResponseReminderDue', [
                'label' => __('manager.setup.reviewOptions.reminders.description.before'),
                'value' => $context->getData('numDaysBeforeReviewResponseReminderDue'),
                'min' => static::MIN_REMINDER_NOTIFICATION_SEND_IN_DAYS,
                'max' => static::MAX_REMINDER_NOTIFICATION_SEND_IN_DAYS,
                'size' => 'normal',
                'updateLabel' => __('manager.setup.reviewOptions.reminders.description.before.days'),
                'groupId' => self::REVIEW_REMINDER_GROUP,
            ]))
            ->addField(new FieldCheckbox('enableBeforeReviewResponseReminder', [
                'label' => __('manager.setup.reviewOptions.reminders.disable'),
                'value' => (bool)$context->getData('enableBeforeReviewResponseReminder'),
                'viewAsButton' => true,
                'checkedLabel' => __('manager.setup.reviewOptions.reminders.disable'),
                'uncheckedLabel' => __('manager.setup.reviewOptions.reminders.enable'),
                'groupId' => self::REVIEW_REMINDER_GROUP,
            ]))
            ->addField(new FieldRangeSlider('numDaysAfterReviewResponseReminderDue', [
                'label' => __('manager.setup.reviewOptions.reminders.description.after'),
                'value' => $context->getData('numDaysAfterReviewResponseReminderDue'),
                'min' => static::MIN_REMINDER_NOTIFICATION_SEND_IN_DAYS,
                'max' => static::MAX_REMINDER_NOTIFICATION_SEND_IN_DAYS,
                'size' => 'normal',
                'updateLabel' => __('manager.setup.reviewOptions.reminders.description.after.days'),
                'groupId' => self::REVIEW_REMINDER_GROUP,
            ]))
            ->addField(new FieldCheckbox('enableAfterReviewResponseReminder', [
                'label' => __('manager.setup.reviewOptions.reminders.disable'),
                'value' => (bool)$context->getData('enableAfterReviewResponseReminder'),
                'viewAsButton' => true,
                'checkedLabel' => __('manager.setup.reviewOptions.reminders.disable'),
                'uncheckedLabel' => __('manager.setup.reviewOptions.reminders.enable'),
                'groupId' => self::REVIEW_REMINDER_GROUP,
            ]))
            ->addField(new FieldHTML('submissionReviewResponseReminder', [
                'label' => __('manager.setup.reviewOptions.reminders.submit'),
                'groupId' => self::REVIEW_REMINDER_GROUP,
            ]))
            ->addField(new FieldRangeSlider('numDaysBeforeReviewSubmitReminderDue', [
                'label' => __('manager.setup.reviewOptions.reminders.description.before'),
                'value' => $context->getData('numDaysBeforeReviewSubmitReminderDue'),
                'min' => static::MIN_REMINDER_NOTIFICATION_SEND_IN_DAYS,
                'max' => static::MAX_REMINDER_NOTIFICATION_SEND_IN_DAYS,
                'size' => 'normal',
                'updateLabel' => __('manager.setup.reviewOptions.reminders.description.before.days'),
                'groupId' => self::REVIEW_REMINDER_GROUP,
            ]))
            ->addField(new FieldCheckbox('enableBeforeReviewSubmitReminder', [
                'label' => __('manager.setup.reviewOptions.reminders.disable'),
                'value' => (bool)$context->getData('enableBeforeReviewSubmitReminder'),
                'viewAsButton' => true,
                'checkedLabel' => __('manager.setup.reviewOptions.reminders.disable'),
                'uncheckedLabel' => __('manager.setup.reviewOptions.reminders.enable'),
                'groupId' => self::REVIEW_REMINDER_GROUP,
            ]))
            ->addField(new FieldRangeSlider('numDaysAfterReviewSubmitReminderDue', [
                'label' => __('manager.setup.reviewOptions.reminders.description.after'),
                'value' => $context->getData('numDaysAfterReviewSubmitReminderDue'),
                'min' => static::MIN_REMINDER_NOTIFICATION_SEND_IN_DAYS,
                'max' => static::MAX_REMINDER_NOTIFICATION_SEND_IN_DAYS,
                'size' => 'normal',
                'updateLabel' => __('manager.setup.reviewOptions.reminders.description.after.days'),
                'groupId' => self::REVIEW_REMINDER_GROUP,
            ]))
            ->addField(new FieldCheckbox('enableAfterReviewSubmitReminder', [
                'label' => __('manager.setup.reviewOptions.reminders.disable'),
                'value' => (bool)$context->getData('enableAfterReviewSubmitReminder'),
                'viewAsButton' => true,
                'checkedLabel' => __('manager.setup.reviewOptions.reminders.disable'),
                'uncheckedLabel' => __('manager.setup.reviewOptions.reminders.enable'),
                'groupId' => self::REVIEW_REMINDER_GROUP,
            ]));
        
        return $this;
    }

    protected function addReminderDisbaleNoticeField(Context $context): static
    {
        if (Config::getVar('general', 'scheduled_tasks')) {
            return $this;
        }

        $this
            ->addGroup([
                'id' => self::REVIEW_REMINDER_GROUP
            ])
            ->addField(new FieldHTML('reviewRemindersDisabled', [
                'label' => __('manager.setup.reviewOptions.automatedReminders'),
                'description' => __('manager.setup.reviewOptions.automatedRemindersDisabled'),
                'groupId' => self::REVIEW_REMINDER_GROUP,
            ]));

        return $this;
    }
}
