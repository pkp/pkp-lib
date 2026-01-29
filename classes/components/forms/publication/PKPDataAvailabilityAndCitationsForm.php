<?php
/**
 * @file classes/components/form/publication/PKPDataAvailabilityAndCitationsForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPDataAvailabilityAndCitationsForm
 *
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for setting a publication's data citations and data availability statement
 */

namespace PKP\components\forms\publication;

use APP\publication\Publication;
use PKP\components\forms\FieldRichTextarea;
use PKP\components\forms\FormComponent;

class PKPDataAvailabilityAndCitationsForm extends FormComponent
{
    public const FORM_DATA_AVAILABILITY_AND_CITATIONS = 'dataAvailabilityAndCitations';
    public $id = self::FORM_DATA_AVAILABILITY_AND_CITATIONS;
    public $method = 'PUT';

    /**
     * Constructor
     *
     * @param string $action URL to submit the form to
     */
    public function __construct(string $action, array $locales, Publication $publication, bool $dataAvailabilitySetting, bool $isRequired = false)
    {
        $this->action = $action;
        $this->locales = $locales;

        if ($dataAvailabilitySetting) {
            $this->addField(new FieldRichTextarea('dataAvailability', [
                'label' => __('submission.dataAvailability'),
                'tooltip' => __('manager.setup.metadata.dataAvailability.description'),
                'isMultilingual' => true,
                'value' => $publication->getData('dataAvailability'),
                'isRequired' => $isRequired
            ]));
        }

    }
}
