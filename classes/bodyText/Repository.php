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
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Masterminds\HTML5;
use PKP\context\Context;
use PKP\core\PKPApplication;
use PKP\db\DAORegistry;
use PKP\galley\Galley;
use PKP\submission\Genre;
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
     * Create a PDF galley or add a revision.
     *
     * @throws \Throwable
     */
    public function createPdfGalley(string $pdf, int $publicationId): Galley
    {
        return $this->createGalleyFile($pdf, $publicationId, 'PDF', 'pdf', 'application/pdf');
    }

    /**
     * Create an HTML galley or add a revision.
     *
     * @throws \Throwable
     */
    public function createHtmlFile(string $html, int $publicationId): Galley
    {
        return $this->createGalleyFile($html, $publicationId, 'HTML', 'html', 'text/html');
    }

    /**
     * Store generated content as a galley, preserving previous revisions.
     */
    protected function createGalleyFile(string $content, int $publicationId, string $label, string $extension, string $mimeType): Galley
    {
        $publication = Repo::publication()->get($publicationId);
        $submission = Repo::submission()->get($publication->getData('submissionId'));
        $context = Application::get()->getRequest()->getContext();
        $user = Application::get()->getRequest()->getUser();
        $primaryGenres = DAORegistry::getDAO('GenreDAO')->getPrimaryByContextId($context->getId())->toArray();
        $genre = collect($primaryGenres)->first(fn (Genre $genre) => $genre->getKey() === 'SUBMISSION');

        $submissionDir = Repo::submissionFile()->getSubmissionDir(
            $submission->getData('contextId'),
            $submission->getId()
        );
        $createdPaths = [];
        try {
            $galleyId = DB::transaction(function () use ($content, $mimeType, $submissionDir, &$createdPaths, $submission, $publication, $user, $context, $publicationId, $label, $extension, $genre) {

                // Catch the images referenced in the HTML and rewrite their src attributes to point to the new image supplement file.
                $images = [];
                if ($mimeType === 'text/html') {
                    [$content, $images] = $this->prepareHtmlImages($content, $publicationId, $submission);
                }
                $path = $submissionDir . '/' . uniqid() . '.' . $extension;
                $createdPaths[] = $path;
                $fileId = app()->get('file')->addFromString($content, $path, $mimeType);

                // Check if galley generated from the Editor already exists
                $locale = $publication->getData('locale');
                $galleys = Repo::galley()->getCollector()->filterByPublicationIds([$publicationId])->getMany()->collect();
                $galley = $galleys->first(fn (Galley $galley) =>
                    $galley->getData('label') === $label &&
                    $galley->getData('locale') === $locale &&
                    !$galley->getData('urlRemote') &&
                    $galley->getData('generatedFrom') === Representation::GENERATED_FROM_EDITOR);

                // If galley doesn't exist, create a new galley and submission file.
                if (!$galley) {
                    $params = [
                        'fileId' => $fileId,
                        'submissionId' => $submission->getId(),
                        'uploaderUserId' => $user->getId(),
                        'fileStage' => SubmissionFile::SUBMISSION_FILE_PROOF,
                        'name' => [$context->getPrimaryLocale() => 'bodyText-' . $publicationId . '.' . $extension],
                    ];
                    if ($genre) {
                        $params['genreId'] = $genre->getId();
                    }
                    $errors = Repo::submissionFile()->validate(
                        null,
                        $params,
                        $context->getData('supportedSubmissionLocales'),
                        $context->getPrimaryLocale()
                    );
                    if (!empty($errors)) {
                        throw new Exception(json_encode($errors));
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
                        throw new Exception(json_encode($errors));
                    }

                    $galley = Repo::galley()->newDataObject($params);
                    $galley->setData('generatedFrom', Representation::GENERATED_FROM_EDITOR);
                    $galleyId = Repo::galley()->add($galley);
                    Repo::submissionFile()->edit(Repo::submissionFile()->get($submissionFileId), [
                        'assocType' => PKPApplication::ASSOC_TYPE_REPRESENTATION,
                        'assocId' => $galleyId,
                    ]);
                } else {
                    $submissionFileId = $galley->getData('submissionFileId');
                    $submissionFile = $submissionFileId
                        ? Repo::submissionFile()->get($submissionFileId)
                        : null;
                    if (
                        !$submissionFile ||
                        (int) $submissionFile->getData('submissionId') !== (int) $submission->getId()
                    ) {
                        throw new ConversionException(ConversionException::INVALID_GALLEY_FILE);
                    }

                    /**
                     * New version of the publication clones galleys and retains their submission files.
                     * Changes in the submission file of the cloned galley will affect the original galley of the previous publication version.
                     * If there is already another galley with the same submission file ID, create a new submission file.
                     */
                    $isShared = Repo::galley()->getCollector()
                        ->filterBySubmissionFileIds([$submissionFileId])
                        ->getMany()
                        ->contains(fn (Galley $other) => $other->getId() !== $galley->getId());

                    $params = ['fileId' => $fileId];
                    $genreId = $submissionFile->getGenreId() ?: $genre?->getId();
                    if ($genreId !== null) {
                        $params['genreId'] = $genreId;
                    }
                    if ($isShared) {
                        $params += [
                            'submissionId' => $submission->getId(),
                            'uploaderUserId' => $user->getId(),
                            'fileStage' => SubmissionFile::SUBMISSION_FILE_PROOF,
                            'name' => $submissionFile->getData('name'),
                            'assocType' => PKPApplication::ASSOC_TYPE_REPRESENTATION,
                            'assocId' => $galley->getId(),
                        ];
                    }
                    $errors = Repo::submissionFile()->validate(
                        $isShared ? null : $submissionFile,
                        $params,
                        $context->getData('supportedSubmissionLocales'),
                        $context->getPrimaryLocale()
                    );
                    if (!empty($errors)) {
                        throw new Exception(json_encode($errors));
                    }

                    $galleyParams = ['isApproved' => false];
                    if ($isShared) {
                        $submissionFile = Repo::submissionFile()->newDataObject($params);
                        $submissionFileId = Repo::submissionFile()->add($submissionFile);
                        $galleyParams['submissionFileId'] = $submissionFileId;
                    } else {
                        // Only this galley uses the file, so normal revision handling is safe.
                        Repo::submissionFile()->edit($submissionFile, $params);
                    }

                    $errors = Repo::galley()->validate(
                        $galley,
                        $galleyParams,
                        $context->getData('supportedSubmissionLocales'),
                        $context->getPrimaryLocale()
                    );
                    if (!empty($errors)) {
                        throw new Exception(json_encode($errors));
                    }
                    $galleyParams['generatedFrom'] = Representation::GENERATED_FROM_EDITOR;
                    Repo::galley()->edit($galley, $galleyParams);
                    $galleyId = $galley->getId();
                }
                $this->attachHtmlImages($submissionFile, $images, $submissionDir, $context, $user, $createdPaths);
                return $galleyId;
            });
        } catch (\Throwable $exception) {
            // The transaction rolls back file records, but not filesystem writes.
            foreach ($createdPaths as $path) {
                try {
                    app()->get('file')->fs->delete($path);
                } catch (\Throwable $cleanupException) {
                    error_log('Unable to clean up generated file ' . $path . ': ' . $cleanupException->getMessage());
                }
            }
            throw $exception;
        }
        if ($mimeType === 'text/html') {
            Cache::forget('htmlArticleGalley-' . $galleyId);
        }

        return Repo::galley()->get($galleyId);
    }

    /**
     * Resolve only this publication's body-text images, without fetching URLs.
     * HTML5 parsing preserves MathJax's SVG namespaces and case-sensitive attributes.
     *
     * @return array{string, array<string, SubmissionFile>}
     */
    protected function prepareHtmlImages(string $html, int $publicationId, Submission $submission): array
    {
        $request = Application::get()->getRequest();
        $baseUrl = $request->getBaseUrl() . '/';
        $bodyTextFile = $this->getBodyTextFile($publicationId)->submissionFile;

        $sourcesByUrl = []; // ['url' => SubmissionFile]
        $schemaMap = Repo::submissionFile()->getSchemaMap($submission, []);
        $files = $bodyTextFile
            ? Repo::submissionFile()->getCollector()
                ->filterBySubmissionIds([$submission->getId()])
                ->filterByAssoc(Application::ASSOC_TYPE_SUBMISSION_FILE, [$bodyTextFile->getId()])
                ->filterByFileStages([SubmissionFile::SUBMISSION_FILE_DEPENDENT])
                ->includeDependentFiles()
                ->getMany()
            : [];
        foreach ($files as $file) {
            if (str_starts_with($file->getData('mimetype'), 'image/')) {
                $url = $schemaMap->map($file)['url'];
                $sourcesByUrl[$this->normalizeImageUrl($url, $baseUrl)] = $file;
            }
        }

        $parser = new HTML5();
        $document = $parser->loadHTML($html);
        $images = [];

        // Iterate through all <img> elements in the HTML document and rewrite their src attributes to point to the new image supplement file.
        foreach ($document->getElementsByTagName('img') as $image) {
            $src = $image->getAttribute('src');
            if (!$src || str_starts_with($src, 'data:')) {
                continue;
            }
            $url = $this->normalizeImageUrl($src, $baseUrl);
            $source = $sourcesByUrl[$url] ?? null;
            if (!$source) {
                // Only images attached to this body text are copied; local files from elsewhere are rejected.
                $uri = new Uri($url);
                parse_str($uri->getQuery(), $query);
                $base = new Uri($baseUrl);
                if (isset($query['submissionFileId']) && $uri->getScheme() === $base->getScheme() && $uri->getAuthority() === $base->getAuthority()) {
                    throw new ConversionException(ConversionException::IMAGE_NOT_ATTACHED);
                }
                if (!in_array($uri->getScheme(), ['http', 'https'], true)) {
                    throw new ConversionException(ConversionException::IMAGE_NOT_EXPORTABLE);
                }
                // Explicit external/public image URLs are left unchanged.
                continue;
            }
            $name = app()->get('file')->formatFilename(
                $source->getData('path'),
                'figure-' . $source->getId() . '-' . $source->getData('fileId')
            );
            $image->setAttribute('src', $name);
            $images[$name] = $source;
            for ($parent = $image->parentNode; $parent instanceof \DOMElement; $parent = $parent->parentNode) {
                if ($parent->localName === 'figure') {
                    $parent->setAttribute('data-src', $name);
                    break;
                }
            }
        }
        return [$parser->saveHTML($document), $images];
    }

    /**
     * Make image URLs comparable by putting them into a consistent form
     */
    protected function normalizeImageUrl(string $url, string $baseUrl): string
    {
        $uri = UriResolver::resolve(new Uri($baseUrl), new Uri($url));
        parse_str($uri->getQuery(), $query);
        ksort($query);
        return (string) $uri->withFragment('')->withQuery(http_build_query($query, '', '&', PHP_QUERY_RFC3986));
    }

    /**
     * Copy SubmissionFile representing images and attach them to the HTML file as dependent files.
     *
     * @param array<string, SubmissionFile> $images
     * @param string[] $createdPaths Paths to remove if the conversion rolls back
     */
    protected function attachHtmlImages(SubmissionFile $htmlFile, array $images, string $submissionDir, Context $context, User $user, array &$createdPaths): void
    {
        if (!$images) {
            return;
        }
        $imageGenre = DAORegistry::getDAO('GenreDAO')->getByKey('IMAGE', $context->getId());
        $existingNames = Repo::submissionFile()->getCollector()
            ->filterByAssoc(Application::ASSOC_TYPE_SUBMISSION_FILE, [$htmlFile->getId()])
            ->filterByFileStages([SubmissionFile::SUBMISSION_FILE_DEPENDENT])
            ->includeDependentFiles()
            ->getMany()
            ->flatMap(fn (SubmissionFile $file) => array_values($file->getData('name') ?? []))
            ->all();
        foreach ($images as $name => $source) {

            // Avoid copying the same image multiple times on every HTML export
            if (in_array($name, $existingNames, true)) {
                continue;
            }
            $path = $submissionDir . '/' . uniqid('figure-', true) . '.' . pathinfo($name, PATHINFO_EXTENSION);
            $createdPaths[] = $path;
            $fileId = app()->get('file')->addFromString(
                app()->get('file')->fs->read($source->getData('path')),
                $path,
                $source->getData('mimetype')
            );
            $params = [
                'fileId' => $fileId,
                'submissionId' => $htmlFile->getData('submissionId'),
                'uploaderUserId' => $user->getId(),
                'fileStage' => SubmissionFile::SUBMISSION_FILE_DEPENDENT,
                'assocType' => Application::ASSOC_TYPE_SUBMISSION_FILE,
                'assocId' => $htmlFile->getId(),
                'name' => array_fill_keys($context->getData('supportedSubmissionLocales'), $name),
            ];
            $genreId = $source->getGenreId() ?: $imageGenre?->getId();
            if ($genreId !== null) {
                $params['genreId'] = $genreId;
            }
            $errors = Repo::submissionFile()->validate(null, $params, $context->getData('supportedSubmissionLocales'), $context->getPrimaryLocale());
            if ($errors) {
                throw new \RuntimeException(
                    'Unable to validate a generated HTML image attachment: '
                    . json_encode($errors)
                );
            }
            Repo::submissionFile()->add(Repo::submissionFile()->newDataObject($params));
        }
    }

}
