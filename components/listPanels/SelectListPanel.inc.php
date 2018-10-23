<?php
/**
 * @file components/listPanels/SelectListPanel.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SelectListPanel
 * @ingroup classes_controllers_list
 *
 * @brief A base class for list selection handlers. This defines the structure
 *  that will be used by handlers which need to select from a list of items.
 */
import('lib.pkp.components.listPanels.ListPanel');

class SelectListPanel extends ListPanel {
	/** @var string A notice to display above items (expects translation key) */
	public $_notice = '';

	/** @var string Input field name */
	public $_inputName = '';

	/** @var string Input field type (checkbox or radio) */
	public $_inputType = 'checkbox';

	/** @var array Pre-selected input values */
	public $_selected = array();

	/** @var mixed Items to select from */
	public $_items = null;

	/**
	 * @copydoc ListPanel::init()
	 */
	public function init($args = array()) {
		parent::init($args);
		$this->_notice = !empty($args['notice']) ? $args['notice'] : $this->_notice;
		$this->_inputName = !empty($args['inputName']) ? $args['inputName'] : $this->_inputName;
		$this->_inputType = !empty($args['inputType']) ? $args['inputType'] : $this->_inputType;
		$this->_selected = !empty($args['selected']) ? $args['selected'] : $this->_selected;
		$this->_items = !empty($args['items']) ? $args['items'] : $this->_items;
	}

	/**
	 * @copydoc ListPanel::getConfig()
	 */
	public function getConfig() {

		if (is_null($this->_items)) {
			$this->_items = $this->getItems();
		}

		$config = array(
			'inputName' => $this->_inputName,
			'inputType' => $this->_inputType,
			'selected' => $this->_selected,
			'items' => $this->_items,
			'i18n' => array(
				'title' => __($this->_title),
			),
		);

		if ($this->_notice) {
			$config['i18n']['notice'] = __($this->_notice);
		}

		return $config;
	}
}
