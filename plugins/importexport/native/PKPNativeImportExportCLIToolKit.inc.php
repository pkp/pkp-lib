<?php

/**
 * @file plugins/importexport/native/PKPNativeImportExportCLIToolKit.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPNativeImportExportCLIToolKit
 * @ingroup plugins_importexport_native
 *
 * @brief CLI Toolkit class
 */
use Colors\Color;

class PKPNativeImportExportCLIToolKit {

	/**
	 * Coloring CLI output object
	 * @var Color
	 */
	var $c = null;

	function __construct() {
		$this->c = new Color();
	}

	/**
	 * Echo a CLI Error Message
	 * @param $errorMessage string
	 */
	function echoCLIError($errorMessage) {
		echo $this->c(__('plugins.importexport.common.cliError'))->white()->bold()->highlight('red') . PHP_EOL;
		echo $this->c($errorMessage)->red()->bold() . PHP_EOL;
	}

	/**
	 * Echo export results
	 * @param $deployment PKPNativeImportExportDeployment
	 * @param $xmlFile string
	 */
	function getCLIExportResult($deployment, $xmlFile) {
		$result = $deployment->processResult;
		$foundErrors = $deployment->isProcessFailed();

		if (!$foundErrors) {
			$xml = $result->saveXml();
			file_put_contents($xmlFile, $xml);
			echo $this->c(__('plugins.importexport.native.export.completed'))->green()->bold() . PHP_EOL . PHP_EOL;
		} else {
			echo $this->c(__('plugins.importexport.native.processFailed'))->red()->bold() . PHP_EOL . PHP_EOL;
		}
	}

	/**
	 * Echo import results
	 * @param $deployment PKPNativeImportExportDeployment
	 */
	function getCLIImportResult($deployment) {
		$result = $deployment->processResult;
		$foundErrors = $deployment->isProcessFailed();
		$importedRootObjects = $deployment->getImportedRootEntitiesWithNames();

		if (!$foundErrors) {
			echo $this->c(__('plugins.importexport.native.importComplete'))->green()->bold() . PHP_EOL . PHP_EOL;

			foreach ($importedRootObjects as $contentItemName => $contentItemArrays) {
				echo $this->c($contentItemName)->white()->bold()->highlight('black') . PHP_EOL;
				foreach ($contentItemArrays as $contentItemArray) {
					foreach ($contentItemArray as $contentItem) {
						echo $this->c('-' . $contentItemName)->white()->bold() . PHP_EOL;
					}
				}
			}
		} else {
			echo $this->c(__('plugins.importexport.native.processFailed'))->red()->bold() . PHP_EOL . PHP_EOL;
		}
	}

	/**
	 * Echo import/export possible warnings and errors
	 * @param $deployment PKPNativeImportExportDeployment
	 */
	function getCLIProblems($deployment) {
		$result = $deployment->processResult;
		$problems = $deployment->getWarningsAndErrors();
		$foundErrors = $deployment->isProcessFailed();

		$warnings = array();
		if (array_key_exists('warnings', $problems)) {
			$warnings = $problems['warnings'];
		}

		$errors = array();
		if (array_key_exists('errors', $problems)) {
			$errors = $problems['errors'];
		}

		// Are there any import warnings? Display them.
		$this->displayCLIIssues($warnings, __('plugins.importexport.common.warningsEncountered'));
		$this->displayCLIIssues($errors, __('plugins.importexport.common.errorsOccured'));
	}

	/**
	 * Echo import/export possible warnings and errors
	 * @param $relatedIssues array
	 * @param $title string
	 */
	function displayCLIIssues($relatedIssues, $title) {
		if(count($relatedIssues) > 0) {
			echo $this->c($title)->black()->bold()->highlight('light_gray') . PHP_EOL;
			$i = 0;
			foreach($relatedIssues as $relatedTypeName => $allRelatedTypes) {
				foreach($allRelatedTypes as $thisTypeId => $thisTypeIds) {
					if(count($thisTypeIds) > 0) {
						echo ++$i . '.' . $relatedTypeName . PHP_EOL;
						foreach($thisTypeIds as $idRelatedItems) {
							foreach($idRelatedItems as $relatedItemMessage) {
								echo '- ' . $relatedItemMessage . PHP_EOL;
							}
						}
					}
				}
			}
		}
	}
}
