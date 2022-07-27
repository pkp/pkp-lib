<?php
/**
 * @file classes/components/form/context/PKPSubmissionsNotifications.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPSubmissionsNotifications
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for configuring a context's submissions notifications settings.
 */

namespace PKP\components\forms\context;

use PKP\components\forms\FieldHTML;
use PKP\components\forms\FieldRadioInput;
use PKP\components\forms\FieldText;
use PKP\components\forms\FormComponent;

define('FORM_SUBMISSIONS_NOTIFICATIONS', 'submissionsNotifications');

class PKPSubmissionsNotificationsForm extends FormComponent
{
    /** @copydoc FormComponent::$id */
    public $id = FORM_SUBMISSIONS_NOTIFICATIONS;

    /** @copydoc FormComponent::$method */
    public $method = 'PUT';

    /**
     * Constructor
     *
     * @param string $action URL to submit the form to
     * @param array $locales Supported locales
     * @param Context $context Journal or Press to change settings for
     */
    public function __construct($action, $locales, $context)
    {
        $this->action = $action;
        $this->locales = $locales;

        $this->buildCopySubmissionAckPrimaryContactField($context);

        $this->addField(new FieldText('copySubmissionAckAddress', [
            'label' => __('manager.setup.notifications.copySubmissionAckAddress'),
            'description' => __('manager.setup.notifications.copySubmissionAckAddress.description'),
            'size' => 'large',
            'value' => $context->getData('copySubmissionAckAddress'),
        ]));
    }

    /**
     * Build the copy submission ack primary contact field
     *
     * @param Context $context Journal or Press to change settings for
     *
     */
    protected function buildCopySubmissionAckPrimaryContactField($context)
    {
        $contactEmail = $context->getData('contactEmail');

        if (!empty($contactEmail)) {
            $this->addField(new FieldRadioInput('copySubmissionAckPrimaryContact', [
                'label' => __('manager.setup.notifications.copySubmissionAckPrimaryContact'),
                'description' => __('manager.setup.notifications.copySubmissionAckPrimaryContact.description'),
                'options' => [
                    ['value' => true, 'label' => __('manager.setup.notifications.copySubmissionAckPrimaryContact.enabled', ['email' => $contactEmail])],
                    ['value' => false, 'label' => __('manager.setup.notifications.copySubmissionAckPrimaryContact.disabled')],
                ],
                'value' => $context->getData('copySubmissionAckPrimaryContact'),
            ]));
            return;
        }

        $request = \Application::get()->getRequest();

        $pageUrl = $request->getDispatcher()
            ->url($request, ROUTE_PAGE, null, 'management', 'settings', 'context', null, 'contact');

        $this->addField(new FieldHTML('copySubmissionAckPrimaryContact', [
            'label' => __('manager.setup.notifications.copySubmissionAckPrimaryContact'),
            'description' => __('manager.setup.notifications.copySubmissionAckPrimaryContact.disabled.description', ['url' => $pageUrl]),
        ]));
    }
}
