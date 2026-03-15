<?php
/**
 * @file classes/dataCitation/Repository.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Repository
 *
 * @brief A helper class to handle operations with Data Citations
 */

namespace PKP\dataCitation;

use APP\core\Application;
use APP\core\Request;
use PKP\context\Context;
use PKP\dataCitation\DataCitation;
use PKP\plugins\Hook;
use PKP\services\PKPSchemaService;
use PKP\validation\ValidatorFactory;

class Repository
{
    /** @var string $schemaMap The name of the class to map this entity to its schema */
    public string $schemaMap = maps\Schema::class;

    protected Request $request;

    /** @var PKPSchemaService<DataCitation> $schemaService */
    protected PKPSchemaService $schemaService;

    public function __construct(Request $request, PKPSchemaService $schemaService)
    {
        $this->request = $request;
        $this->schemaService = $schemaService;
    }

    /**
     * Validate properties for a data citation
     *
     * Perform validation checks on data used to add or edit a data citation.
     *
     * @param DataCitation|null $dataCitation Data citation being edited. Pass `null` if creating a new citation
     * @param array $props A key/value array with the new data to validate
     *
     * @return array A key/value array with validation errors. Empty if no errors
     *
     * @hook DataCitation::validate [[&$errors, $dataCitation, $props]]
     */
    public function validate(?DataCitation $dataCitation, array $props): array
    {
        $schema = DataCitation::getSchemaName();
        $errors = [];

        $validator = ValidatorFactory::make(
            $props,
            $this->schemaService->getValidationRules($schema, [])
        );

        // Check required fields
        ValidatorFactory::required(
            $validator,
            $dataCitation,
            $this->schemaService->getRequiredProps($schema),
            $this->schemaService->getMultilingualProps($schema),
            [],
            ''
        );

        // Validate identifier format based on identifierType
        // Note that Accession does not have a standard to validate against
        $validator->after(function ($validator) use ($props) {
            $identifierType = $props['identifierType'] ?? null;
            $identifier = $props['identifier'] ?? null;

            if (!$identifierType || !$identifier || $validator->errors()->get('identifier')) {
                return;
            }

            $identifierRules = [
                'DOI'    => '/^10[.][0-9]{4,}\/[^\s"<>]+/i',
                'ARXIV'  => '/^(?:\d+\.\d+|[a-zA-Z.-]+\/\d+)/i',
                'Handle' => '/^[0-9a-z]+(?:\.[0-9a-z]+)*\/.+/i',
                'ISSN'   => '/^\d{4}-\d{3}[\dX]$/i',
                'ISBN'   => '/^(?:97[89])?\d{9}[\dX]$/i',
                'PMID'   => '/^\d+$/',
                'PMCID'  => '/^PMC\d+$/i',
                'UUID'   => '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
                'ARK'    => '/^ark:\/\d{5,}(\/.+)+$/i',
                'ECLI'   => '/^ECLI:[A-Z]{2}:[A-Z0-9.]+:\d{4}:[A-Z0-9.]+$/i',
                'URI'    => '/^https?:\/\/.+/i',
                'PURL'   => '/^https?:\/\/.+/i',
            ];

            $identifierBaseUrls = DataCitation::getIdentifierBaseUrls();

            if (isset($identifierBaseUrls[$identifierType])) {
                $identifier = preg_replace($identifierBaseUrls[$identifierType], '', $identifier);
            }

            if (isset($identifierRules[$identifierType])) {
                if (!preg_match($identifierRules[$identifierType], $identifier)) {
                    $validator->errors()->add('identifier', __('submission.dataCitations.identifier.invalid', [
                        'type' => $identifierType,
                        'value' => $identifier,
                    ]));
                }
            }
        });

        if ($validator->fails()) {
            $errors = $this->schemaService->formatValidationErrors($validator->errors());
        }

        Hook::call('DataCitation::validate', [&$errors, $dataCitation, $props]);

        return $errors;
    }

    /**
     * Get an instance of the map class for mapping
     * data citations to their schema
     */
    public function getSchemaMap(): maps\Schema
    {
        return app('maps')->withExtensions($this->schemaMap);
    }
}
