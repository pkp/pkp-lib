<?php

/**
 * @file classes/galley/maps/Schema.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Schema
 *
 * @brief Map galleys to the properties defined in the galley schema
 */

namespace PKP\galley\maps;

use APP\core\Request;
use APP\facades\Repo;
use APP\publication\Publication;
use APP\submission\Submission;
use Illuminate\Support\Enumerable;
use PKP\context\Context;
use PKP\core\PKPApplication;
use PKP\galley\Galley;
use PKP\services\PKPSchemaService;

class Schema extends \PKP\core\maps\Schema
{
    public Enumerable $collection;
    public array $genres = [];
    public Publication $publication;
    public string $schema = PKPSchemaService::SCHEMA_GALLEY;
    public Submission $submission;

    public function __construct(Submission $submission, Publication $publication, array $genres, Request $request, Context $context, PKPSchemaService $schemaService)
    {
        parent::__construct($request, $context, $schemaService);
        $this->publication = $publication;
        $this->submission = $submission;
        $this->genres = $genres;
    }
    /**
     * Map a galley
     *
     * Includes all properties in the galley schema.
     */
    public function map(Galley $item): array
    {
        return $this->mapByProperties($this->getProps(), $item);
    }

    /**
     * Summarize a galley
     *
     * Includes properties with the apiSummary flag in the galley schema.
     */
    public function summarize(Galley $item): array
    {
        return $this->mapByProperties($this->getSummaryProps(), $item);
    }

    /**
     * Map a collection of galleys
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
     * Summarize a collection of galleys
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
     * Map schema properties of a galley to an assoc array
     */
    protected function mapByProperties(array $props, Galley $galley): array
    {
        $output = [];
        foreach ($props as $prop) {
            switch ($prop) {
                case 'doiObject':
                    if ($galley->getData('doiObject')) {
                        $retVal = Repo::doi()->getSchemaMap()->summarize($galley->getData('doiObject'));
                    } else {
                        $retVal = null;
                    }
                    $output[$prop] = $retVal;
                    break;
                case 'file':
                    $output[$prop] = null;
                    if ($galley instanceof Galley) {
                        if (!$galley->getData('submissionFileId')) {
                            break;
                        }

                        $submissionFile = Repo::submissionFile()->get($galley->getData('submissionFileId'));

                        if (empty($submissionFile)) {
                            break;
                        }

                        $output[$prop] = Repo::submissionFile()
                            ->getSchemaMap($this->submission, $this->genres)
                            ->map($submissionFile);
                    }
                    break;
                case 'urlPublished':

                    $output['urlPublished'] = $this->request->getDispatcher()->url(
                        $this->request,
                        PKPApplication::ROUTE_PAGE,
                        $this->context->getData('urlPath'),
                        'article',
                        'view',
                        [
                            $this->submission->getBestId(),
                            'version',
                            $this->publication->getId(),
                            $galley->getBestGalleyId()
                        ]
                    );
                    break;
                default:
                    $output[$prop] = $galley->getData($prop);
                    break;
            }
        }

        $locales = $this->publication->getLanguages($this->context->getSupportedSubmissionMetadataLocales(), $galley->getLanguages());

        $output = $this->schemaService->addMissingMultilingualValues($this->schema, $output, $locales);

        ksort($output);

        return $this->withExtensions($output, $galley);
    }
}
