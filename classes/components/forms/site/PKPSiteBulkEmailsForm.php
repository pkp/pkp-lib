<?php
/**
 * @file classes/components/form/site/PKPSiteBulkEmailsForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPSiteBulkEmailsForm
 *
 * @ingroup classes_controllers_form
 *
 * @brief A form for enabling the bulk email features.
 */

namespace PKP\components\forms\site;

use APP\core\Application;
use PKP\components\forms\FieldOptions;
use PKP\components\forms\FormComponent;

class PKPSiteBulkEmailsForm extends FormComponent
{
    public const FORM_SITE_BULK_EMAILS = 'bulkEmails';
    public $id = self::FORM_SITE_BULK_EMAILS;
    public $method = 'PUT';

    /**
     * Constructor
     *
     * @param string $action URL to submit the form to
     * @param \PKP\site\Site $site
     * @param array $contexts List of context summary objects. See PKPContextQueryBuilder::getManySummary()
     */
    public function __construct($action, $site, $contexts)
    {
        $this->action = $action;

        $request = Application::get()->getRequest();
        $hostedContextsUrl = $request->getDispatcher()->url($request, Application::ROUTE_PAGE, null, 'admin', 'contexts');

        $options = [];
        foreach ($contexts as $context) {
            $options[] = [
                'value' => $context->id,
                'label' => htmlspecialchars($context->name),
            ];
        }

        $this->addField(new FieldOptions('enableBulkEmails', [
            'label' => __('admin.settings.enableBulkEmails.label'),
            'description' => __('admin.settings.enableBulkEmails.description', ['hostedContextsUrl' => $hostedContextsUrl]),
            'value' => (array) $site->getData('enableBulkEmails'),
            'options' => $options,
        ]));
    }
}
