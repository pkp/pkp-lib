<?php
/**
 * @file classes/components/form/publication/TitleAbstractForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class TitleAbstractForm
 *
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for setting a publication's title and abstract
 */

namespace PKP\components\forms\publication;

use APP\publication\Publication;
use PKP\components\forms\FieldRichText;
use PKP\components\forms\FieldRichTextarea;
use PKP\components\forms\FieldText;
use PKP\components\forms\FormComponent;

define('FORM_TITLE_ABSTRACT', 'titleAbstract');

class TitleAbstractForm extends FormComponent
{
    public $id = FORM_TITLE_ABSTRACT;
    public $method = 'PUT';
    public $publication;
    public int $abstractWordLimit;
    public bool $isAbstractRequired;

    /**
     * Constructor
     *
     * @param int $abstractWordLimit The abstract word limit for this submission or 0 for no limit
     * @param bool $isAbstractRequired Is the abstract required?
     */
    public function __construct(
        string $action,
        array $locales,
        Publication $publication,
        int $abstractWordLimit = 0,
        bool $isAbstractRequired = false
    ) {
        $this->action = $action;
        $this->locales = $locales;
        $this->publication = $publication;
        $this->abstractWordLimit = $abstractWordLimit;
        $this->isAbstractRequired = $isAbstractRequired;

        $this->addField(new FieldText('prefix', [
            'label' => __('common.prefix'),
            'description' => __('common.prefixAndTitle.tip'),
            'size' => 'small',
            'isMultilingual' => true,
            'value' => $publication->getData('prefix'),
        ]))
            ->addField(new FieldRichText('title', [
                'label' => __('common.title'),
                'isMultilingual' => true,
                'isRequired' => true,
                'value' => $publication->getData('title'),
            ]))
            ->addField(new FieldRichText('subtitle', [
                'label' => __('common.subtitle'),
                'isMultilingual' => true,
                'value' => $publication->getData('subtitle'),
            ]))
            ->addField(new FieldRichTextarea('abstract', [
                'label' => __('common.abstract'),
                'isMultilingual' => true,
                'isRequired' => $this->isAbstractRequired,
                'size' => 'large',
                'wordLimit' => $this->abstractWordLimit,
                'value' => $publication->getData('abstract'),
            ]));
    }
}
