<?php
/**
 * @file classes/components/form/FieldRichText.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FieldRichText
 *
 * @ingroup classes_controllers_form
 *
 * @brief A rich single line text editor field in a form.
 */

namespace PKP\components\forms;

class FieldRichText extends Field
{
    /** @copydoc Field::$component */
    public $component = 'field-rich-text';

    /** @var array Optional. An assoc array of init properties to pass to TinyMCE */
    public $init;

    /** @var string Optional. A preset size option. */
    public $size = 'oneline';

    /** @var string Optional. A preset toolbar configuration. */
    public $toolbar = 'formatgroup';

    /** @var array Optional. A list of required plugins. */
    public $plugins = 'paste';

    /**
     * @copydoc Field::getConfig()
     */
    public function getConfig()
    {
        $config = parent::getConfig();

        $config['i18nFormattingLabel'] = __('common.formatting');

        $config['toolbar'] = $this->toolbar;
        $config['plugins'] = $this->plugins;
        $config['size'] = $this->size;

        if (!empty($this->init)) {
            $config['init'] = $this->init;
        }

        return $config;
    }
}
