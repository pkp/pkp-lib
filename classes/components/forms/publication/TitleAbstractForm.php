<?php
/**
 * @file classes/components/form/publication/TitleAbstractForm.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class TitleAbstractForm
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for setting a publication's title and abstract
 */

namespace PKP\components\forms\publication;

use APP\publication\Publication;
use PKP\components\forms\FieldRichTextarea;
use PKP\components\forms\FieldText;
use PKP\components\forms\FormComponent;

define('FORM_TITLE_ABSTRACT', 'titleAbstract');

abstract class TitleAbstractForm extends FormComponent
{
    public $id = FORM_TITLE_ABSTRACT;
    public $method = 'PUT';
    public $publication;

    /** @var bool Whether or not this form is for the submission wizard */
    public bool $isSubmissionWizard = false;

    /** @var int The abstract word limit for this submission or 0 for no limit */
    public int $abstractWordLimit = 0;

    /** @var bool The abstract word limit for this submission */
    public bool $isAbstractRequired = false;

    public function __construct(string $action, array $locales, Publication $publication, bool $isSubmissionWizard = false)
    {
        $this->action = $action;
        $this->locales = $locales;
        $this->publication = $publication;
        $this->isSubmissionWizard = $isSubmissionWizard;

        if (!$this->isSubmissionWizard) {
            $this->addField(new FieldText('prefix', [
                'label' => __('common.prefix'),
                'description' => __('common.prefixAndTitle.tip'),
                'size' => 'small',
                'isMultilingual' => true,
                'value' => $publication->getData('prefix'),
            ]));
        }
        $this->addField(new FieldText('title', [
            'label' => __('common.title'),
            'size' => 'large',
            'isMultilingual' => true,
            'isRequired' => true,
            'value' => $publication->getData('title'),
        ]));
        if (!$this->isSubmissionWizard) {
            $this->addField(new FieldText('subtitle', [
                'label' => __('common.subtitle'),
                'size' => 'large',
                'isMultilingual' => true,
                'value' => $publication->getData('subtitle'),
            ]));
        }
        $this->addField(new FieldRichTextarea('abstract', [
            'label' => __('common.abstract'),
            'isMultilingual' => true,
            'isRequired' => $this->isAbstractRequired,
            'size' => 'large',
            'wordLimit' => $this->abstractWordLimit,
            'value' => $publication->getData('abstract'),
        ]));
    }
}
