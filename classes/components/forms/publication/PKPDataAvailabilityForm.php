<?php
/**
 * @file classes/components/form/publication/PKPDataAvailabilityForm.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPDataAvailabilityForm
 *
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for setting a publication's data availability statement
 */

namespace PKP\components\forms\publication;

use APP\publication\Publication;
use PKP\components\forms\FieldRichTextarea;
use PKP\components\forms\FormComponent;

class PKPDataAvailabilityForm extends FormComponent
{
    public const FORM_DATA_AVAILABILITY = 'dataAvailability';
    public $id = self::FORM_DATA_AVAILABILITY;
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
                'description' => __('manager.setup.metadata.dataAvailability.description'),
                'isMultilingual' => true,
                'value' => $publication->getData('dataAvailability'),
                'isRequired' => $isRequired
            ]));
        }

    }
}
