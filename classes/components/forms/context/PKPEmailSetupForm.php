<?php
/**
 * @file classes/components/form/context/PKPEmailSetupForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPEmailSetupForm
 *
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for configuring a context's email settings.
 */

namespace PKP\components\forms\context;

use APP\core\Application;
use APP\mail\variables\ContextEmailVariable;
use Illuminate\Support\Arr;
use PKP\components\forms\FieldHTML;
use PKP\components\forms\FieldOptions;
use PKP\components\forms\FieldPreparedContent;
use PKP\components\forms\FieldRadioInput;
use PKP\components\forms\FieldText;
use PKP\components\forms\FormComponent;
use PKP\config\Config;
use PKP\context\Context;

class PKPEmailSetupForm extends FormComponent
{
    public const GROUP_EMAIL_TEMPLATES = 'emailTemplates';
    public const GROUP_NEW_SUBMISSION = 'newSubmission';
    public const GROUP_EDITORIAL_DECISIONS = 'decisions';
    public const GROUP_EDITORS = 'editors';
    public const GROUP_ADVANCED = 'advanced';
    public const FIELD_SUBMISSION_ACK = 'submissionAcknowledgement';

    public $id = 'emailSetup';
    public $method = 'PUT';
    public Context $context;


    public function __construct(string $action, array $locales, Context $context)
    {
        $this->action = $action;
        $this->locales = $locales;
        $this->context = $context;

        $this->addGroup([
            'id' => self::GROUP_EMAIL_TEMPLATES,
            'label' => __('manager.manageEmails'),
            'description' => __('manager.manageEmails.description'),
        ])
            ->addEmailTemplatesField()
            ->addSignatureField()
            ->addGroup([
                'id' => self::GROUP_NEW_SUBMISSION,
                'label' => __('manager.newSubmission'),
                'description' => __('manager.newSubmission.description'),
            ])
            ->addSubmissionAcknowledgementField()
            ->addCopySubmissionAckPrimaryContactField()
            ->addCopySubmissionAckAddress()
            ->addGroup([
                'id' => self::GROUP_EDITORIAL_DECISIONS,
                'label' => __('manager.editorialDecisions'),
                'description' => __('manager.editorialDecisions.description'),
            ])
            ->addNotifyAllAuthorsField()
            ->addGroup([
                'id' => self::GROUP_EDITORS,
                'label' => __('manager.forEditors'),
                'description' => __('manager.forEditors.description')
            ])
            ->addStatisticsReportField()
            ->addGroup([
                'id' => self::GROUP_ADVANCED,
                'label' => __('manager.setup.advanced'),
            ])
            ->addEnveloperSenderField();
    }

    protected function addEmailTemplatesField(): self
    {
        $manageEmailsUrl = Application::get()->getRequest()->getDispatcher()->url(
            Application::get()->getRequest(),
            Application::ROUTE_PAGE,
            $this->context->getPath(),
            'management',
            'settings',
            ['manageEmails']
        );
        return $this->addField(new FieldHTML('emailTemplates', [
            'label' => __('manager.emails.emailTemplates'),
            'description' => __('manager.manageEmailTemplates.description', ['url' => $manageEmailsUrl]),
            'groupId' => self::GROUP_EMAIL_TEMPLATES,
        ]));
    }

    protected function addSignatureField(): self
    {
        return $this->addField(new FieldPreparedContent('emailSignature', [
            'label' => __('manager.setup.emailSignature'),
            'description' => __('manager.setup.emailSignature.description'),
            'value' => $this->context->getData('emailSignature'),
            'preparedContent' => array_values(
                Arr::sort(
                    Arr::map(
                        Arr::except(ContextEmailVariable::descriptions(), ContextEmailVariable::CONTEXT_SIGNATURE),
                        function ($description, $key) {
                            return [
                                'key' => $key,
                                'description' => $description,
                                'value' => '{$' . $key . '}'
                            ];
                        }
                    )
                )
            ),
            'groupId' => self::GROUP_EMAIL_TEMPLATES,
        ]));
    }

    /**
     * Add the submission ack field
     */
    protected function addSubmissionAcknowledgementField(): self
    {
        return $this->addField(new FieldOptions(self::FIELD_SUBMISSION_ACK, [
            'label' => __('mailable.submissionAck.name'),
            'description' => __('manager.submissionAck.description'),
            'type' => 'radio',
            'options' => [
                ['value' => Context::SUBMISSION_ACKNOWLEDGEMENT_ALL_AUTHORS, 'label' => __('manager.submissionAck.allAuthors')],
                ['value' => Context::SUBMISSION_ACKNOWLEDGEMENT_SUBMITTING_AUTHOR, 'label' => __('manager.submissionAck.submittingAuthor')],
                ['value' => Context::SUBMISSION_ACKNOWLEDGEMENT_OFF, 'label' => __('manager.submissionAck.off')],
            ],
            'value' => $this->context->getData(self::FIELD_SUBMISSION_ACK),
            'groupId' => self::GROUP_NEW_SUBMISSION,
        ]));
    }

    /**
     * Add the copy submission ack primary contact field
     */
    protected function addCopySubmissionAckPrimaryContactField(): self
    {
        $contactEmail = $this->context->getData('contactEmail');

        if (!empty($contactEmail)) {
            return $this->addField(new FieldRadioInput('copySubmissionAckPrimaryContact', [
                'label' => __('manager.setup.notifications.copySubmissionAckPrimaryContact'),
                'description' => __('manager.setup.notifications.copySubmissionAckPrimaryContact.description'),
                'options' => [
                    ['value' => true, 'label' => __('manager.setup.notifications.copySubmissionAckPrimaryContact.enabled', ['email' => $contactEmail])],
                    ['value' => false, 'label' => __('manager.setup.notifications.copySubmissionAckPrimaryContact.disabled')],
                ],
                'value' => $this->context->getData('copySubmissionAckPrimaryContact'),
                'groupId' => self::GROUP_NEW_SUBMISSION,
                'showWhen' => self::FIELD_SUBMISSION_ACK,
            ]));
        }

        $request = Application::get()->getRequest();

        $pageUrl = $request->getDispatcher()
            ->url($request, Application::ROUTE_PAGE, null, 'management', 'settings', ['context'], null, 'contact');

        return $this->addField(new FieldHTML('copySubmissionAckPrimaryContact', [
            'label' => __('manager.setup.notifications.copySubmissionAckPrimaryContact'),
            'description' => __('manager.setup.notifications.copySubmissionAckPrimaryContact.disabled.description', ['url' => $pageUrl]),
            'groupId' => self::GROUP_NEW_SUBMISSION,
            'showWhen' => self::FIELD_SUBMISSION_ACK,
        ]));
    }

    /**
     * Add the field to copy any email address on the submission acknowledgement
     */
    protected function addCopySubmissionAckAddress(): self
    {
        return $this->addField(new FieldText('copySubmissionAckAddress', [
            'label' => __('manager.setup.notifications.copySubmissionAckAddress'),
            'description' => __('manager.setup.notifications.copySubmissionAckAddress.description'),
            'size' => 'large',
            'value' => $this->context->getData('copySubmissionAckAddress'),
            'groupId' => self::GROUP_NEW_SUBMISSION,
            'showWhen' => self::FIELD_SUBMISSION_ACK,
        ]));
    }

    /**
     * Add the field to notify all authors when an editorial decision is recorded
     */
    protected function addNotifyAllAuthorsField(): self
    {
        return $this->addField(new FieldOptions('notifyAllAuthors', [
            'label' => __('manager.setup.notifyAllAuthors'),
            'description' => __('manager.setup.notifyAllAuthors.description'),
            'type' => 'radio',
            'options' => [
                ['value' => true, 'label' => __('manager.setup.notifyAllAuthors.allAuthors')],
                ['value' => false, 'label' => __('manager.setup.notifyAllAuthors.assignedAuthors')],
            ],
            'value' => $this->context->getData('notifyAllAuthors'),
            'groupId' => self::GROUP_EDITORIAL_DECISIONS,
        ]));
    }

    /**
     * Add the field to enable/disable the editorial statistics report email
     */
    protected function addStatisticsReportField(): self
    {
        return $this->addField(new FieldOptions('editorialStatsEmail', [
            'label' => __('manager.editorialStatistics'),
            'description' => __('manager.editorialStatistics.description'),
            'type' => 'radio',
            'options' => [
                ['value' => true, 'label' => __('manager.editorialStatistics.on')],
                ['value' => false, 'label' => __('manager.editorialStatistics.off')],
            ],
            'value' => $this->context->getData('editorialStatsEmail'),
            'groupId' => self::GROUP_EDITORS,
        ]));
    }

    protected function addEnveloperSenderField(): self
    {
        $canEnvelopeSender = Config::getVar('email', 'allow_envelope_sender');

        if ($canEnvelopeSender) {
            return $this->addField(new FieldText('envelopeSender', [
                'label' => __('manager.setup.emailBounceAddress'),
                'tooltip' => __('manager.setup.emailBounceAddress.description'),
                'value' => $this->context->getData('envelopeSender'),
                'groupId' => self::GROUP_ADVANCED,
            ]));
        }

        return $this->addField(new FieldHTML('envelopeSender', [
            'label' => __('manager.setup.emailBounceAddress'),
            'description' => __('manager.setup.emailBounceAddress.disabled'),
            'groupId' => self::GROUP_ADVANCED,
        ]));
    }
}
