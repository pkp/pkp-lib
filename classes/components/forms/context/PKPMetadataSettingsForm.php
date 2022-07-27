<?php
/**
 * @file classes/components/form/context/PKPMetadataSettingsForm.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPMetadataSettingsForm
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for enabling and configuring types of metadata to
 *  attach to submissions.
 */

namespace PKP\components\forms\context;

use PKP\components\forms\FieldMetadataSetting;
use PKP\components\forms\FormComponent;
use PKP\context\Context;

define('FORM_METADATA_SETTINGS', 'metadataSettings');

class PKPMetadataSettingsForm extends FormComponent
{
    /** @copydoc FormComponent::$id */
    public $id = FORM_METADATA_SETTINGS;

    /** @copydoc FormComponent::$method */
    public $method = 'PUT';

    /**
     * Constructor
     *
     * @param string $action URL to submit the form to
     * @param Context $context Journal or Press to change settings for
     */
    public function __construct($action, $context)
    {
        $this->action = $action;

        $this->addField(new FieldMetadataSetting('coverage', [
            'label' => __('manager.setup.metadata.coverage'),
            'description' => __('manager.setup.metadata.coverage.description'),
            'options' => [
                ['value' => Context::METADATA_ENABLE, 'label' => __('manager.setup.metadata.coverage.enable')]
            ],
            'submissionOptions' => [
                ['value' => Context::METADATA_ENABLE, 'label' => __('manager.setup.metadata.coverage.noRequest')],
                ['value' => Context::METADATA_REQUEST, 'label' => __('manager.setup.metadata.coverage.request')],
                ['value' => Context::METADATA_REQUIRE, 'label' => __('manager.setup.metadata.coverage.require')],
            ],
            'value' => $context->getData('coverage') ? $context->getData('coverage') : Context::METADATA_DISABLE,
        ]))
            ->addField(new FieldMetadataSetting('languages', [
                'label' => __('common.languages'),
                'description' => __('manager.setup.metadata.languages.description'),
                'options' => [
                    ['value' => Context::METADATA_ENABLE, 'label' => __('manager.setup.metadata.languages.enable')]
                ],
                'submissionOptions' => [
                    ['value' => Context::METADATA_ENABLE, 'label' => __('manager.setup.metadata.languages.noRequest')],
                    ['value' => Context::METADATA_REQUEST, 'label' => __('manager.setup.metadata.languages.request')],
                    ['value' => Context::METADATA_REQUIRE, 'label' => __('manager.setup.metadata.languages.require')],
                ],
                'value' => $context->getData('languages') ? $context->getData('languages') : Context::METADATA_DISABLE,
            ]))
            ->addField(new FieldMetadataSetting('rights', [
                'label' => __('submission.rights'),
                'description' => __('manager.setup.metadata.rights.description'),
                'options' => [
                    ['value' => Context::METADATA_ENABLE, 'label' => __('manager.setup.metadata.rights.enable')]
                ],
                'submissionOptions' => [
                    ['value' => Context::METADATA_ENABLE, 'label' => __('manager.setup.metadata.rights.noRequest')],
                    ['value' => Context::METADATA_REQUEST, 'label' => __('manager.setup.metadata.rights.request')],
                    ['value' => Context::METADATA_REQUIRE, 'label' => __('manager.setup.metadata.rights.require')],
                ],
                'value' => $context->getData('rights') ? $context->getData('rights') : Context::METADATA_DISABLE,
            ]))
            ->addField(new FieldMetadataSetting('source', [
                'label' => __('submission.source'),
                'description' => __('manager.setup.metadata.source.description'),
                'options' => [
                    ['value' => Context::METADATA_ENABLE, 'label' => __('manager.setup.metadata.source.enable')]
                ],
                'submissionOptions' => [
                    ['value' => Context::METADATA_ENABLE, 'label' => __('manager.setup.metadata.source.noRequest')],
                    ['value' => Context::METADATA_REQUEST, 'label' => __('manager.setup.metadata.source.request')],
                    ['value' => Context::METADATA_REQUIRE, 'label' => __('manager.setup.metadata.source.require')],
                ],
                'value' => $context->getData('source') ? $context->getData('source') : Context::METADATA_DISABLE,
            ]))
            ->addField(new FieldMetadataSetting('subjects', [
                'label' => __('common.subjects'),
                'description' => __('manager.setup.metadata.subjects.description'),
                'options' => [
                    ['value' => Context::METADATA_ENABLE, 'label' => __('manager.setup.metadata.subjects.enable')]
                ],
                'submissionOptions' => [
                    ['value' => Context::METADATA_ENABLE, 'label' => __('manager.setup.metadata.subjects.noRequest')],
                    ['value' => Context::METADATA_REQUEST, 'label' => __('manager.setup.metadata.subjects.request')],
                    ['value' => Context::METADATA_REQUIRE, 'label' => __('manager.setup.metadata.subjects.require')],
                ],
                'value' => $context->getData('subjects') ? $context->getData('subjects') : Context::METADATA_DISABLE,
            ]))
            ->addField(new FieldMetadataSetting('type', [
                'label' => __('common.type'),
                'description' => __('manager.setup.metadata.type.description'),
                'options' => [
                    ['value' => Context::METADATA_ENABLE, 'label' => __('manager.setup.metadata.type.enable')]
                ],
                'submissionOptions' => [
                    ['value' => Context::METADATA_ENABLE, 'label' => __('manager.setup.metadata.type.noRequest')],
                    ['value' => Context::METADATA_REQUEST, 'label' => __('manager.setup.metadata.type.request')],
                    ['value' => Context::METADATA_REQUIRE, 'label' => __('manager.setup.metadata.type.require')],
                ],
                'value' => $context->getData('type') ? $context->getData('type') : Context::METADATA_DISABLE,
            ]))
            ->addField(new FieldMetadataSetting('disciplines', [
                'label' => __('search.discipline'),
                'description' => __('manager.setup.metadata.disciplines.description'),
                'options' => [
                    ['value' => Context::METADATA_ENABLE, 'label' => __('manager.setup.metadata.disciplines.enable')]
                ],
                'submissionOptions' => [
                    ['value' => Context::METADATA_ENABLE, 'label' => __('manager.setup.metadata.disciplines.noRequest')],
                    ['value' => Context::METADATA_REQUEST, 'label' => __('manager.setup.metadata.disciplines.request')],
                    ['value' => Context::METADATA_REQUIRE, 'label' => __('manager.setup.metadata.disciplines.require')],
                ],
                'value' => $context->getData('disciplines') ? $context->getData('disciplines') : Context::METADATA_DISABLE,
            ]))
            ->addField(new FieldMetadataSetting('keywords', [
                'label' => __('common.keywords'),
                'description' => __('manager.setup.metadata.keywords.description'),
                'options' => [
                    ['value' => Context::METADATA_ENABLE, 'label' => __('manager.setup.metadata.keywords.enable')]
                ],
                'submissionOptions' => [
                    ['value' => Context::METADATA_ENABLE, 'label' => __('manager.setup.metadata.keywords.noRequest')],
                    ['value' => Context::METADATA_REQUEST, 'label' => __('manager.setup.metadata.keywords.request')],
                    ['value' => Context::METADATA_REQUIRE, 'label' => __('manager.setup.metadata.keywords.require')],
                ],
                'value' => $context->getData('keywords') ? $context->getData('keywords') : Context::METADATA_DISABLE,
            ]))
            ->addField(new FieldMetadataSetting('agencies', [
                'label' => __('submission.supportingAgencies'),
                'description' => __('manager.setup.metadata.agencies.description'),
                'options' => [
                    ['value' => Context::METADATA_ENABLE, 'label' => __('manager.setup.metadata.agencies.enable')]
                ],
                'submissionOptions' => [
                    ['value' => Context::METADATA_ENABLE, 'label' => __('manager.setup.metadata.agencies.noRequest')],
                    ['value' => Context::METADATA_REQUEST, 'label' => __('manager.setup.metadata.agencies.request')],
                    ['value' => Context::METADATA_REQUIRE, 'label' => __('manager.setup.metadata.agencies.require')],
                ],
                'value' => $context->getData('agencies') ? $context->getData('agencies') : Context::METADATA_DISABLE,
            ]))
            ->addField(new FieldMetadataSetting('citations', [
                'label' => __('submission.citations'),
                'description' => __('manager.setup.metadata.citations.description'),
                'options' => [
                    ['value' => Context::METADATA_ENABLE, 'label' => __('manager.setup.metadata.citations.enable')]
                ],
                'submissionOptions' => [
                    ['value' => Context::METADATA_ENABLE, 'label' => __('manager.setup.metadata.citations.noRequest')],
                    ['value' => Context::METADATA_REQUEST, 'label' => __('manager.setup.metadata.citations.request')],
                    ['value' => Context::METADATA_REQUIRE, 'label' => __('manager.setup.metadata.citations.require')],
                ],
                'value' => $context->getData('citations') ? $context->getData('citations') : Context::METADATA_DISABLE,
            ]));
    }
}
