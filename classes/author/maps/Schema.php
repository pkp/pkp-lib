<?php

/**
 * @file classes/author/maps/Schema.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2000-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Schema
 *
 * @brief Map authors to the properties defined in the announcement schema
 */

namespace PKP\author\maps;

use APP\author\Author;
use APP\facades\Repo;
use Illuminate\Support\Enumerable;
use PKP\core\PKPRequest;
use PKP\security\Role;
use PKP\services\PKPSchemaService;
use PKP\userGroup\UserGroup;
use stdClass;

class Schema extends \PKP\core\maps\Schema
{
    public Enumerable $collection;

    public string $schema = PKPSchemaService::SCHEMA_AUTHOR;

    protected Enumerable $authorUserGroups;

    public function __construct(PKPRequest $request, \PKP\context\Context $context, PKPSchemaService $schemaService)
    {
        parent::__construct($request, $context, $schemaService);

        $this->authorUserGroups = UserGroup::withRoleIds([Role::ROLE_ID_AUTHOR])->withContextIds([$this->context->getId()])->get();
    }

    /**
     * Map an author
     *
     * Includes all properties in the announcement schema.
     */
    public function map(Author $item): array
    {
        return $this->mapByProperties($this->getProps(), $item);
    }

    /**
     * Summarize an author
     *
     * Includes properties with the apiSummary flag in the author schema.
     */
    public function summarize(Author $item): array
    {
        return $this->mapByProperties($this->getSummaryProps(), $item);
    }

    /**
     * Map a collection of Authors
     *
     * @see self::map
     */
    public function mapMany(Enumerable $collection): Enumerable
    {
        $this->collection = $collection;
        return $collection->map(function ($item) {
            return $this->map($item);
        });
    }

    /**
     * Summarize a collection of Authors
     *
     * @see self::summarize
     */
    public function summarizeMany(Enumerable $collection): Enumerable
    {
        $this->collection = $collection;
        return $collection->map(function ($item) {
            return $this->summarize($item);
        });
    }

    /**
     * Map schema properties of an Author to an assoc array
     */
    protected function mapByProperties(array $props, Author $item): array
    {
        $output = [];
        foreach ($props as $prop) {
            switch ($prop) {
                case 'userGroupName':
                    /** @var UserGroup $userGroup */
                    $userGroup = $this->authorUserGroups->first(fn (UserGroup $userGroup) => $userGroup->id === $item->getData('userGroupId'));
                    $output[$prop] = $userGroup ? $userGroup->name : new stdClass();
                    break;
                case 'fullName':
                    $output[$prop] = $item->getFullName();
                    break;
                case 'hasVerifiedOrcid':
                    $output[$prop] = $item->hasVerifiedOrcid();
                    break;
                case 'orcidDisplayValue':
                    $output[$prop] = $item->getOrcidDisplayValue();
                    break;
                case 'affiliations':
                    $data = [];
                    foreach ($item->getAffiliations() as $affiliation) {
                        $data[] = Repo::affiliation()->getSchemaMap()->map($affiliation);
                    }
                    $output[$prop] = $data;
                    break;
                default:
                    $output[$prop] = $item->getData($prop);
                    break;
            }
        }

        $locales = Repo::submission()->get(Repo::publication()->get($item->getData('publicationId'))->getData('submissionId'))->getPublicationLanguages($this->context->getSupportedSubmissionMetadataLocales());

        $output = $this->schemaService->addMissingMultilingualValues($this->schema, $output, $locales);

        ksort($output);

        return $this->withExtensions($output, $item);
    }
}
