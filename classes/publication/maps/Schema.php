<?php
/**
 * @file classes/publication/maps/Schema.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
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
use Illuminate\Support\LazyCollection;
use PKP\citation\CitationDAO;
use PKP\context\Context;
use PKP\db\DAORegistry;
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

    /** @var bool */
    public $anonymize;

    /** @var LazyCollection UserGroup The user groups for this context. */
    public $userGroups;

    /** @var Genre[] The file genres for this context. */
    public array $genres;

    public function __construct(Submission $submission, LazyCollection $userGroups, array $genres, Request $request, Context $context, PKPSchemaService $schemaService)
    {
        parent::__construct($request, $context, $schemaService);
        $this->submission = $submission;
        $this->userGroups = $userGroups;
        $this->genres = $genres;
    }

    /**
     * Map a publication
     *
     * Includes all properties in the publication schema.
     */
    public function map(Publication $item, bool $anonymize = false): array
    {
        return $this->mapByProperties($this->getProps(), $item, $anonymize);
    }

    /**
     * Summarize a publication
     *
     * Includes properties with the apiSummary flag in the publication schema.
     */
    public function summarize(Publication $item, bool $anonymize = false): array
    {
        return $this->mapByProperties($this->getSummaryProps(), $item, $anonymize);
    }

    /**
     * Map a collection of Publications
     *
     * @see self::map
     */
    public function mapMany(Enumerable $collection, bool $anonymize = false): Enumerable
    {
        $this->collection = $collection;
        return $collection->map(function ($item) use ($anonymize) {
            return $this->map($item, $anonymize);
        });
    }

    /**
     * Summarize a collection of Publications
     *
     * @see self::summarize
     */
    public function summarizeMany(Enumerable $collection, bool $anonymize = false): Enumerable
    {
        $this->collection = $collection;
        return $collection->map(function ($item) use ($anonymize) {
            return $this->summarize($item, $anonymize);
        });
    }

    /**
     * Map schema properties of a Publication to an assoc array
     */
    protected function mapByProperties(array $props, Publication $publication, bool $anonymize): array
    {
        $this->anonymize = $anonymize;

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
                    if ($this->anonymize) {
                        $output[$prop] = [];
                    } else {
                        $output[$prop] = Repo::author()->getSchemaMap()->summarizeMany($publication->getData('authors'))->values();
                    }
                    break;
                case 'authorsString':
                    $output[$prop] = $this->anonymize ? '' : $publication->getAuthorString($this->userGroups);
                    break;
                case 'authorsStringIncludeInBrowse':
                    $output[$prop] = $this->anonymize ? '' : $publication->getAuthorString($this->userGroups, true);
                    break;
                case 'authorsStringShort':
                    $output[$prop] = $this->anonymize ? '' : $publication->getShortAuthorString();
                    break;
                case 'categoryIds':
                    $output[$prop] = $publication->getData('categoryIds');
                    break;
                case 'citations':
                    $citationDao = DAORegistry::getDAO('CitationDAO'); /** @var CitationDAO $citationDao */
                    $output[$prop] = array_map(
                        function ($citation) {
                            return $citation->getCitationWithLinks();
                        },
                        $citationDao->getByPublicationId($publication->getId())->toArray()
                    );
                    break;
                case 'doiObject':
                    if ($publication->getData('doiObject')) {
                        $retVal = Repo::doi()->getSchemaMap()->summarize($publication->getData('doiObject'));
                    } else {
                        $retVal = null;
                    }

                    $output[$prop] = $retVal;
                    break;
                case 'fullTitle':
                    $output[$prop] = $publication->getFullTitles('html');
                    break;
                default:
                    $output[$prop] = $publication->getData($prop);
                    break;
            }
        }

        return $output;
    }
}
