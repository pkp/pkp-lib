<?php

/**
 * @file classes/bodyText/Repository.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Repository
 *
 * @brief A repository to find and manage body text files.
 */

namespace PKP\bodyText;

use APP\core\Application;
use APP\facades\Repo;
use APP\publication\Publication;
use APP\submission\Submission;
use Exception;
use Illuminate\Support\Facades\DB;
use PKP\context\Context;
use PKP\core\PKPApplication;
use PKP\db\DAORegistry;
use PKP\galley\Galley;
use PKP\submission\Representation;
use PKP\submissionFile\SubmissionFile;
use PKP\user\User;

class Repository
{
    /**
     * Map the BodyTextFile to an array including body text content and dependent files
     */
    public function map(BodyTextFile $bodyTextFile): array
    {
        $fileProps = [];

        if ($bodyTextFile->submissionFile) {
            $submission = Repo::submission()->get($bodyTextFile->submissionFile->getData('submissionId'));
            $schemaMap = Repo::submissionFile()->getSchemaMap($submission, []);

            $fileProps = $schemaMap->map($bodyTextFile->submissionFile);
        }

        if ($bodyTextFile->bodyTextContent) {
            $fileProps['bodyTextContent'] = $bodyTextFile->bodyTextContent;
        }

        if ($bodyTextFile->loadingContentError) {
            $fileProps['loadingContentError'] = $bodyTextFile->loadingContentError;
        }

        return $fileProps;
    }

    /**
     * Returns the SubmissionFile, if any, that corresponds to the body text contents of the given submission/publication
     */
    public function getBodyTextFile(int $publicationId): BodyTextFile
    {
        $submissionFile = Repo::submissionFile()
            ->getCollector()
            ->filterByFileStages([SubmissionFile::SUBMISSION_FILE_BODY_TEXT])
            ->filterByAssoc(Application::ASSOC_TYPE_PUBLICATION, [$publicationId])
            ->getMany()
            ->first();

        return new BodyTextFile(
            $publicationId,
            $submissionFile,
        );
    }

    /**
     * Create or update the body text file for a publication
     */
    public function setBodyText(
        string $bodyText,
        int $publicationId,
    ): BodyTextFile {
        $publication = Repo::publication()->get($publicationId);
        $submission = Repo::submission()->get($publication->getData('submissionId'));

        $existingBodyTextFile = $this->getBodyTextFile($publicationId);

        if ($existingBodyTextFile->submissionFile) {
            return $this->updateBodyTextFile($existingBodyTextFile->submissionFile, $bodyText, $submission);
        }

        $context = Application::get()->getRequest()->getContext();
        $user = Application::get()->getRequest()->getUser();

        return $this->createBodyTextFile($bodyText, $publication, $submission, $context, $user);
    }

    /**
     * Store body text content to file storage
     */
    protected function storeContent(string $bodyText, Submission $submission): int
    {
        $submissionDir = Repo::submissionFile()->getSubmissionDir(
            $submission->getData('contextId'),
            $submission->getId()
        );

        return app()->get('file')->addFromString(
            $bodyText,
            $submissionDir . '/' . uniqid() . '.json',
            'application/json'
        );
    }

    /**
     * Update existing body text file content
     */
    protected function updateBodyTextFile(
        SubmissionFile $submissionFile,
        string $bodyText,
        Submission $submission,
    ): BodyTextFile {
        $newFileId = $this->storeContent($bodyText, $submission);

        $oldFileId = $submissionFile->getData('fileId');
        Repo::submissionFile()->edit($submissionFile, ['fileId' => $newFileId]);
        app()->get('file')->delete($oldFileId);

        return $this->getBodyTextFile($submissionFile->getData('assocId'));
    }

    /**
     * Create new body text file
     */
    protected function createBodyTextFile(
        string $bodyText,
        Publication $publication,
        Submission $submission,
        Context $context,
        User $user,
    ): BodyTextFile {
        $fileId = $this->storeContent($bodyText, $submission);

        $primaryLocale = $context->getPrimaryLocale();
        $allowedLocales = $context->getData('supportedSubmissionLocales');

        $params = [
            'fileId' => $fileId,
            'submissionId' => $submission->getId(),
            'uploaderUserId' => $user->getId(),
            'fileStage' => SubmissionFile::SUBMISSION_FILE_BODY_TEXT,
            'name' => [$primaryLocale => 'bodyText.json'],
            'assocType' => Application::ASSOC_TYPE_PUBLICATION,
            'assocId' => $publication->getId(),
        ];

        $errors = Repo::submissionFile()->validate(
            null,
            $params,
            $allowedLocales,
            $primaryLocale
        );

        if (!empty($errors)) {
            app()->get('file')->delete($fileId);
            throw new Exception(print_r($errors, true));
        }

        $submissionFile = Repo::submissionFile()->newDataObject($params);
        Repo::submissionFile()->add($submissionFile);

        return $this->getBodyTextFile($publication->getId());
    }

    /**
     * Get all valid file stages
     *
     * Valid file stages should be passed through
     * the hook SubmissionFile::fileStages.
     */
    public function getFileStages(): array
    {
        return [SubmissionFile::SUBMISSION_FILE_BODY_TEXT];
    }

    /**
     * Create a PDF galley or add a revision to its existing proof file.
     */
    public function createPdfGalley(string $pdf, int $publicationId): Galley
    {
        return $this->createGalleyFile($pdf, $publicationId, 'PDF', 'pdf', 'application/pdf');
    }

    /**
     * Create an HTML galley or add a revision without changing the editor's JSON document.
     */
    public function createHtmlFile(string $html, int $publicationId): Galley
    {
        return $this->createGalleyFile($html, $publicationId, 'HTML', 'html', 'text/html');
    }

    /**
     * Store generated content as a galley proof file, preserving previous revisions.
     */
    protected function createGalleyFile(string $content, int $publicationId, string $label, string $extension, string $mimeType): Galley
    {
        $publication = Repo::publication()->get($publicationId);
        $submission = Repo::submission()->get($publication->getData('submissionId'));
        $context = Application::get()->getRequest()->getContext();
        $user = Application::get()->getRequest()->getUser();
        $primaryGenres = DAORegistry::getDAO('GenreDAO')->getPrimaryByContextId($context->getId())->toArray();
        $genreId = null;
        foreach ($primaryGenres as $genre) {
            $genreId ??= $genre->getId();
            if ($genre->getKey() === 'SUBMISSION') {
                $genreId = $genre->getId();
                break;
            }
        }
        if (!$genreId) {
            throw new \Exception('No primary file genre is configured for this context.');
        }

        $submissionDir = Repo::submissionFile()->getSubmissionDir(
            $submission->getData('contextId'),
            $submission->getId()
        );
        $fileId = app()->get('file')->addFromString(
            $content,
            $submissionDir . '/' . uniqid() . '.' . $extension,
            $mimeType
        );

        $galleyId = DB::transaction(function () use ($fileId, $submission, $publication, $user, $context, $publicationId, $label, $extension, $mimeType, $genreId) {
            // Serialize conversions for this publication so simultaneous first exports
            // do not create duplicate galleys.
            DB::table('publications')->where('publication_id', $publicationId)->lockForUpdate()->first();
            $locale = $publication->getData('locale') ?: $context->getPrimaryLocale();
            $galleys = Repo::galley()->getCollector()->filterByPublicationIds([$publicationId])->getMany();
            foreach ($galleys as $galley) {
                if ($galley->getData('label') !== $label || $galley->getData('locale') !== $locale || $galley->getData('urlRemote')) {
                    continue;
                }
                $submissionFileId = $galley->getData('submissionFileId');
                $submissionFile = $submissionFileId ? Repo::submissionFile()->get($submissionFileId) : null;
                if (!$submissionFile || $submissionFile->getData('submissionId') !== $submission->getId() || $submissionFile->getData('mimetype') !== $mimeType) {
                    continue;
                }

                $params = ['fileId' => $fileId];
                if (!$submissionFile->getGenreId()) {
                    $params['genreId'] = $genreId;
                }
                $errors = Repo::submissionFile()->validate(
                    $submissionFile,
                    $params,
                    $context->getData('supportedSubmissionLocales'),
                    $context->getPrimaryLocale()
                );
                if (!empty($errors)) {
                    throw new \Exception(json_encode($errors));
                }

                // The DAO adds submission_file_revisions when fileId changes.
                Repo::submissionFile()->edit($submissionFile, $params);
                Repo::galley()->edit($galley, [
                    'isApproved' => false,
                    'generatedFrom' => Representation::GENERATED_FROM_EDITOR,
                ]);
                return $galley->getId();
            }

            $params = [
                'fileId' => $fileId,
                'submissionId' => $submission->getId(),
                'uploaderUserId' => $user->getId(),
                'fileStage' => SubmissionFile::SUBMISSION_FILE_PROOF,
                'genreId' => $genreId,
                'name' => [$context->getPrimaryLocale() => 'bodyText-' . $publicationId . '.' . $extension],
            ];
            $errors = Repo::submissionFile()->validate(
                null,
                $params,
                $context->getData('supportedSubmissionLocales'),
                $context->getPrimaryLocale()
            );
            if (!empty($errors)) {
                throw new \Exception(json_encode($errors));
            }

            $submissionFile = Repo::submissionFile()->newDataObject($params);
            $submissionFileId = Repo::submissionFile()->add($submissionFile);

            $params = [
                'publicationId' => $publicationId,
                'label' => $label,
                'locale' => $locale,
                'submissionFileId' => $submissionFileId,
                'isApproved' => false,
            ];
            $errors = Repo::galley()->validate(
                null,
                $params,
                $context->getData('supportedSubmissionLocales'),
                $context->getPrimaryLocale()
            );
            if (!empty($errors)) {
                throw new \Exception(json_encode($errors));
            }

            $galley = Repo::galley()->newDataObject($params);
            $galley->setData('generatedFrom', Representation::GENERATED_FROM_EDITOR);
            $galleyId = Repo::galley()->add($galley);
            Repo::submissionFile()->edit(Repo::submissionFile()->get($submissionFileId), [
                'assocType' => PKPApplication::ASSOC_TYPE_REPRESENTATION,
                'assocId' => $galleyId,
            ]);

            return $galleyId;
        });


        if (!$galleyId) {
            app()->get('file')->delete($fileId);
        }

        return Repo::galley()->get($galleyId);
    }

}
