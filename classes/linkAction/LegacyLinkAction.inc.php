<?php


/**
 * @file classes/linkAction/LegacyLinkAction.inc.php
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class LegacyLinkAction
 * @ingroup linkAction
 *
 * @brief Base class defining an action that can be performed within a Grid
 */

define('LINK_ACTION_MODE_MODAL', 1);
define('LINK_ACTION_MODE_LINK', 2);
define('LINK_ACTION_MODE_AJAX', 3);
define('LINK_ACTION_MODE_CONFIRM', 4);

// Action types for modal mode
define('LINK_ACTION_TYPE_NOTHING', 'nothing');
define('LINK_ACTION_TYPE_APPEND', 'append');
define('LINK_ACTION_TYPE_REPLACE', 'replace');
define('LINK_ACTION_TYPE_REMOVE', 'remove');
define('LINK_ACTION_TYPE_REDIRECT', 'redirect');

class LegacyLinkAction {
	/** @var string the id of the action */
	var $_id;

	/** @var string url of the action */
	var $_url;

	/** @var integer the mode of the action (modal, ajax, link, etc) */
	var $_mode;

	/** @var string the type of action to be done on callback */
	var $_type;

	/** @var string optional, the title of the link, translated */
	var $_title;

	/** @var string optional, the title of the link, translated */
	var $_titleLocalized;

	/** @var string optional, the URL to the image to be linked to */
	var $_image;

	/** @var string optional, the locale key for a message to display in a confirm dialog */
	var $_confirmMessageLocalized;

	/**
	 * @var string a specification of the target on which the action
	 *  should act, e.g. a selector when the view technology is HTML/jQuery.
	 *
	 *  The default depends on the implementation of the action type
	 *  in the view.
	 */
	var $_actOn;

	/**
	 * Constructor
	 * @param $id string
	 * @param $mode integer one of LINK_ACTION_MODE_*
	 * @param $type string one of LINK_ACTION_TYPE_*
	 * @param $url string
	 * @param $title string (optional)
	 * @param $titleLocalized string (optional)
	 * @param $image string (optional)
	 * @param $confirmMessageLocalized string (optional)
	 * @param $actOn string (optional) a specification of the target object
	 *  to act on
	 */
	function LegacyLinkAction($id, $mode, $type, $url, $title = null, $titleLocalized = null, $image = null, $confirmMessageLocalized = null, $actOn = null) {
		$this->_id = $id;
		$this->_mode = $mode;
		$this->_type = $type;
		$this->_url = $url;
		$this->_title = $title;
		$this->_titleLocalized = $titleLocalized;
		$this->_image = $image;
		$this->_confirmMessageLocalized = $confirmMessageLocalized;
		$this->_actOn = $actOn;
	}

	/**
	 * Set the action id.
	 * @param $id string
	 */
	function setId($id) {
		$this->_id = $id;
	}

	/**
	 * Get the action id.
	 * @return string
	 */
	function getId() {
		return $this->_id;
	}

	/**
	 * Set the action mode.
	 * @param $mode integer
	 */
	function setMode($mode) {
		$this->_mode = $mode;
	}

	/**
	 * Get the action mode.
	 * @return integer
	 */
	function getMode() {
		return $this->_mode;
	}

	/**
	 * Set the action type.
	 * @param $type string
	 */
	function setType($type) {
		$this->_type = $type;
	}

	/**
	 * Get the action type.
	 * @return string
	 */
	function getType() {
		return $this->_type;
	}

	/**
	 * Set the action URL.
	 * @param $url string
	 */
	function setUrl($url) {
		$this->_url = $url;
	}

	/**
	 * Get the action URL.
	 * @return string
	 */
	function getUrl() {
		return $this->_url;
	}

	/**
	 * Set the action title.
	 * @param $title string
	 */
	function setTitle($title) {
		$this->_title = $title;
	}

	/**
	 * Get the action title.
	 * @return string
	 */
	function getTitle() {
		return $this->_title;
	}

	/**
	 * Set the column title (already translated)
	 * @param $titleLocalized string
	 */
	function setTitleTranslated($titleLocalized) {
		$this->_titleLocalized = $titleLocalized;
	}

	/**
	 * Get the translated column title
	 * @return string
	 */
	function getLocalizedTitle() {
		if ( $this->_titleLocalized ) return $this->_titleLocalized;
		return __($this->_title);
	}

	/**
	 * Set the action image.
	 * @param $image string
	 */
	function setImage($image) {
		$this->_image = $image;
	}

	/**
	 * Get the action image.
	 * @return string
	 */
	function getImage() {
		return $this->_image;
	}

	/**
	 * Set the locale key to display in the confirm dialog
	 * @param $confirmMessageLocalized string
	 */
	function setLocalizedConfirmMessage($confirmMessageLocalized) {
		$this->_confirmMessageLocalized = $confirmMessageLocalized;
	}

	/**
	 * Get the locale key to display in the confirm dialog
	 * @return string
	 */
	function getLocalizedConfirmMessage() {
		return $this->_confirmMessageLocalized;
	}

	/**
	 * Specify the target object of the action (if any).
	 * @param $actOn string
	 */
	function setActOn($actOn) {
		$this->_actOn = $actOn;
	}

	/**
	 * Get the target object of the action (null if none configured).
	 * @return string
	 */
	function getActOn() {
		return $this->_actOn;
	}
}

?>
