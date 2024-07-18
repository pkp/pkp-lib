<?php
/**
 * @file classes/components/form/publication/PKPPublicationIdentifiersForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPPublicationIdentifiersForm
 *
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for setting a publication's public identifiers (DOI, etc)
 */

namespace PKP\components\forms\publication;

use APP\publication\Publication;
use PKP\components\forms\FormComponent;

class PKPPublicationIdentifiersForm extends FormComponent
{
    public const FORM_PUBLICATION_IDENTIFIERS = 'publicationIdentifiers';
    public $id = self::FORM_PUBLICATION_IDENTIFIERS;

    /** @copydoc FormComponent::$method */
    public $method = 'PUT';

    /** @var Publication The publication this form is for */
    public $publication;

    /** @var \PKP\context\Context The journal/press this publication exists in */
    public $submissionContext;

    /**
     * Constructor
     *
     * @param string $action URL to submit the form to
     * @param array $locales Supported locales
     * @param Publication $publication The publication to change settings for
     * @param \PKP\context\Context $submissionContext The journal/press this publication exists in
     */
    public function __construct($action, $locales, $publication, $submissionContext)
    {
        $this->action = $action;
        $this->locales = $locales;
        $this->publication = $publication;
        $this->submissionContext = $submissionContext;
    }
}
