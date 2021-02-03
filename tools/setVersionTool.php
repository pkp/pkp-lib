<?php

/**
 * @file tools/setVersionTool.php
 *
 * Copyright (c) 2013-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SetVersionTool
 * @ingroup tools
 *
 * @brief CLI tool to set a version number for each publication.
 */

require(dirname(dirname(dirname(dirname(__FILE__)))) . '/tools/bootstrap.inc.php');

class SetVersionTool extends CommandLineTool {

	/**
	 * Set the version numbers
	 */
	function execute() {
		$request = Application::get()->getRequest();
		$contextIds = Services::get('context')->getIds();
		foreach ($contextIds as $contextId) {
			$submissions = Services::get('submission')->getMany(['contextId' => $contextId]);
			foreach ($submissions as $submission) {
				$version = 1;
				foreach ((array) $submission->getData('publications') as $publication) {
					Services::get('publication')->edit($publication, ['version' => $version], $request);
					$version++;
				}
			}
		}
	}
}

$tool = new SetVersionTool(isset($argv) ? $argv : []);
$tool->execute();

