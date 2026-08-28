<?php

/**
 * @file classes/publication/maps/Schema.php
 *
 * Copyright (c) 2014-2026 Simon Fraser University
 * Copyright (c) 2000-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Schema
 *
 * @brief Map publications to the properties defined in the publication schema
 */

namespace PKP\publication\maps;

use APP\core\Request;
use APP\facades\Repo;
use APP\publication\Publication;
use APP\submission\Submission;
use Illuminate\Support\Enumerable;
use PKP\context\Context;
use PKP\services\PKPSchemaService;
use PKP\submission\Genre;

class Schema extends \PKP\core\maps\Schema
{
    /**  */
    public Enumerable $collection;

    /**  */
    public string $schema = PKPSchemaService::SCHEMA_PUBLICATION;

    /** @var Submission */
    public $submission;

    /** @var Genre[] The file genres for this context. */
    public array $genres;

    /** @var array<int, array>|null Cached review DOI data keyed by publicationId */
    protected ?array $reviewDoiItemsCache = null;

    public function __construct(Submission $submission, array $genres, Request $request, Context $context, PKPSchemaService $schemaService)
    {
        parent::__construct($request, $context, $schemaService);
        $this->submission = $submission;
        $this->genres = $genres;
    }

    /**
     * Map a publication
     *
     * Includes all properties in the publication schema.
     */
    public function map(Publication $item, bool $anonymizeAuthors = false): array
    {
        return $this->mapByProperties($this->getProps(), $item, $anonymizeAuthors);
    }

    /**
     * Summarize a publication
     *
     * Includes properties with the apiSummary flag in the publication schema.
     */
    public function summarize(Publication $item, bool $anonymizeAuthors = false): array
    {
        return $this->mapByProperties($this->getSummaryProps(), $item, $anonymizeAuthors);
    }

    /**
     * Map a collection of Publications
     *
     * @see self::map
     */
    public function mapMany(Enumerable $collection, bool $anonymizeAuthors = false): Enumerable
    {
        $this->collection = $collection;
        return $collection->map(function ($item) use ($anonymizeAuthors) {
            return $this->map($item, $anonymizeAuthors);
        });
    }

    /**
     * Summarize a collection of Publications
     *
     * @see self::summarize
     */
    public function summarizeMany(Enumerable $collection, bool $anonymizeAuthors = false): Enumerable
    {
        $this->collection = $collection;
        return $collection->map(function ($item) use ($anonymizeAuthors) {
            return $this->summarize($item, $anonymizeAuthors);
        });
    }

    /**
     * Get and cache review DOI data for all publications in the current collection.
     *
     * @throws \Exception
     *
     * @return array<int, array> Review DOI entries keyed by publication ID
     */
    protected function getReviewDoiItemsCache(Publication $publication): array
    {
        if ($this->reviewDoiItemsCache === null) {
            $publicationIds = isset($this->collection) && $this->collection->isNotEmpty()
                ? $this->collection->map(fn ($pub) => $pub->getId())->toArray()
                : [$publication->getId()];

            $this->reviewDoiItemsCache = Repo::publication()->getReviewDoiItemsGroupedByPublication($publicationIds);
        }

        return $this->reviewDoiItemsCache;
    }

    /**
     * Map schema properties of a Publication to an assoc array
     */
    protected function mapByProperties(array $props, Publication $publication, bool $anonymizeAuthors): array
    {
        $output = [];

        foreach ($props as $prop) {
            switch ($prop) {
                case '_href':
                    $output[$prop] = $this->getApiUrl(
                        'submissions/' . $publication->getData('submissionId') . '/publications/' . $publication->getId(),
                        $this->context->getData('urlPath')
                    );
                    break;
                case 'authors':
                    if ($anonymizeAuthors) {
                        $output[$prop] = [];
                    } else {
                        $output[$prop] = Repo::author()->getSchemaMap($this->submission)
                            ->summarizeMany($publication->getData('authors'))->values();
                    }
                    break;
                case 'authorsString':
                    $output[$prop] = $anonymizeAuthors ? '' : $publication->getAuthorString();
                    break;
                case 'authorsStringIncludeInBrowse':
                    $output[$prop] = $anonymizeAuthors ? '' : $publication->getAuthorString(true);
                    break;
                case 'authorsStringShort':
                    $output[$prop] = $anonymizeAuthors ? '' : $publication->getShortAuthorString();
                    break;
                case 'categoryIds':
                    $output[$prop] = iterator_to_array($publication->getData('categoryIds'));
                    break;
                case 'citations':
                    $data = [];
                    foreach ($publication->getData('citations') as $citation) {
                        $data[] = Repo::citation()->getSchemaMap()->map($citation);
                    }
                    $output[$prop] = $data;
                    break;
                case 'citationsRaw':
                    $output[$prop] = Repo::citation()->getRawCitationsByPublicationId($publication->getId())->implode(PHP_EOL);
                    break;
                case 'dataAvailability':
                    $output[$prop] = $anonymizeAuthors ? null : $publication->getData('dataAvailability');
                    break;
                case 'dataCitations':
                    $data = [];

                    if (!$anonymizeAuthors) {
                        foreach ($publication->getData('dataCitations') as $dataCitation) {
                            $data[] = Repo::dataCitation()->getSchemaMap()->map($dataCitation);
                        }
                    }

                    $output[$prop] = $data;
                    break;
                case 'doiObject':
                    if ($publication->getData('doiObject')) {
                        $retVal = Repo::doi()->getSchemaMap()->summarize($publication->getData('doiObject'));
                    } else {
                        $retVal = null;
                    }
                    $output[$prop] = $retVal;
                    break;
                case 'fundingStatement':
                    $output[$prop] = $anonymizeAuthors ? null : $publication->getData('fundingStatement');
                    break;
                case 'reviewDoiItems':
                    $reviewDoiItemsByPub = $this->getReviewDoiItemsCache($publication);
                    $entries = $reviewDoiItemsByPub[$publication->getId()] ?? [];
                    $output[$prop] = array_map(function ($entry) {
                        return [
                            'pubObjectType' => $entry['pubObjectType'],
                            'pubObjectId' => $entry['pubObjectId'],
                            'doiObject' => $entry['doiObject']
                                ? Repo::doi()->getSchemaMap()->summarize($entry['doiObject'])
                                : null,
                        ];
                    }, $entries);
                    break;
                case 'fullTitle':
                    $output[$prop] = $publication->getFullTitles('html');
                    break;
                case 'versionString':
                    $output[$prop] = Repo::publication()->getVersionString($publication);
                    break;
                default:
                    $output[$prop] = $publication->getData($prop);
                    break;
            }
        }

        return $output;
    }
}
