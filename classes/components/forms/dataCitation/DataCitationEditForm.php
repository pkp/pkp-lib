<?php

/**
 * @file classes/components/form/citation/DataCitationEditForm.php
 *
 * Copyright (c) 2014-2026 Simon Fraser University
 * Copyright (c) 2000-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DataCitationEditForm
 *
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for setting a publication's single data citation
 */

namespace PKP\components\forms\dataCitation;

use PKP\components\forms\FieldAuthors;
use PKP\components\forms\FieldText;
use PKP\components\forms\FieldSelect;
use PKP\components\forms\FormComponent;

class DataCitationEditForm extends FormComponent
{
    public const FORM_DATA_CITATION_EDIT = 'data_citation';
    public $id = self::FORM_DATA_CITATION_EDIT;
    public $method = 'POST';

    /**
     * Constructor
     *
     * @param string $action URL to submit the form to
     */
    public function __construct(string $action) 
    {
        $this->action = $action;

        $types = ['DOI', 'Accession', 'PURL', 'ARK', 'URI', 'ARXIV', 'ECLI', 'Handle', 'ISSN', 'ISBN', 'PMID', 'PMCID', 'UUID'];
        $identifierTypes = array_map(fn($type) => ['value' => $type, 'label' => $type], $types);

        $types = ['supporting', 'generated', 'analyzed', 'non-analyzed'];
        $relationshipTypes = array_map(
            fn($type) => [
                'value' => $type,
                'label' => __('submission.dataCitations.label.relationshipType.' . $type),
            ],
            $types
        );

        $this->addField(new FieldText('title', [
            'label' => __('submission.dataCitations.label.title'),
            'description' => '',
            'value' => null,
            'isRequired' => true
        ]));

        $this->addField(new FieldSelect('identifierType', [
            'label' => __('submission.dataCitations.label.identifierType'),
            'options' => $identifierTypes
        ]));

        $this->addField(new FieldText('identifier', [
            'label' => __('submission.dataCitations.label.identifier'),
            'description' => '',
            'value' => null
        ]));

        $this->addField(new FieldSelect('relationshipType', [
            'label' => __('submission.dataCitations.label.relationshipType'),
            'options' => $relationshipTypes,
            'isRequired' => true
        ]));

        $this->addField(new FieldText('repository', [
            'label' => __('submission.dataCitations.label.repository'),
            'description' => '',
            'value' => null
        ]));

        $this->addField(new FieldText('year', [
            'label' => __('submission.dataCitations.label.year'),
            'description' => '',
            'value' => null
        ]));

        $this->addField(new FieldAuthors('authors', [
            'label' => __('submission.dataCitations.label.creators'),
            'description' => '',
            'value' => null,
        ]));

        $this->addField(new FieldText('url', [
            'label' => __('submission.dataCitations.label.url'),
            'description' => '',
            'value' => null
        ]));

    }
}
