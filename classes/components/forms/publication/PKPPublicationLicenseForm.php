<?php
/**
 * @file classes/components/form/publication/PKPPublicationLicenseForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPPublicationLicenseForm
 *
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for setting a publication's license and copyright info
 */

namespace PKP\components\forms\publication;

use APP\core\Application;
use APP\publication\Publication;
use Illuminate\Support\Enumerable;
use PKP\components\forms\FieldText;
use PKP\components\forms\FormComponent;

class PKPPublicationLicenseForm extends FormComponent
{
    public const FORM_PUBLICATION_LICENSE = 'publicationLicense';
    public $id = self::FORM_PUBLICATION_LICENSE;
    public $method = 'PUT';

    /**
     * Constructor
     *
     * @param string $action URL to submit the form to
     * @param array $locales Supported locales
     * @param Publication $publication The publication to change settings for
     * @param \PKP\context\Context $context The publication's context
     */
    public function __construct($action, $locales, $publication, $context)
    {
        $this->action = $action;
        $this->locales = $locales;

        // Get the copyright that will be set on publication based on context settings
        if ($context->getData('copyrightHolderType') === 'author') {
            $copyright = $publication->getAuthorString();
        } elseif ($context->getData('copyrightHolderType') === 'other') {
            $copyright = $context->getLocalizedData('copyrightHolderOther');
        } else {
            $copyright = $context->getLocalizedData('name');
        }

        // Get the name of the context's license setting
        $licenseUrlDescription = '';
        if ($context->getData('licenseUrl')) {
            $licenseOptions = Application::getCCLicenseOptions();
            if (array_key_exists($context->getData('licenseUrl'), $licenseOptions)) {
                $licenseName = __($licenseOptions[$context->getData('licenseUrl')]);
            } else {
                $licenseName = $context->getData('licenseUrl');
            }
            $licenseUrlDescription = __('submission.license.description', [
                'licenseUrl' => $context->getData('licenseUrl'),
                'licenseName' => $licenseName,
            ]);
        }

        $this->addField(new FieldText('copyrightHolder', [
            'label' => __('submission.copyrightHolder'),
            'description' => __('submission.copyrightHolder.description', ['copyright' => htmlspecialchars($copyright)]),
            'isMultilingual' => true,
            'optIntoEdit' => !$publication->getData('copyrightHolder'),
            'optIntoEditLabel' => __('common.override'),
            'value' => $publication->getData('copyrightHolder'),
        ]))
            ->addField(new FieldText('copyrightYear', [
                'label' => __('submission.copyrightYear'),
                'description' => $context->getData('copyrightYearBasis') === 'issue'
                    ? __('publication.copyrightYearBasis.issueDescription')
                    : __('publication.copyrightYearBasis.submissionDescription'),
                'optIntoEdit' => !$publication->getData('copyrightYear'),
                'optIntoEditLabel' => __('common.override'),
                'value' => $publication->getData('copyrightYear'),
            ]))
            ->addField(new FieldText('licenseUrl', [
                'label' => __('submission.licenseURL'),
                'description' => $licenseUrlDescription,
                'optIntoEdit' => $context->getData('licenseUrl') && !$publication->getData('licenseUrl'),
                'optIntoEditLabel' => __('common.override'),
                'value' => $publication->getData('licenseUrl'),
            ]));
    }
}
