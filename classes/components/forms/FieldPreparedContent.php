<?php
/**
 * @file classes/components/form/FieldPreparedContent.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FieldPreparedContent
 * @ingroup classes_controllers_form
 *
 * @brief A rich text editor that can insert prepared content snippets
 */

namespace PKP\components\forms;

use stdClass;

class FieldPreparedContent extends FieldRichTextarea
{
    public $component = 'field-prepared-content';

    /**
     * A list of content that can be inserted from a TinyMCE button.
     *
     * @see FieldPreparedContent in the UI Library for details on the expected format
     */
    public array $preparedContent = [];

    public function getConfig()
    {
        $config = parent::getConfig();
        $config['preparedContentLabel'] = __('common.content');
        $config['insertLabel'] = __('common.insert');
        $config['insertModalLabel'] = __('common.insertContent');
        $config['searchLabel'] = __('common.insertContentSearch');
        $config['preparedContent'] = $this->preparedContent;

        return $config;
    }
}
