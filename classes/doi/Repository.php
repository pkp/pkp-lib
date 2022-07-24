<?php

namespace APP\doi;

use APP\core\Application;
use APP\core\Request;
use APP\facades\Repo;
use APP\plugins\PubIdPlugin;
use APP\publication\Publication;
use APP\server\ServerDAO;
use APP\submission\Submission;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use PKP\context\Context;
use PKP\core\DataObject;
use PKP\doi\Collector;
use PKP\galley\Galley;
use PKP\services\PKPSchemaService;
use PKP\submission\Representation;

class Repository extends \PKP\doi\Repository
{
    public function __construct(DAO $dao, Request $request, PKPSchemaService $schemaService)
    {
        parent::__construct($dao, $request, $schemaService);
    }

    public function getCollector(): Collector
    {
        return App::makeWith(Collector::class, ['dao' => $this->dao]);
    }

    /**
     * Create a DOI for the given publication
     */
    public function mintPublicationDoi(Publication $publication, Submission $submission, Context $context): ?int
    {
        if ($context->getData(Context::SETTING_DOI_SUFFIX_TYPE) === Repo::doi()::SUFFIX_DEFAULT) {
            $doiSuffix = $this->generateDefaultSuffix();
        } else {
            $doiSuffix = $this->generateSuffixPattern($publication, $context, $context->getData(Context::SETTING_DOI_SUFFIX_TYPE), $submission);
        }

        return $this->mintAndStoreDoi($context, $doiSuffix);
    }

    /**
     * Create a DOI for the given galley
     */
    public function mintGalleyDoi(Galley $galley, Publication $publication, Submission $submission, Context $context): ?int
    {
        if ($context->getData(Context::SETTING_DOI_SUFFIX_TYPE) === Repo::doi()::SUFFIX_DEFAULT) {
            $doiSuffix = $this->generateDefaultSuffix();
        } else {
            $doiSuffix = $this->generateSuffixPattern($galley, $context, $context->getData(Context::SETTING_DOI_SUFFIX_TYPE), $submission, $galley);
        }

        return $this->mintAndStoreDoi($context, $doiSuffix);
    }

    /**
     * Generate a suffix using a provided pattern type
     *
     * @param string $patternType Repo::doi()::CUSTOM_SUFFIX_* constants
     *
     */
    protected function generateSuffixPattern(
        DataObject $object,
        Context $context,
        string $patternType,
        ?Submission $submission = null,
        ?Representation $representation = null
    ): string {
        $doiSuffix = '';
        switch ($patternType) {
            case self::SUFFIX_CUSTOM_PATTERN:
                $pubIdSuffixPattern = $this->getPubIdSuffixPattern($object, $context);
                $publication = $submission !== null ? Repo::publication()->get($submission->getData('currentPublicationId')) : null;
                $doiSuffix = PubIdPlugin::generateCustomPattern($context, $pubIdSuffixPattern, $object, $submission, $publication, $representation);
                break;
            case self::SUFFIX_MANUAL:
                break;
        }

        return $doiSuffix;
    }

    /**
     * Get app-specific DOI type constants to check when scheduling deposit for submissions
     */
    protected function getValidSubmissionDoiTypes(): array
    {
        return [
            self::TYPE_PUBLICATION,
            self::TYPE_REPRESENTATION
        ];
    }

    /**
     * Gets all DOI IDs related to a submission
     *
     * @return array<int> DOI IDs
     */
    public function getDoisForSubmission(int $submissionId): array
    {
        $doiIds = Collection::make();

        $submission = Repo::submission()->get($submissionId);
        /** @var Publication[] $publications */
        $publications = $submission->getData('publications');

        /** @var ServerDAO $contextDao */
        $contextDao = Application::getContextDAO();
        $context = $contextDao->getById($submission->getData('contextId'));

        foreach ($publications as $publication) {
            $publicationDoiId = $publication->getData('doiId');
            if (!empty($publicationDoiId) && $context->isDoiTypeEnabled(self::TYPE_PUBLICATION)) {
                $doiIds->add($publicationDoiId);
            }

            // Galleys
            $galleys = Repo::galley()->getCollector()
                ->filterByPublicationIds(['publicationIds' => $publication->getId()])
                ->getMany();

            foreach ($galleys as $galley) {
                $galleyDoiId = $galley->getData('doiId');
                if (!empty($galleyDoiId) && $context->isDoiTypeEnabled(self::TYPE_REPRESENTATION)) {
                    $doiIds->add($galleyDoiId);
                }
            }
        }

        return $doiIds->unique()->toArray();
    }

    /**
     * Checks whether a DOI object is referenced by ID on any pub objects for a given pub object type.
     *
     * @param string $pubObjectType One of Repo::doi()::TYPE_* constants
     */
    public function isAssigned(int $doiId, string $pubObjectType): bool
    {
        $isAssigned = match ($pubObjectType) {
            Repo::doi()::TYPE_REPRESENTATION => Repo::galley()
                ->getCollector()
                ->filterByDoiIds([$doiId])
                ->getIds()
                ->count(),
            default => false,
        };

        return $isAssigned || parent::isAssigned($doiId, $pubObjectType);
    }

    /**
     *  Gets legacy, user-generated suffix pattern associated with object type and context
     *
     * @return mixed|null
     */
    private function getPubIdSuffixPattern(DataObject $object, Context $context)
    {
        if ($object instanceof Representation) {
            return $context->getData(Repo::doi()::CUSTOM_REPRESENTATION_PATTERN);
        } else {
            return $context->getData(Repo::doi()::CUSTOM_PUBLICATION_PATTERN);
        }
    }
}
