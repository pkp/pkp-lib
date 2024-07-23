<?php
/**
 * @file classes/components/form/submission/SubmissionGuidanceSettings.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionGuidanceSettings
 *
 * @ingroup classes_controllers_form
 *
 * @brief A form for the submission wizard instruction settings.
 */

namespace PKP\components\forms\submission;

use APP\core\Application;
use PKP\components\forms\FieldRichTextarea;
use PKP\components\forms\FormComponent;
use PKP\context\Context;

class SubmissionGuidanceSettings extends FormComponent
{
    public const FORM_METADATA = 'metadata';
    public $id = 'submissionGuidanceSettings';
    public $method = 'PUT';
    public Context $context;

    public function __construct(string $action, array $locales, Context $context)
    {
        $this->action = $action;
        $this->locales = $locales;
        $this->context = $context;

        $submissionUrl = Application::get()->getRequest()->getDispatcher()->url(
            Application::get()->getRequest(),
            Application::ROUTE_PAGE,
            $context->getPath(),
            'about',
            'submissions'
        );

        $this->addField(new FieldRichTextarea('authorGuidelines', [
            'label' => __('manager.setup.authorGuidelines'),
            'description' => __('manager.setup.authorGuidelines.description', ['url' => $submissionUrl]),
            'isMultilingual' => true,
            'value' => $context->getData('authorGuidelines'),
            'toolbar' => 'bold italic superscript subscript | link | blockquote bullist numlist',
            'plugins' => 'paste,link,lists',
        ]))
            ->addField(new FieldRichTextarea('beginSubmissionHelp', [
                'label' => __('submission.wizard.beforeStart'),
                'description' => __('manager.setup.workflow.beginSubmissionHelp.description'),
                'isMultilingual' => true,
                'value' => $context->getData('beginSubmissionHelp'),
                'toolbar' => 'bold italic superscript subscript | link | blockquote bullist numlist',
                'plugins' => 'paste,link,lists',
            ]))
            ->addField(new FieldRichTextarea('submissionChecklist', [
                'label' => __('manager.setup.submissionPreparationChecklist'),
                'description' => __('manager.setup.submissionPreparationChecklistDescription'),
                'isMultilingual' => true,
                'value' => $context->getData('submissionChecklist'),
                'toolbar' => 'bold italic superscript subscript | link | blockquote bullist numlist',
                'plugins' => 'paste,link,lists',
            ]))
            ->addField(new FieldRichTextarea('uploadFilesHelp', [
                'label' => __('submission.upload.uploadFiles'),
                'description' => __('manager.setup.workflow.uploadFilesHelp.description'),
                'isMultilingual' => true,
                'value' => $context->getData('uploadFilesHelp'),
                'toolbar' => 'bold italic superscript subscript | link | blockquote bullist numlist',
                'plugins' => 'paste,link,lists',
            ]))
            ->addField(new FieldRichTextarea('contributorsHelp', [
                'label' => __('publication.contributors'),
                'description' => __('manager.setup.workflow.contributorsHelp.description'),
                'isMultilingual' => true,
                'value' => $context->getData('contributorsHelp'),
                'toolbar' => 'bold italic superscript subscript | link | blockquote bullist numlist',
                'plugins' => 'paste,link,lists',
            ]))
            ->addField(new FieldRichTextarea('detailsHelp', [
                'label' => __('common.details'),
                'description' => __('manager.setup.workflow.detailsHelp.description'),
                'isMultilingual' => true,
                'value' => $context->getData('detailsHelp'),
                'toolbar' => 'bold italic superscript subscript | link | blockquote bullist numlist',
                'plugins' => 'paste,link,lists',
            ]))
            ->addField(new FieldRichTextarea('forTheEditorsHelp', [
                'label' => __('submission.forTheEditors'),
                'description' => __('manager.setup.workflow.forTheEditorsHelp.description'),
                'isMultilingual' => true,
                'value' => $context->getData('forTheEditorsHelp'),
                'toolbar' => 'bold italic superscript subscript | link | blockquote bullist numlist',
                'plugins' => 'paste,link,lists',
            ]))
            ->addField(new FieldRichTextarea('reviewHelp', [
                'label' => __('submission.reviewAndSubmit'),
                'description' => __('manager.setup.workflow.reviewHelp.description'),
                'isMultilingual' => true,
                'value' => $context->getData('reviewHelp'),
                'toolbar' => 'bold italic superscript subscript | link | blockquote bullist numlist',
                'plugins' => 'paste,link,lists',
            ]))
            ->addField(new FieldRichTextarea('copyrightNotice', [
                'label' => __('manager.setup.copyrightNotice'),
                'description' => __('manager.setup.copyrightNotice.description', ['url' => $submissionUrl]),
                'isMultilingual' => true,
                'value' => $context->getData('copyrightNotice'),
                'toolbar' => 'bold italic superscript subscript | link | blockquote bullist numlist',
                'plugins' => 'paste,link,lists',
            ]));
    }
}
