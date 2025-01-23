<?php
/**
 * @file classes/components/form/context/PKPReviewGuidanceForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPReviewGuidanceForm
 *
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for configuring the guidance a reviewer should receive.
 */

namespace PKP\components\forms\context;

use PKP\components\forms\FieldRichTextarea;
use PKP\components\forms\FieldShowEnsuringLink;
use PKP\components\forms\FormComponent;

class PKPReviewGuidanceForm extends FormComponent
{
    public const FORM_REVIEW_GUIDANCE = 'reviewerGuidance';
    public $id = self::FORM_REVIEW_GUIDANCE;
    public $method = 'PUT';

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

        $this->addField(new FieldRichTextarea('reviewGuidelines', [
            'label' => __('manager.setup.reviewGuidelines'),
            'isMultilingual' => true,
            'value' => $context->getData('reviewGuidelines'),
            'toolbar' => 'bold italic superscript subscript | link | blockquote bullist numlist',
            'plugins' => ['link','lists'],
        ]))
            ->addField(new FieldRichTextarea('competingInterests', [
                'label' => __('manager.setup.competingInterests'),
                'isMultilingual' => true,
                'value' => $context->getData('competingInterests'),
                'toolbar' => 'bold italic superscript subscript | link | blockquote bullist numlist',
                'plugins' => ['link','lists'],
            ]))
            ->addField(new FieldShowEnsuringLink('showEnsuringLink', [
                'options' => [
                    ['value' => true, 'label' => __('manager.setup.reviewOptions.showAnonymousReviewLink')],
                ],
                'value' => $context->getData('showEnsuringLink'),
            ]));
    }
}
