<?php
/**
 * @file classes/components/form/FieldMetadataSetting.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FieldMetadataSetting
 * @ingroup classes_controllers_form
 *
 * @brief A field to enable a type of metadata and determine when it should be
 *  requested or required.
 */

namespace PKP\components\forms;

use PKP\context\Context;

class FieldMetadataSetting extends FieldOptions
{
    /** @copydoc Field::$component */
    public $component = 'field-metadata-setting';

    /** @var int What is the value that represents metadata that is disabled */
    public $disabledValue = Context::METADATA_DISABLE;

    /**
     * @var int What is the value that represents metadata that is enabled,
     *	but which is not requested or required during submission?
     */
    public $enabledOnlyValue = Context::METADATA_ENABLE;

    /** @var array The options for what to request/require from the author during submission */
    public $submissionOptions = [];

    /**
     * @copydoc Field::getConfig()
     */
    public function getConfig()
    {
        $config = parent::getConfig();
        $config['disabledValue'] = $this->disabledValue;
        $config['enabledOnlyValue'] = $this->enabledOnlyValue;
        $config['submissionOptions'] = $this->submissionOptions;

        return $config;
    }
}
