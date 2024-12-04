<?php
/**
 * @file classes/components/form/FieldAffiliation.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FieldAffiliation
 *
 * @ingroup classes_controllers_form
 *
 * @brief A field for author affiliations.
 */

namespace PKP\components\forms;

class FieldAffiliations extends Field
{
    /** @copydoc Field::$component */
    public $component = 'field-affiliations';

    /** @copydoc Field::$component */
    public $default = [];

    /**
     * Author ID associated with the affiliations
     * Filled in components/ListPanel/ContributorsListPanel.vue
     */
    public int $authorId = 0;

    /**
     * Primary language
     * Filled in components/ListPanel/ContributorsListPanel.vue
     */
    public string $primaryLocale = '';

    /**
     * Supported locales for forms
     * Filled in components/ListPanel/ContributorsListPanel.vue
     */
    public array $supportedFormLocales = [];

    /**
     * @copydoc Field::getConfig()
     */
    public function getConfig()
    {
        $config = parent::getConfig();

        $config['authorId'] = $this->authorId;
        $config['primaryLocale'] = $this->primaryLocale;
        $config['supportedFormLocales'] = $this->supportedFormLocales;
        $config['value'] = $this->value ?? $this->default ?? null;

        return $config;
    }
}

