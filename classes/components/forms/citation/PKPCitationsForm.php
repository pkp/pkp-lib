<?php

/**
 * @file classes/components/form/citation/PKPCitationsForm.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2000-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPCitationsForm
 *
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for setting a publication's citations
 */

namespace PKP\components\forms\citation;

use APP\facades\Repo;
use APP\publication\Publication;
use PKP\components\forms\FieldOptions;
use PKP\components\forms\FieldTextarea;
use PKP\components\forms\FormComponent;

class PKPCitationsForm extends FormComponent
{
    public const FORM_CITATIONS = 'citations';
    public $id = self::FORM_CITATIONS;
    public $method = 'PUT';
    public bool $isRequired;

    /**
     * Constructor
     *
     * @param string $action URL to submit the form to
     */
    public function __construct(string $action, Publication $publication, bool $isRequired = false)
    {
        $this->action = $action;
        $this->isRequired = $isRequired;

        $this->addField(new FieldTextarea('rawCitations', [
            'label' => __('submission.citations'),
            'description' => __('submission.citations.description'),
            'value' => $publication->getData('rawCitations'),
            'isRequired' => $isRequired
        ]));

        $useStructuredCitations = $publication->getData('useStructuredCitations');
        $citations = Repo::citation()->getByPublicationId($publication->getId());
        $citations = array_map(function ($citation) {
            return Repo::citation()->getSchemaMap()->map($citation);
        }, $citations);

        $this->addField(new FieldOptions('useStructuredCitations', [
            'label' => __('submission.citations.structured'),
            'description' => '',
            'type' => 'checkbox',
            'value' => $useStructuredCitations,
            'options' => [
                [
                    'value' => $useStructuredCitations,
                    'label' => __('submission.citations.structured.label.useStructuredReferences'),
                ]
            ],
            'isRequired' => false
        ]));

        $this->addField(new FieldCitations('citations', [
            'label' => '',
            'description' => __('submission.citations.structured.description'),
            'value' => $citations,
            'isRequired' => $isRequired
        ]));
    }
}
