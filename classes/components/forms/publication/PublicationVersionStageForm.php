<?php
/**
 * @file classes/components/forms/publication/PublishForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PublishForm
 *
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for confirming a publication's issue before publishing.
 *   It may also be used for scheduling a publication in an issue for later
 *   publication.
 */

namespace PKP\components\forms\publication;

use APP\components\forms\FieldSelectIssue;
use APP\facades\Repo;
use APP\publication\Publication;
use APP\submission\Submission;
use PKP\components\forms\FieldHTML;
use PKP\components\forms\FieldOptions;
use PKP\components\forms\FieldRichTextarea;
use PKP\components\forms\FieldSelect;
use PKP\components\forms\FieldText;
use PKP\components\forms\FieldTextarea;
use PKP\components\forms\FormComponent;
use PKP\context\Context;
use PKP\core\Core;
use PKP\core\PKPString;
use PKP\facades\Locale;
use PKP\publication\enums\JavStage;

class PublicationVersionStageForm extends FormComponent
{
    public const FORM_PUBLICATION_VERSION_STAGE = 'publicationVersionStageForm';

    /** @copydoc FormComponent::$id */
    public $id = self::FORM_PUBLICATION_VERSION_STAGE;

    /** @copydoc FormComponent::$method */
    public $method = 'PUT';

    /** @var \APP\publication\Publication */
    public $publication;

    /** @var \APP\journal\Journal */
    public $submissionContext;

    /**
     * Constructor
     *
     * @param string $action URL to submit the form to
     * @param \APP\publication\Publication $publication The publication to change settings for
     * @param \APP\journal\Journal $submissionContext journal or press
     * @param array $requirementErrors A list of pre-publication requirements that are not met.
     */
    public function __construct(string $action, Submission $submission, Publication $publication, Context $context)
    {
        $this->action = $action;
        $this->publication = $publication;

        $this->addGroup([
            'id' => 'publicationStage',
        ]);

        $versioningOptions = array_map(function ($stage) {
            // Map the enum values to readable labels
            return [
                'value' => $stage->value,
                'label' => match ($stage) {
                    JavStage::AUTHOR_ORIGINAL => __('versioning.authorOriginal'),
                    JavStage::ACCEPTED_MANUSCRIPT => __('versioning.acceptedManuscript'),
                    JavStage::SUBMITTED_MANUSCRIPT => __('versioning.submittedManuscript'),
                    JavStage::PROOF => __('versioning.proof'),
                    JavStage::VERSION_OF_RECORD => __('versioning.versionOfRecord'),
                },
            ];
        }, JavStage::cases());

        $defaultVersionStage = JavStage::VERSION_OF_RECORD->value;

        // $this->addGroup([
        //     'id' => 'default',
        //     'pageId' => 'default',
        // ])
        $this->addField(new FieldSelect('versionStage', [
                'label' => __('publication.versionStage'),
                'options' => $versioningOptions,
                'value' => $publication->getData('versionStage') ?: $defaultVersionStage,
                'groupId' => 'publicationStage',
            ]))
            ->addField(new FieldOptions('javVersionIsMinor', [
                'label' => __('publication.versionStage.minorOrMajor'),
                'type' => 'radio',
                'options' => [
                    ['value' => true, 'label' => __('publication.versionStage.minorOrMajor.minor')],
                    ['value' => false, 'label' => __('publication.versionStage.minorOrMajor.major')],
                ],
                'value' => $publication->getData('isMinor') ?: true,
                'groupId' => 'publicationStage',
            ]))
            ->addField(new FieldText('versionDescription', [
                'label' => __('publication.versionStage.description'),
                'isMultilingual' => true,
                'groupId' => 'publicationStage',
            ]));

        // Add cancel button
        $this->addCancel();
    }

    protected function addCancel() {
        $this->addPage([
            'id' => 'default',
            'submitButton' => ['label' => __('common.confirm')],
            'cancelButton' => ['label' => __('common.cancel')],
        ]);
        collect($this->groups)->each(fn ($_, $i) => ($this->groups[$i]['pageId'] = 'default'));
    }
}
