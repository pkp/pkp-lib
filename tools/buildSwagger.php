<?php

/**
 * @file tools/buildSwagger.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class buildSwagger
 *
 * @ingroup tools
 *
 * @brief CLI tool to compile a complete swagger.json file for hosting API
 *  documentation.
 */

use APP\core\Services;
use PKP\decision\DecisionType;
use PKP\file\FileManager;

define('APP_ROOT', dirname(__FILE__, 4));
require(APP_ROOT . '/tools/bootstrap.php');

class buildSwagger extends \PKP\cliTool\CommandLineTool
{
    public $outputFile;
    public $parameters;

    /**
     * Constructor.
     *
     * @param array $argv command-line arguments (see usage)
     */
    public function __construct($argv = [])
    {
        parent::__construct($argv);
        $this->outputFile = array_shift($this->argv);
    }

    /**
     * Print command usage information.
     */
    public function usage()
    {
        echo "Command-line tool to compile swagger.json API definitions\n"
            . "Usage:\n"
            . "\t{$this->scriptName} [outputFile]: Compile swagger file and save to [outputFile]\n"
            . "\t{$this->scriptName} usage: Display usage information this tool\n";
    }

    /**
     * Parse and execute the import/export task.
     */
    public function execute()
    {
        if (empty($this->outputFile)) {
            $this->usage();
            exit;
        } elseif ((file_exists($this->outputFile) && !is_writable($this->outputFile)) ||
                (!is_writeable(dirname($this->outputFile)))) {
            echo "You do not have permission to write to this file.\n";
            exit;
        } else {
            $source = file_get_contents(APP_ROOT . '/docs/dev/swagger-source.json');
            $decisions = file_get_contents(APP_ROOT . '/docs/dev/swagger-source-decision-examples.json');
            if (!$source || !$decisions) {
                echo 'Unable to find source files at ' . APP_ROOT . '/docs/dev/';
                exit;
            }

            $locales = ['en', 'fr_CA'];

            $apiSchema = json_decode($source);
            foreach ($apiSchema->definitions as $definitionName => $definition) {
                // We assume a definition that is not a string does not need to be compiled
                // from the schema files. It has already been defined.
                if (!is_string($definition)) {
                    continue;
                }

                $editDefinition = $summaryDefinition = $readDefinition = ['type' => 'object', 'properties' => []];
                $entitySchema = Services::get('schema')->get($definition, true);
                foreach ($entitySchema->properties as $propName => $propSchema) {
                    $editPropSchema = clone $propSchema;
                    $readPropSchema = clone $propSchema;
                    $summaryPropSchema = clone $propSchema;

                    // Special handling to catch readOnly, writeOnly and apiSummary props in objects
                    if (!empty($propSchema->{'$ref'})) {
                        if (empty($propSchema->readOnly) || empty($propSchema->writeDisabledInApi)) {
                            $editPropSchema->properties = $propSchema;
                        }
                        if (empty($propSchema->writeOnly)) {
                            $readPropSchema->properties = $propSchema;
                        }
                        if (!empty($propSchema->apiSummary)) {
                            $summaryPropSchema->properties = $propSchema;
                        }
                    } elseif ($propSchema->type === 'object') {
                        $subPropsEdit = $subPropsRead = $subPropsSummary = [];
                        foreach ($propSchema->properties as $subPropName => $subPropSchema) {
                            if (empty($subPropSchema->readOnly) && empty($propSchema->writeDisabledInApi)) {
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
                        if (empty($propSchema->readOnly) && empty($propSchema->writeDisabledInApi)) {
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
                                if (empty($propSchema->readOnly) && empty($propSchema->writeDisabledInApi)) {
                                    $editPropSchema->properties = $subProperties;
                                }
                                if (empty($propSchema->writeOnly)) {
                                    $readPropSchema->properties = $subProperties;
                                }
                                if (!empty($propSchema->apiSummary)) {
                                    $summaryPropSchema->properties = $subProperties;
                                }
                            } else {
                                if (empty($propSchema->readOnly) && empty($propSchema->writeDisabledInApi)) {
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

                    if (empty($propSchema->readOnly) && empty($propSchema->writeDisabledInApi)) {
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
                    $apiSchema->definitions->{$definitionEditableName} = $this->setEnum($editDefinition);
                }
                if (!empty($readDefinition['properties'])) {
                    ksort($readDefinition['properties']);
                    $apiSchema->definitions->{$definitionName} = $this->setEnum($readDefinition);
                }
                if (!empty($summaryDefinition['properties'])) {
                    $definitionSummaryName = $definitionName . 'Summary';
                    ksort($summaryDefinition['properties']);
                    $apiSchema->definitions->{$definitionSummaryName} = $this->setEnum($summaryDefinition);
                }
            }

            $this->addDecisionExamples($apiSchema, json_decode($decisions));

            file_put_contents($this->outputFile, json_encode($apiSchema, JSON_PRETTY_PRINT));

            echo "Done\n";
        }
    }

    /**
     * Convert the `in:` validation rules to swagger's
     * `enum` specification
     */
    protected function setEnum(array $definition): array
    {
        foreach ($definition['properties'] as $propName => $schema) {
            if (isset($schema->validation) && is_array($schema->validation)) {
                foreach ($schema->validation as $rule) {
                    if (substr($rule, 0, 3) === 'in:') {
                        $enum = explode(',', substr($rule, 3));
                        if ($schema->type === 'integer') {
                            $enum = array_map('intval', $enum);
                        }
                        $definition['properties'][$propName]->enum = $enum;
                    }
                }
            }
        }

        return $definition;
    }

    /**
     * Add the example request bodies for each decision
     */
    protected function addDecisionExamples(stdClass $schema, stdClass $decisions): void
    {
        $examples = [];
        foreach ($decisions as $class => $decision) {
            /** @var DecisionType $object */
            $object = new $class();

            $value = [
                'decision' => $object->getDecision(),
            ];

            if ($this->isDecisionInReview($object)) {
                $value['reviewRound'] = 123;
                $value['round'] = 1;
            }

            if (!empty($decision->actions)) {
                $value['actions'] = array_map(
                    function (stdClass $action) {
                        if ($action->type === 'form') {
                            return array_merge((array) $action->data, ['id' => $action->id]);
                        } elseif ($action->type === 'email') {
                            return [
                                'attachments' => [
                                    [
                                        'name' => 'example-upload.pdf',
                                        'temporaryFileId' => 1,
                                        'documentType' => FileManager::DOCUMENT_TYPE_PDF
                                    ],
                                    [
                                        'name' => 'example-submission-file.pdf',
                                        'submissionFileId' => 1,
                                        'documentType' => FileManager::DOCUMENT_TYPE_PDF
                                    ],
                                    [
                                        'name' => 'example-library-file.pdf',
                                        'libraryFileId' => 1,
                                        'documentType' => FileManager::DOCUMENT_TYPE_PDF
                                    ]
                                ],
                                'bcc' => 'example@pkp.sfu.ca',
                                'cc' => 'example@pkp.sfu.ca',
                                'id' => $action->id,
                                'locale' => 'en',
                                'recipients' => $action->canChangeRecipients
                                    ? [1,2]
                                    : [],
                                'subject' => 'Example email subject',
                                'body' => '<p>Example email body.</p>',
                            ];
                        }
                        throw new Exception('Unrecognized decision action type. Can not compile example request body for decision ' . $class);
                    },
                    $decision->actions ?? []
                );
            }

            $examples[$class] = [
                'summary' => $object->getLabel(),
                'value' => $value,
            ];
        }

        $schema
            ->paths
            ->{'/submissions/{submissionId}/decisions'}
            ->post
            ->requestBody
            ->content
            ->{'application/json'}
            ->examples = $examples;
    }

    /**
     * Is the decision type in a review stage?
     */
    protected function isDecisionInReview(DecisionType $decision): bool
    {
        return in_array($decision->getStageId(), [WORKFLOW_STAGE_ID_INTERNAL_REVIEW, WORKFLOW_STAGE_ID_EXTERNAL_REVIEW]);
    }
}

$tool = new buildSwagger($argv ?? []);
$tool->execute();
