<?php
/**
 * @file classes/components/form/publication/PKPDataCitationsForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPDataCitationsForm
 *
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for setting a publication's data citations and data availability statement
 */

namespace PKP\components\forms\publication;

use APP\publication\Publication;
use PKP\components\forms\FieldRichTextarea;
use PKP\components\forms\FieldTextarea;
use PKP\components\forms\FormComponent;
use APP\core\Application;
use APP\facades\Repo;
use PKP\controlledVocab\ControlledVocab;
use PKP\components\forms\FieldControlledVocab;
use PKP\components\forms\FieldText;
use PKP\context\Context;

class PKPDataCitationsForm extends FormComponent
{
    public const FORM_DATA_CITATIONS = 'dataCitations';
    public $id = self::FORM_DATA_CITATIONS;
    public $method = 'PUT';

    public bool $isRequired;

    /**
     * Constructor
     *
     * @param string $action URL to submit the form to
     */
    public function __construct(string $action, array $locales, Publication $publication, bool $isRequired = false)
    {
        $this->action = $action;
        $this->locales = $locales;
        $this->isRequired = $isRequired;

        $this->addField(new FieldRichTextarea('dataAvailability', [
            'label' => __('submission.dataAvailability'),
            'tooltip' => __('manager.setup.metadata.dataAvailability.description'),
            'isMultilingual' => true,
            'value' => $publication->getData('dataAvailability'),
        ]));

        $this->addField(new FieldTextarea('dataCitationsRaw', [
            'label' => __('submission.dataCitations'),
            'description' => __('submission.dataCitations.description'),
            'value' => $publication->getData('dataCitationsRaw'),
            'isRequired' => $isRequired
        ]));
    }
}
