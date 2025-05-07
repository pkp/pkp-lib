<?php
/**
 * @file classes/components/forms/publication/VersionForm.php
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2000-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class VersionForm
 *
 * @ingroup classes_controllers_form
 *
 * @brief A form to create new publication version
 */

namespace PKP\components\forms\publication;

use PKP\context\Context;
use PKP\components\forms\FieldSelect;
use PKP\components\forms\FormComponent;
use PKP\publication\enums\VersionStage;

class VersionForm extends FormComponent
{
    public $id = 'version';
    public $action = FormComponent::ACTION_EMIT;
    public $method = 'POST';

    public function __construct(
        array $locales,
        public Context $context,
    ) {
        $this->locales = $locales;
        $this->showErrorFooter = false;

        $versionStages = [];
        $allVersionStages = VersionStage::getVersions();

        foreach ($allVersionStages as $versionStage) {
            $versionStages[] = [
                'label' => $versionStage->label() . ' (' . $versionStage->value . ')',
                'value' => $versionStage->value,
            ];
        }

        $revisionSignificance = [
            ['value' => 'false', 'label' => __('publication.revisionSignificance.major')],
            ['value' => 'true', 'label' => __('publication.revisionSignificance.minor')],
        ];

        $this->addField(new FieldSelect('sendToVersion', [
            'label' => __('publication.sendToTextEditor.label'),
            'options' => [],
            'size' => 'large',
            'groupId' => 'default',
            'isRequired' => true,
        ]))->addField(new FieldSelect('versionSource', [
            'label' => __('publication.versionSource.create.label'),
            'options' => [],
            'size' => 'large',
            'groupId' => 'default',
            'description' => __('publication.versionSource.create.description'),
        ]))->addField(new FieldSelect('versionStage', [
            'label' => __('publication.versionStage.label'),
            'options' => $versionStages,
            'size' => 'large',
            'groupId' => 'default',
            'description' => __('publication.versionStage.description'),
        ]))->addField(new FieldSelect('versionIsMinor', [
            'label' => __('publication.revisionSignificance.label'),
            'options' => $revisionSignificance,
            'size' => 'large',
            'groupId' => 'default',
            'description' => __('publication.revisionSignificance.description'),
        ]))->addGroup([
            'id' => 'default',
            'pageId' => 'default',
        ])->addPage([
            'id' => 'default',
            'submitButton' => ['label' => __('common.confirm')],
            'cancelButton' => ['label' => __('common.cancel')],
        ]);
    }
}
