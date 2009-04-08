<?php

/**
 * @file classes/plugins/PKPBlockPlugin.inc.php
 *
 * Copyright (c) 2000-2009 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class BlockPlugin
 * @ingroup plugins
 *
 * @brief Abstract class for block plugins
 */

// $Id$


define('BLOCK_CONTEXT_LEFT_SIDEBAR',		0x00000001);
define('BLOCK_CONTEXT_RIGHT_SIDEBAR', 		0x00000002);
define('BLOCK_CONTEXT_HOMEPAGE',		0x00000003);

class PKPBlockPlugin extends PKPPlugin {
	function register($category, $path) {
		$success = parent::register($category, $path);
		if ($success && $this->getEnabled()) {
			$contextMap =& $this->getContextMap();
			$blockContext = $this->getBlockContext();
			if (isset($contextMap[$blockContext])) {
				$hookName = $contextMap[$blockContext];
				HookRegistry::register($hookName, array(&$this, 'callback'));
			}
		}
		return $success;
	}

	/**
	 * Get the block context (e.g. BLOCK_CONTEXT_...) for this block.
	 * @return int
	 */
	function getBlockContext() {
		fatalError('Abstract method');
	}

	/**
	 * Set the block context (e.g. BLOCK_CONTEXT_...) for this block.
	 * @param context int
	 */
	function setBlockContext($context) {
		fatalError('Abstract method');
	}

	/**
	 * Get the supported contexts (e.g. BLOCK_CONTEXT_...) for this block.
	 * @return array
	 */
	function getSupportedContexts() {
		fatalError('Abstract method');
	}

	/**
	 * Determine whether or not this plugin is currently enabled.
	 * @return boolean
	 */
	function getEnabled() {
		fatalError('Abstract method');
	}

	/**
	 * Set whether or not this plugin is currently enabled.
	 * @param $enabled boolean
	 */
	function setEnabled($enabled) {
		fatalError('Abstract method');
	}

	/**
	 * Get the sequence information for this plugin.
	 * Higher numbers move plugins down the page compared to other blocks.
	 * @return int
	 */
	function getSeq() {
		fatalError('Abstract method');
	}

	/**
	 * Set the sequence information for this plugin.
	 * Higher numbers move plugins down the page compared to other blocks.
	 * @param i int
	 */
	function setSeq($i) {
		fatalError('Abstract method');
	}

	/**
	 * Get an associative array linking block context to hook name.
	 * @return array
	 */
	function &getContextMap() {
		fatalError('Abstract method');
	}

	/**
	 * Get the name of this plugin. The name must be unique within
	 * its category.
	 * @return String name of plugin
	 */
	function getName() {
		// This should not be used as this is an abstract class
		return 'BlockPlugin';
	}

	/**
	 * Get the display name of this plugin.
	 * @return String
	 */
	function getDisplayName() {
		// This name should never be displayed because child classes
		// will override this method.
		return 'Abstract Block Plugin';
	}

	/**
	 * Get a description of the plugin.
	 */
	function getDescription() {
		return 'This is the BlockPlugin base class. Its functions can be overridden by subclasses to provide support for UI blocks.';
	}

	/**
	 * Get the filename of the template block. (Default behavior may
	 * be overridden through some combination of this function and the
	 * getContents function.)
	 * Returning null from this function results in an empty display.
	 * @return string
	 */
	function getBlockTemplateFilename() {
		return 'block.tpl';
	}

	/**
	 * Get the HTML contents for this block.
	 * @param $templateMgr object
	 * @return string
	 */
	function getContents(&$templateMgr) {
		$blockTemplateFilename = $this->getBlockTemplateFilename();
		if ($blockTemplateFilename === null) return '';
		return $templateMgr->fetch($this->getTemplatePath() . '/' . $blockTemplateFilename);
	}

	function callback($hookName, &$args) {
		$params =& $args[0];
		$smarty =& $args[1];
		$output =& $args[2];
		$output .= $this->getContents($smarty);
	}
}

?>
