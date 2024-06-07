<?php
/**
 * @file classes/components/form/FieldRichText.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FieldRichText
 *
 * @ingroup classes_controllers_form
 *
 * @brief A rich single line text editor field in a form.
 */

namespace PKP\components\forms;

use PKP\config\Config;

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

    /** @var string Optional. A list of comma separated elements. */
    public $invalidElements;

    /** @var string Optional. A list of comma separated list of element conversion chunks. */
    public $validElements = null;

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

        if (isset($this->invalidElements)) {
            $config['invalidElements'] = $this->invalidElements;
        }

        $config['validElements'] = $this->validElements ?? Config::getVar('security', 'allowed_title_html', 'b,i,u,sup,sub');

        return $config;
    }
}
