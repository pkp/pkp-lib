<?php
/**
 * @file classes/components/form/FormComponent.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FormComponent
 * @ingroup classes_controllers_form
 *
 * @brief A base class for building forms to be passed to the Form component
 *  in the UI Library.
 */

namespace PKP\components\forms;

use Exception;
use PKP\facades\Locale;

define('FIELD_POSITION_BEFORE', 'before');
define('FIELD_POSITION_AFTER', 'after');

class FormComponent
{
    /** @var string A unique ID for this form */
    public $id = '';

    /** @var string Form method: POST or PUT */
    public $method = '';

    /** @var string Where the form should be submitted. */
    public $action = '';

    /** @var array Key/value list of languages this form should support. Key = locale code. Value = locale name */
    public $locales = [];

    /** @var array List of fields in this form. */
    public $fields = [];

    /** @var array List of groups in this form. */
    public $groups = [];

    /** @var array List of pages in this form. */
    public $pages = [];

    /** @var array List of error messages */
    public $errors = [];

    /**
     * Initialize the form with config parameters
     *
     * @param string $id
     * @param string $method
     * @param string $action
     * @param array $locales
     */
    public function __construct($id, $method, $action, $locales)
    {
        $this->id = $id;
        $this->action = $action;
        $this->method = $method;
        $this->locales = $locales;
    }

    /**
     * Add a form field
     *
     * @param Field $field
     * @param array $position [
     *  @option string One of FIELD_POSITION_BEFORE or FIELD_POSITION_AFTER
     *  @option string The field to position it before or after
     * ]
     *
     * @return FormComponent
     */
    public function addField($field, $position = [])
    {
        if (empty($position)) {
            $this->fields[] = $field;
        } else {
            $this->fields = $this->addToPosition($position[1], $this->fields, $field, $position[0]);
        }
        return $this;
    }

    /**
     * Remove a form field
     *
     * @param string $fieldName
     *
     * @return FormComponent
     */
    public function removeField($fieldName)
    {
        $this->fields = array_values(array_filter($this->fields, function ($field) use ($fieldName) {
            return $field->name !== $fieldName;
        }));
        return $this;
    }

    /**
     * Get a form field
     *
     * @param string $fieldName
     *
     * @return Field
     */
    public function getField($fieldName)
    {
        foreach ($this->fields as $field) {
            if ($field->name === $fieldName) {
                return $field;
            }
        }
        return null;
    }

    /**
     * Add a form group
     *
     * @param array $args [
     *  @option id string Required A unique ID for this form group
     *  @option label string A label to identify this group of fields. Will become the fieldset's <legend>
     *  @option description string A description of this group of fields.
     * ]
     *
     * @param array $position [
     *  @option string One of FIELD_POSITION_BEFORE or FIELD_POSITION_AFTER
     *  @option string The group to position it before or after
     * ]
     *
     * @return FormComponent
     */
    public function addGroup($args, $position = [])
    {
        if (empty($args['id'])) {
            fatalError('Tried to add a form group without an id.');
        }
        if (empty($position)) {
            $this->groups[] = $args;
        } else {
            $this->groups = $this->addToPosition($position[1], $this->groups, $args, $position[0]);
        }
        return $this;
    }

    /**
     * Remove a form group
     *
     * @param string $groupId
     *
     * @return FormComponent
     */
    public function removeGroup($groupId)
    {
        $this->groups = array_filter($this->groups, function ($group) use ($groupId) {
            return $group['id'] !== $groupId;
        });
        $this->fields = array_filter($this->fields, function ($field) use ($groupId) {
            return $field['groupId'] !== $groupId;
        });
        return $this;
    }

    /**
     * Add a form page
     *
     * @param array $args [
     *  @option id string Required A unique ID for this form page
     *  @option label string The name of the page to identify it in the page list
     *  @option submitButton array Required Assoc array defining submission/next button params. Supports any param of the Button component in the UI Library.
     *  @option previousButton array Assoc array defining button params to go back to the previous page. Supports any param of the Button component in the UI Library.
     * ]
     *
     * @param array $position [
     *  @option string One of FIELD_POSITION_BEFORE or FIELD_POSITION_AFTER
     *  @option string The page to position it before or after
     * ]
     *
     * @return FormComponent
     */
    public function addPage($args, $position = [])
    {
        if (empty($args['id'])) {
            fatalError('Tried to add a form page without an id.');
        }
        if (empty($position)) {
            $this->pages[] = $args;
        } else {
            $this->pages = $this->addToPosition($position[1], $this->pages, $args, $position[0]);
        }
        return $this;
    }

    /**
     * Remove a form page
     *
     * @param string $pageId
     *
     * @return FormComponent
     */
    public function removePage($pageId)
    {
        $this->pages = array_filter($this->pages, function ($page) use ($pageId) {
            return $page['id'] !== $pageId;
        });
        foreach ($this->groups as $group) {
            if ($group['pageId'] === $pageId) {
                $this->removeGroup($group['id']);
            }
        }
        return $this;
    }

    /**
     * Add an field, group or page to a specific position in its array
     *
     * @param string $id The id of the item to position before or after
     * @param array $list The list of fields, groups or pages
     * @param array $item The item to insert
     * @param string $position FIELD_POSITION_BEFORE or FIELD_POSITION_AFTER
     *
     * @return array
     */
    public function addToPosition($id, $list, $item, $position)
    {
        $index = count($list);
        foreach ($list as $key => $val) {
            if (($val instanceof \PKP\components\forms\Field && $id === $val->name) || (!$val instanceof \PKP\components\forms\Field && $id === $val['id'])) {
                $index = $key;
                break;
            }
        }
        if (!$index && $position === FIELD_POSITION_BEFORE) {
            array_unshift($list, $item);
            return $list;
        }

        $slice = $position === FIELD_POSITION_BEFORE ? $index : $index + 1;

        return array_merge(
            array_slice($list, 0, $slice),
            [$item],
            array_slice($list, $slice)
        );
    }

    /**
     * Retrieve the configuration data to be used when initializing this
     * handler on the frontend
     *
     * @return array Configuration data
     */
    public function getConfig()
    {
        if (empty($this->id) || empty($this->method) || empty($this->action)) {
            throw new Exception('FormComponent::getConfig() was called but one or more required property is missing: id, method, action.');
        }

        \HookRegistry::call('Form::config::before', $this);

        // Add a default page/group if none exist
        if (!$this->groups) {
            $this->addGroup(['id' => 'default']);
            $this->fields = array_map(function ($field) {
                $field->groupId = 'default';
                return $field;
            }, $this->fields);
        }

        if (!$this->pages) {
            $this->addPage(['id' => 'default', 'submitButton' => ['label' => __('common.save')]]);
            $this->groups = array_map(function ($group) {
                $group['pageId'] = 'default';
                return $group;
            }, $this->groups);
        }

        $fieldsConfig = array_map([$this, 'getFieldConfig'], $this->fields);

        $visibleLocales = [Locale::getLocale()];
        if (Locale::getLocale() !== Locale::getPrimaryLocale()) {
            array_unshift($visibleLocales, Locale::getPrimaryLocale());
        }

        $config = [
            'id' => $this->id,
            'method' => $this->method,
            'action' => $this->action,
            'fields' => $fieldsConfig,
            'groups' => $this->groups,
            'pages' => $this->pages,
            'primaryLocale' => Locale::getPrimaryLocale(),
            'visibleLocales' => $visibleLocales,
            'supportedFormLocales' => array_values($this->locales), // See #5690
            'errors' => (object) [],
        ];

        \HookRegistry::call('Form::config::after', [&$config, $this]);

        return $config;
    }

    /**
     * Compile a configuration array for a single field
     *
     * @param Field $field
     *
     * @return array
     */
    public function getFieldConfig($field)
    {
        $config = $field->getConfig();

        // Add a value property if the field does not include one
        if (!array_key_exists('value', $config)) {
            $config['value'] = $field->isMultilingual ? [] : $field->getEmptyValue();
        }
        if ($field->isMultilingual) {
            foreach ($this->locales as $locale) {
                if (!array_key_exists($locale['key'], $config['value'])) {
                    $config['value'][$locale['key']] = $field->getEmptyValue();
                }
            }
        }

        return $config;
    }
}
