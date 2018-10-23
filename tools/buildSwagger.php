<?php

/**
 * @file tools/buildSwagger.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class buildSwagger
 * @ingroup tools
 *
 * @brief CLI tool to compile a complete swagger.json file for hosting API
 *  documentation.
 */
define('APP_ROOT', dirname(dirname(dirname(dirname(__FILE__)))));
require(APP_ROOT . '/tools/bootstrap.inc.php');

class buildSwagger extends CommandLineTool {

	var $outputFile;
	var $parameters;

	/**
	 * Constructor.
	 * @param $argv array command-line arguments (see usage)
	 */
	function __construct($argv = array()) {
		parent::__construct($argv);
		$this->outputFile = array_shift($this->argv);
	}

	/**
	 * Print command usage information.
	 */
	function usage() {
		echo "Command-line tool to compile swagger.json API definitions\n"
			. "Usage:\n"
			. "\t{$this->scriptName} [outputFile]: Compile swagger file and save to [outputFile]\n"
			. "\t{$this->scriptName} usage: Display usage information this tool\n";
	}

	/**
	 * Parse and execute the import/export task.
	 */
	function execute() {
		if (empty($this->outputFile)) {
			$this->usage();
			exit();
		} elseif ((file_exists($this->outputFile) && !is_writable($this->outputFile)) ||
				(!is_writeable(dirname($this->outputFile)))) {
			echo "You do not have permission to write to this file.\n";
			exit;
		} else {
			$source = file_get_contents(APP_ROOT . '/docs/dev/swagger-source.json');
			if (!$source) {
				$this->usage();
				exit;
			}

			import('classes.core.ServicesContainer');
			$locales = ['en_US', 'fr_CA'];

			$apiSchema = json_decode($source);
			foreach ($apiSchema->definitions as $definitionName => $definition) {
				// We assume a definition that is not a string does not need to be compiled
				// from the schema files. It has already been defined.
				if (!is_string($definition)) {
					continue;
				}

				$editDefinition = $summaryDefinition = $readDefinition = ['type' => 'object', 'properties' => []];
				$entitySchema = \ServicesContainer::instance()->get('schema')->get($definition, true);
				foreach ($entitySchema->properties as $propName => $propSchema) {

					// Skip prop schemas with a `$ref`. They are already set up for the
					// API docs but have no been converted to use SchemaDAO yet.
					if (!empty($propSchema->{'$ref'})) {
						continue;
					}

					$editPropSchema = clone $propSchema;
					$readPropSchema = clone $propSchema;
					$summaryPropSchema = clone $propSchema;

					// Special handling to catch readOnly, writeOnly and apiSummary props in objects
					if ($propSchema->type === 'object') {
						$subPropsEdit = $subPropsRead = $subPropsSummary = [];
						foreach ($propSchema->properties as $subPropName => $subPropSchema) {
							if (empty($subPropSchema->readOnly)) {
								$subPropsEdit[$subPropName] = $subPropSchema;
							}
							if (empty($subPropSchema->writeOnly)) {
								$subPropsRead[$subPropName] = $subPropSchema;
							}
							if (!empty($subPropSchema->apiSummary)) {
								$subPropsSummary[$subPropName] = $subPropSchema;
							}
						}
						if (!empty($propSchema->multilingual)) {
							$subPropsSchemaEdit = $subPropsSchemaRead = $subPropsSchemaSummary = [
								'type' => 'object',
								'properties' => [],
							];
							foreach ($locales as $localeKey) {
								$subPropsSchemaEdit[$localeKey]['properties'] = $subPropsEdit;
								$subPropsSchemaRead[$localeKey]['properties'] = $subPropsRead;
								$subPropsSchemaSummary[$localeKey]['properties'] = $subPropsSummary;
							}
						} else {
							$subPropsSchemaEdit = $subPropsEdit;
							$subPropsSchemaRead = $subPropsRead;
							$subPropsSchemaSummary = $subPropsSummary;
						}
						if (empty($propSchema->readOnly)) {
							$editPropSchema->properties = $subPropsSchemaEdit;
						}
						if (empty($propSchema->writeOnly)) {
							$readPropSchema->properties = $subPropsSchemaRead;
						}
						if (!empty($propSchema->apiSummary)) {
							$summaryPropSchema->properties = $subPropsSchemaSummary;
						}

					// All non-object props
					} else {
						if (!empty($propSchema->multilingual)) {
							if ($propSchema->type === 'array') {
								$subProperties = [];
								foreach ($locales as $localeKey) {
									$subProperties[$localeKey] = $propSchema->items;
								}
								if (empty($propSchema->readOnly)) {
									$editPropSchema->properties = $subProperties;
								}
								if (empty($propSchema->writeOnly)) {
									$readPropSchema->properties = $subProperties;
								}
								if (!empty($propSchema->apiSummary)) {
									$summaryPropSchema->properties = $subProperties;
								}
							} else {
								if (empty($propSchema->readOnly)) {
									$editPropSchema = ['$ref' => '#/definitions/LocaleObject'];
								}
								if (empty($propSchema->writeOnly)) {
									$readPropSchema = ['$ref' => '#/definitions/LocaleObject'];
								}
								if (!empty($propSchema->apiSummary)) {
									$summaryPropSchema = ['$ref' => '#/definitions/LocaleObject'];
								}
							}
						}
					}

					if (empty($propSchema->readOnly)) {
						$editDefinition['properties'][$propName] = $editPropSchema;
					}
					if (empty($propSchema->writeOnly)) {
						$readDefinition['properties'][$propName] = $readPropSchema;
					}
					if (!empty($propSchema->apiSummary)) {
						$summaryDefinition['properties'][$propName] = $summaryPropSchema;
					}
				}
				if (!empty($editDefinition['properties'])) {
					$definitionEditableName = $definitionName . 'Editable';
					ksort($editDefinition['properties']);
					$apiSchema->definitions->{$definitionEditableName} = $editDefinition;
				}
				if (!empty($readDefinition['properties'])) {
					ksort($readDefinition['properties']);
					$apiSchema->definitions->{$definitionName} = $readDefinition;
				}
				if (!empty($summaryDefinition['properties'])) {
					$definitionSummaryName = $definitionName . 'Summary';
					ksort($summaryDefinition['properties']);
					$apiSchema->definitions->{$definitionSummaryName} = $summaryDefinition;
				}
			}

			file_put_contents($this->outputFile, json_encode($apiSchema, JSON_PRETTY_PRINT));

			echo "Done\n";
		}
	}

}

$tool = new buildSwagger(isset($argv) ? $argv : array());
$tool->execute();
?>
