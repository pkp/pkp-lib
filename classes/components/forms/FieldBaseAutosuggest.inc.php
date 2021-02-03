<?php
/**
 * @file classes/components/form/FieldBaseAutosuggest.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FieldBaseAutosuggest
 * @ingroup classes_controllers_form
 *
 * @brief A base class for text fields that provide suggested values while typing.
 */
namespace PKP\components\forms;

define('AUTOSUGGEST_POSITION_INLINE', 'inline');
define('AUTOSUGGEST_POSITION_BELOW', 'below');

abstract class FieldBaseAutosuggest extends Field {
	/** @copydoc Field::$component */
	public $component = 'field-base-autosuggest';

	/** @var string A URL to retrieve suggestions. */
	public $apiUrl;

	/** @var array Query params when getting suggestions. */
	public $getParams = [];

	/** @var string Displayed in the text box or below the input. One of the AUTOSUGGEST_POSITION_* constants. */
	public $initialPosition = AUTOSUGGEST_POSITION_INLINE;

	/** @var array List of selected items. */
	public $selected = [];

	/**
	 * @copydoc Field::getConfig()
	 */
	public function getConfig() {
		$config = parent::getConfig();
		$config['apiUrl'] = $this->apiUrl;
		$config['deselectLabel'] = __('common.removeItem');
		$config['getParams'] = empty($this->getParams) ? new \stdClass() : $this->getParams;
		$config['initialPosition'] = $this->initialPosition;
		$config['selectedLabel'] = __('common.selectedPrefix');
		$config['selected'] = $this->selected;

		return $config;
	}
}
