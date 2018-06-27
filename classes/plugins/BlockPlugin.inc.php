<?php

/**
 * @file classes/plugins/BlockPlugin.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class BlockPlugin
 * @ingroup plugins
 *
 * @brief Abstract class for block plugins
 */

define('BLOCK_CONTEXT_SIDEBAR',		0x00000001);
define('BLOCK_CONTEXT_HOMEPAGE',		0x00000003);

import('lib.pkp.classes.plugins.LazyLoadPlugin');

abstract class BlockPlugin extends LazyLoadPlugin {

	//
	// Override public methods from Plugin
	//

	/**
	 * @copydoc Plugin::register()
	 */
	function register($category, $path, $mainContextId = null) {
		$success = parent::register($category, $path, $mainContextId);
		if ($success && $this->getEnabled($mainContextId)) {
			$contextMap = $this->getContextMap();
			$blockContext = $this->getBlockContext();
			if (isset($contextMap[$blockContext])) {
				$hookName = $contextMap[$blockContext];
				HookRegistry::register($hookName, array($this, 'callback'), HOOK_SEQUENCE_NORMAL + $this->getSeq());
			}
		}
		return $success;
	}

	/**
	 * Override protected methods from Plugin
	 */
	/**
	 * @see Plugin::getSeq()
	 *
	 * NB: In the case of block plugins, higher numbers move
	 * plugins down the page compared to other blocks.
	 *
	 * @param $contextId int Context ID (journal/press)
	 */
	function getSeq($contextId = null) {
		return $this->getSetting(is_null($contextId) ? $this->getCurrentContextId() : $contextId, 'seq');
	}

	/*
	 * Block Plugin specific methods
	 */
	/**
	 * Set the sequence information for this plugin.
	 *
	 * NB: In the case of block plugins, higher numbers move
	 * plugins down the page compared to other blocks.
	 *
	 * @param $seq int
	 * @param $contextId int Context ID (journal/press)
	 */
	function setSeq($seq, $contextId = null) {
		return $this->updateSetting(is_null($contextId) ? $this->getCurrentContextId() : $contextId, 'seq', $seq, 'int');
	}

	/**
	 * Get the block context (e.g. BLOCK_CONTEXT_...) for this block.
	 *
	 * @param $contextId int Context ID (journal/press)
	 * @return int
	 */
	function getBlockContext($contextId = null) {
		return $this->getSetting(is_null($contextId) ? $this->getCurrentContextId() : $contextId, 'context');
	}

	/**
	 * Set the block context (e.g. BLOCK_CONTEXT_...) for this block.
	 *
	 * @param $context int Sidebar context
	 * @param $contextId int Context ID (journal/press)
	 */
	function setBlockContext($context, $contextId = null) {
		return $this->updateSetting(is_null($contextId) ? $this->getCurrentContextId() : $contextId, 'context', $context, 'int');
	}

	/**
	 * Determine whether or not this plugin is currently enabled.
	 *
	 * @param $contextId int Context ID (journal/press)
	 * @return boolean
	 */
	function getEnabled($contextId = null) {
		return $this->getSetting(is_null($contextId) ? $this->getCurrentContextId() : $contextId, 'enabled');
	}

	/**
	 * Set whether or not this plugin is currently enabled.
	 *
	 * @param $enabled boolean
	 * @param $contextId int Context ID (journal/press)
	 */
	function setEnabled($enabled, $contextId = null) {
		$this->updateSetting(is_null($contextId) ? $this->getCurrentContextId() : $contextId, 'enabled', $enabled, 'bool');
	}


	/**
	 * Get the supported contexts (e.g. BLOCK_CONTEXT_...) for this block.
	 *
	 * @return array
	 */
	function getSupportedContexts() {
		return array(BLOCK_CONTEXT_SIDEBAR);
	}

	/**
	 * Get an associative array linking block context to hook name.
	 *
	 * @return array
	 */
	function &getContextMap() {
		static $contextMap = array(
			BLOCK_CONTEXT_SIDEBAR => 'Templates::Common::Sidebar',
		);

		$homepageHook = $this->_getContextSpecificHomepageHook();
		if ($homepageHook) $contextMap[BLOCK_CONTEXT_HOMEPAGE] = $homepageHook;

		HookRegistry::call('BlockPlugin::getContextMap', array($this, &$contextMap));
		return $contextMap;
	}

	/**
	 * Get the filename of the template block. (Default behavior may
	 * be overridden through some combination of this function and the
	 * getContents function.)
	 * Returning null from this function results in an empty display.
	 *
	 * @return string
	 */
	function getBlockTemplateFilename() {
		return 'block.tpl';
	}

	/**
	 * Get the HTML contents for this block.
	 *
	 * @param $templateMgr object
	 * @param $request PKPRequest (Optional for legacy plugins)
	 * @return string
	 */
	function getContents($templateMgr, $request = null) {
		$blockTemplateFilename = $this->getBlockTemplateFilename();
		if ($blockTemplateFilename === null) return '';
		return $templateMgr->fetch($this->getTemplateResource($blockTemplateFilename));
	}

	/**
	 * Callback that renders the block.
	 *
	 * @param $hookName string
	 * @param $args array
	 * @return string
	 */
	function callback($hookName, $args) {
		$params =& $args[0];
		$smarty =& $args[1];
		$output =& $args[2];
		$output .= $this->getContents($smarty, Application::getRequest());
		return false;
	}

	/*
	 * Private helper methods
	 */
	/**
	 * The application specific context home page hook name.
	 *
	 * @return string
	 */
	function _getContextSpecificHomepageHook() {
		$application = Application::getApplication();

		if ($application->getContextDepth() == 0) return null;

		$contextList = $application->getContextList();
		return 'Templates::Index::'.array_shift($contextList);
	}
}

?>
