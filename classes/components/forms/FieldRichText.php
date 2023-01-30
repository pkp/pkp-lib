<?php
/**
 * @file classes/components/form/FieldRichText.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FieldRichText
 * @ingroup classes_controllers_form
 *
 * @brief A rich signle line text editor field in a form.
 */

namespace PKP\components\forms;

class FieldRichText extends Field
{
    /** @copydoc Field::$component */
    public $component = 'field-rich-text';

    /** @var array Optional. An assoc array of init properties to pass to TinyMCE */
    public $init;

    /** @var string Optional. A preset size option. */
    public $size;

    /** @var string Optional. A preset toolbar configuration. */
    public $toolbar = 'bold italic superscript subscript';

    /** @var int Optional. When a word limit is specified a word counter will be shown */
    public $wordLimit = 0;

    /** @var array Optional. A list of required plugins. */
    public $plugins = 'paste';

    /**
     * @copydoc Field::getConfig()
     */
    public function getConfig()
    {
        $config = parent::getConfig();
        $config['toolbar'] = $this->toolbar;

        if (!empty($this->init)) {
            $config['init'] = $this->init;
        }

        if (!empty($this->size)) {
            $config['size'] = $this->size;
        }
        
        if ($this->wordLimit) {
            $config['wordLimit'] = $this->wordLimit;
            $config['wordCountLabel'] = __('publication.wordCount');
        }

        return $config;
    }
}
