<?php

/**
 * @file classes/components/form/citation/PKPCitationEditForm.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2000-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPCitationEditForm
 *
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for setting a publication's single citation
 */

namespace PKP\components\forms\citation;

use PKP\components\forms\FieldText;
use PKP\components\forms\FormComponent;

class PKPCitationEditForm extends FormComponent
{
    public const FORM_CITATION = 'citation';
    public $id = self::FORM_CITATION;
    public $method = 'PUT';
    public bool $isRequired;

    /**
     * Constructor
     *
     * @param string $action URL to submit the form to
     */
    public function __construct(string $action, ?int $citationId, bool $isRequired = false)
    {
        $this->action = $action;
        $this->isRequired = $isRequired;

        // Article Information
        foreach (['doi', 'url', 'urn', 'arxiv', 'handle'] as $key) {
            $this->addField(new FieldText($key, [
                'label' => __('submission.citations.structured.label.' . $key),
                'description' => '',
                'value' => null,
                'isRequired' => $isRequired
            ]));
        }

        $this->addField(new FieldText('title', [
            'label' => __('submission.citations.structured.label.title'),
            'description' => '',
            'value' => null,
            'isRequired' => $isRequired
        ]));

        // Author Information
        $this->addField(new FieldCitationAuthors('authors', [
            'label' => __('submission.citations.structured.label.authors'),
            'description' => '',
            'value' => null,
            'isRequired' => $isRequired
        ]));

        // Journal Information
        $this->addField(new FieldText('sourceName', [
            'label' => __('submission.citations.structured.label.sourceName'),
            'description' => '',
            'value' => null,
            'isRequired' => $isRequired
        ]));
        $this->addField(new FieldText('sourceIssn', [
            'label' => __('submission.citations.structured.label.sourceIssn'),
            'description' => '',
            'value' => null,
            'isRequired' => $isRequired
        ]));
        $this->addField(new FieldText('sourceHost', [
            'label' => __('submission.citations.structured.label.sourceHost'),
            'description' => '',
            'value' => null,
            'isRequired' => $isRequired
        ]));
        $this->addField(new FieldText('sourceType', [
            'label' => __('submission.citations.structured.label.sourceType'),
            'description' => '',
            'value' => null,
            'isRequired' => $isRequired
        ]));
        $this->addField(new FieldText('date', [
            'label' => __('submission.citations.structured.label.date'),
            'description' => '',
            'value' => null,
            'isRequired' => $isRequired
        ]));
        $this->addField(new FieldText('type', [
            'label' => __('submission.citations.structured.label.type'),
            'description' => '',
            'value' => null,
            'isRequired' => $isRequired
        ]));
        $this->addField(new FieldText('volume', [
            'label' => __('submission.citations.structured.label.volume'),
            'description' => '',
            'value' => null,
            'isRequired' => $isRequired
        ]));
        $this->addField(new FieldText('issue', [
            'label' => __('submission.citations.structured.label.issue'),
            'description' => '',
            'value' => null,
            'isRequired' => $isRequired
        ]));
        $this->addField(new FieldText('firstPage', [
            'label' => __('submission.citations.structured.label.firstPage'),
            'description' => '',
            'value' => null,
            'isRequired' => $isRequired
        ]));
        $this->addField(new FieldText('lastPage', [
            'label' => __('submission.citations.structured.label.lastPage'),
            'description' => '',
            'value' => null,
            'isRequired' => $isRequired
        ]));
    }
}
