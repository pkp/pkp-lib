<?php
/**
 * @file classes/components/form/FieldAutosuggest.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FieldAutosuggest
 * @ingroup classes_controllers_form
 *
 * @brief A basic text field in a form.
 */
namespace PKP\components\forms;

define('AUTOSUGGEST_POSITION_INLINE', 'inline');
define('AUTOSUGGEST_POSITION_BELOW', 'below');

class FieldAutosuggest extends Field {
	/** @copydoc Field::$component */
	public $component = 'field-autosuggest';

	/** @var string Whether selections should be displayed inline or below the input. One of the AUTOSUGGEST_POSITION_* constants. */
	public $initialPosition = AUTOSUGGEST_POSITION_INLINE;

	/** @var string A URL to retrieve suggestions. */
	public $suggestionsUrl;

	/**
	 * @copydoc Field::getConfig()
	 */
	public function getConfig() {
		$config = parent::getConfig();
		$config['initialPosition'] = $this->initialPosition;
		$config['suggestionsUrl'] = $this->suggestionsUrl;
		$config['deselectLabel'] = __('common.removeItem');
		$config['noneLabel'] = __('common.none');
		$config['selectedLabel'] = __('common.selectedPrefix');

		return $config;
	}
}
