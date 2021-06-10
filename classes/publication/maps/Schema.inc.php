<?php
/**
 * @file classes/publication/maps/Schema.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class publication
 *
 * @brief Map publications to the properties defined in the publication schema
 */

namespace PKP\publication\maps;

use APP\core\Request;
use APP\core\Services;
use APP\publication\Publication;
use APP\submission\Submission;

use Illuminate\Support\Enumerable;

use PKP\context\Context;
use PKP\db\DAORegistry;
use PKP\services\PKPSchemaService;

class Schema extends \PKP\core\maps\Schema
{
    /** @var Enumerable */
    public $collection;

    /** @var string */
    public $schema = PKPSchemaService::SCHEMA_PUBLICATION;

    /** @var Submission */
    public $submission;

    /** @var bool */
    public $anonymize;

    /** @var array The user groups for this context. */
    public $userGroups;

    public function __construct(Submission $submission, array $userGroups, Request $request, Context $context, PKPSchemaService $schemaService)
    {
        parent::__construct($request, $context, $schemaService);
        $this->submission = $submission;
        $this->userGroups = $userGroups;
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
                        $output[$prop] = array_map(
                            function ($author) {
                                return Services::get('author')->getSummaryProperties($author, ['request' => $this->request]);
                            },
                            $publication->getData('authors')
                        );
                    }
                    break;
                case 'authorsString':
                    $output[$prop] = $this->anonymize ? '' : $publication->getAuthorString($this->userGroups);
                    break;
                case 'authorsStringShort':
                    $output[$prop] = $this->anonymize ? '' : $publication->getShortAuthorString();
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
                case 'fullTitle':
                    $output[$prop] = $publication->getFullTitles();
                    break;
                default:
                    $output[$prop] = $publication->getData($prop);
                    break;
            }
        }

        return $output;
    }
}
