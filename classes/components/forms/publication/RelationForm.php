<?php
/**
 * @file classes/components/form/publication/RelationForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RelationForm
 *
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for setting a publication's relation
 */

namespace APP\components\forms\publication;

use APP\publication\Publication;
use PKP\components\forms\FieldOptions;
use PKP\components\forms\FieldText;

use PKP\components\forms\FormComponent;

define('FORM_ID_RELATION', 'relation');

class RelationForm extends FormComponent
{
    /** @copydoc FormComponent::$id */
    public $id = FORM_ID_RELATION;

    /** @copydoc FormComponent::$method */
    public $method = 'PUT';

    /**
     * Constructor
     *
     * @param string $action URL to submit the form to
     * @param Publication $publication The publication to change settings for
     */
    public function __construct($action, $publication)
    {
        $this->action = $action;
        $this->locales = [];

        // Relation options
        $this->addField(new FieldOptions('relationStatus', [
            'label' => __('publication.relation.label'),
            'type' => 'radio',
            'value' => (int) $publication->getData('relationStatus'),
            'options' => [
                [
                    'value' => Publication::PUBLICATION_RELATION_NONE,
                    'label' => __('publication.relation.none')
                ],
                [
                    'value' => Publication::PUBLICATION_RELATION_PUBLISHED,
                    'label' => __('publication.relation.published')
                ]
            ],
        ]))
            ->addField(new FieldText('vorDoi', [
                'label' => __('publication.relation.vorDoi'),
                'value' => $publication->getData('vorDoi'),
                'size' => 'large',
                'showWhen' => ['relationStatus', Publication::PUBLICATION_RELATION_PUBLISHED],
            ]));
    }
}
