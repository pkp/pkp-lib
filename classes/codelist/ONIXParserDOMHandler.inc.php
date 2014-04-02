<?php

/**
 * @file classes/codelist/ONIXParserDOMHandler.inc.php
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ONIXParserDOMHandler
 * @ingroup codelist
 * @see XMLParser
 *
 * @brief This parser extracts a specific xs:simpleType based on a name attribute
 * representing a code list within it. It returns the xs:enumeration values
 * within it along with the xs:documentation elements which serve as textual
 * descriptions of the Codelist values.
 *
 * Example:  <xs:simpleType name="List30">...</xs:simpleType>
 */

import('lib.pkp.classes.xml.XMLParserDOMHandler');
import('lib.pkp.classes.xml.XMLNode');

class ONIXParserDOMHandler extends XMLParserDOMHandler {

	/** @string  the list being searched for */
	var $_listName = null;

	/** @boolean to maintain state */
	var $_foundRequestedList = false;

	/** @array of items the parser eventually returns */
	var $_listItems = null;

	/** @string to store the current character data  */
	var $_currentValue = null;

	/** @boolean currently inside an xs:documentation element */
	var $_insideDocumentation = false;

	/**
	 * Constructor.
	 */
	function ONIXParserDOMHandler($listName) {
		parent::XMLParserHandler();
		$this->_listName =& $listName;
		$this->_listItems = array();
	}

	function startElement(&$parser, $tag, $attributes) {
		$this->currentData = null;

		switch ($tag) {
			case 'xs:simpleType':
				if ($attributes['name'] == $this->_listName) {
					$this->_foundRequestedList = true;
				}
				break;
			case 'xs:enumeration':
				if ($this->_foundRequestedList) {
					$this->_currentValue = $attributes['value'];
					$this->_listItems[$this->_currentValue] = array(); // initialize the array cell
				}
				break;
			case 'xs:documentation':
				if ($this->_foundRequestedList) {
					$this->_insideDocumentation = true;
				}
				break;
		}

		$node = new XMLNode($tag);
		$node->setAttributes($attributes);
		if (isset($this->currentNode)) {
			$this->currentNode->addChild($node);
			$node->setParent($this->currentNode);

		} else {
			$this->rootNode =& $node;
		}

		$this->currentNode =& $node;
	}

	/**
	 * Callback function to act as the character data handler.
	 */
	function characterData(&$parser, $data) {
		if ($this->_insideDocumentation) {
			$this->_listItems[$this->_currentValue][] = $data;
		}
	}

	/**
	 * Callback function to act as the end element handler.
	 */
	function endElement(&$parser, $tag) {

		switch ($tag) {
			case 'xs:simpleType':
				$this->_foundRequestedList = false;
				break;
			case 'xs:documentation':
				$this->_insideDocumentation = false;
				break;
		}
	}

	/**
	 * Returns the array of found list items
	 * @return array
	 */
	function getResult() {
		return array($this->_listName => $this->_listItems);
	}
}
?>
