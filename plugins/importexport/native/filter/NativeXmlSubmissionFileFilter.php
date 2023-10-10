<?php

/**
 * @file plugins/importexport/native/filter/NativeXmlSubmissionFileFilter.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NativeXmlSubmissionFileFilter
 *
 * @ingroup plugins_importexport_native
 *
 * @brief Base class that converts a Native XML document to a submission file
 */

namespace PKP\plugins\importexport\native\filter;

use APP\core\Application;
use APP\core\Services;
use APP\facades\Repo;
use PKP\core\Core;
use PKP\core\PKPApplication;
use PKP\db\DAORegistry;
use PKP\file\FileManager;
use PKP\file\TemporaryFileManager;
use PKP\filter\FilterGroup;
use PKP\plugins\PluginRegistry;
use PKP\submission\GenreDAO;
use PKP\submissionFile\SubmissionFile;

class NativeXmlSubmissionFileFilter extends NativeImportFilter
{
    /**
     * Constructor
     *
     * @param FilterGroup $filterGroup
     */
    public function __construct($filterGroup)
    {
        $this->setDisplayName('Native XML submission file import');
        parent::__construct($filterGroup);
    }

    //
    // Implement template methods from NativeImportFilter
    //
    /**
     * Return the plural element name
     *
     * @return string
     */
    public function getPluralElementName()
    {
        return 'submission_files';
    }

    /**
     * Get the singular element name
     *
     * @return string
     */
    public function getSingularElementName()
    {
        return 'submission_file';
    }

    /**
     * Handle a submission file element
     *
     * @param \DOMElement $node
     *
     * @return SubmissionFile|null Null if skipping this file
     */
    public function handleElement($node)
    {
        $deployment = $this->getDeployment();
        $submission = $deployment->getSubmission();
        $context = $deployment->getContext();
        $stageName = $node->getAttribute('stage');
        $submissionFileIdFromXml = $node->getAttribute('id');
        $stageNameIdMapping = $deployment->getStageNameStageIdMapping();
        assert(isset($stageNameIdMapping[$stageName]));
        $stageId = $stageNameIdMapping[$stageName];
        $errorOccurred = false;

        $genreId = null;
        $genreName = $node->getAttribute('genre');
        // Build a cached list of genres by context ID by name
        if ($genreName) {
            if (!isset($genresByContextId[$context->getId()])) {
                $genreDao = DAORegistry::getDAO('GenreDAO'); /** @var GenreDAO $genreDao */
                $genres = $genreDao->getByContextId($context->getId());
                while ($genre = $genres->next()) {
                    foreach ($genre->getName(null) as $locale => $name) {
                        $genresByContextId[$context->getId()][$name] = $genre;
                    }
                }
            }
            if (!isset($genresByContextId[$context->getId()][$genreName])) {
                $deployment->addError(PKPApplication::ASSOC_TYPE_SUBMISSION_FILE, $submission->getId(), __('plugins.importexport.common.error.unknownGenre', ['param' => $genreName]));
                $errorOccurred = true;
            } else {
                $genre = $genresByContextId[$context->getId()][$genreName];
                $genreId = $genre->getId();
            }
        }

        $uploaderUsername = $node->getAttribute('uploader');
        $uploaderUserId = null;
        if (!$uploaderUsername) {
            $user = $deployment->getUser();
        } else {
            // Determine the user based on the username
            $user = Repo::user()->getByUsername($uploaderUsername, true);
        }
        $uploaderUserId = $user
            ? (int) $user->getId()
            : Application::get()->getRequest()->getUser()->getId();

        $submissionFile = Repo::submissionFile()->dao->newDataObject();
        $submissionFile->setData('submissionId', $submission->getId());
        $submissionFile->setData('locale', $submission->getLocale());
        $submissionFile->setData('fileStage', $stageId);
        $submissionFile->setData('createdAt', Core::getCurrentDate());
        $submissionFile->setData('updatedAt', Core::getCurrentDate());
        $submissionFile->setData('dateCreated', $node->getAttribute('date_created'));
        $submissionFile->setData('language', $node->getAttribute('language'));

        if ($caption = $node->getAttribute('caption')) {
            $submissionFile->setData('caption', $caption);
        }

        if ($copyrightOwner = $node->getAttribute('copyright_owner')) {
            $submissionFile->setData('copyrightOwner', $copyrightOwner);
        }

        if ($credit = $node->getAttribute('credit')) {
            $submissionFile->setData('credit', $credit);
        }

        if (strlen($directSalesPrice = $node->getAttribute('direct_sales_price'))) {
            $submissionFile->setData('directSalesPrice', $directSalesPrice);
        }

        if ($genreId) {
            $submissionFile->setData('genreId', $genreId);
        }

        if ($salesType = $node->getAttribute('sales_type')) {
            $submissionFile->setData('salesType', $salesType);
        }

        if ($sourceSubmissionFileId = $node->getAttribute('source_submission_file_id')) {
            $submissionFile->setData('sourceSubmissionFileId', $sourceSubmissionFileId);
        }

        if ($terms = $node->getAttribute('terms')) {
            $submissionFile->setData('terms', $terms);
        }

        if ($uploaderUserId) {
            $submissionFile->setData('uploaderUserId', $uploaderUserId);
        }

        if ($node->getAttribute('viewable') == 'true') {
            $submissionFile->setData('viewable', true);
        }

        // Handle metadata in sub-elements
        $fileIds = [];
        $currentFileId = null;
        for ($childNode = $node->firstChild; $childNode !== null; $childNode = $childNode->nextSibling) {
            if ($childNode instanceof \DOMElement) {
                switch ($childNode->tagName) {
                    case 'id':
                        $this->parseIdentifier($childNode, $submissionFile);
                        break;
                    case 'creator':
                    case 'description':
                    case 'name':
                    case 'publisher':
                    case 'source':
                    case 'sponsor':
                    case 'subject':
                        [$locale, $value] = $this->parseLocalizedContent($childNode);
                        $submissionFile->setData($childNode->tagName, $value, $locale);
                        break;
                    case 'submission_file_ref':
                        if ($submissionFile->getData('fileStage') == SubmissionFile::SUBMISSION_FILE_DEPENDENT) {
                            $oldAssocId = $childNode->getAttribute('id');
                            $newAssocId = $deployment->getSubmissionFileDBId($oldAssocId);
                            if ($newAssocId) {
                                $submissionFile->setData('assocType', PKPApplication::ASSOC_TYPE_SUBMISSION_FILE);
                                $submissionFile->setData('assocId', $newAssocId);
                            }
                        }
                        break;
                    case 'file':
                        // File has already been imported so update file id
                        $fileId = $deployment->getFileDBId($childNode->getAttribute('id')) ?: $this->handleRevisionElement($childNode);
                        // Failed to insert the file (error messages are set at the <file> handler)
                        if (!$fileId) {
                            break;
                        }

                        // If this is the current file revision, set the submission file id
                        if ($childNode->getAttribute('id') == $node->getAttribute('file_id')) {
                            $currentFileId = $fileId;
                        } else { // Otherwise add it to the list of previous revisions
                            $fileIds[] = $fileId;
                        }

                        break;
                    default:
                        $deployment->addWarning(PKPApplication::ASSOC_TYPE_SUBMISSION, $submission->getId(), __('plugins.importexport.common.error.unknownElement', ['param' => $node->tagName]));
                }
            }
        }

        // Quit if there were errors or if the main file could not be inserted
        if ($errorOccurred || !$currentFileId) {
            return null;
        }

        // Ensure the current file revision is the last to be processed
        $fileIds[] = $currentFileId;

        // Consumes the first file ID to insert an initial submission file
        $submissionFile->setData('fileId', array_shift($fileIds));
        $submissionFile = Repo::submissionFile()->get(Repo::submissionFile()->add($submissionFile));

        // Edits the submission file revisions one-by-one so that a useful activity log is built and past revisions can be accessed
        foreach ($fileIds as $fileId) {
            Repo::submissionFile()->edit($submissionFile, ['fileId' => $fileId]);
        }

        $deployment->setSubmissionFileDBId($submissionFileIdFromXml, $submissionFile->getId());

        // Retrieves the updated submission file
        return Repo::submissionFile()->get($submissionFile->getId());
    }

    /**
     * Handle a revision element
     *
     * @param \DOMElement $node
     *
     * @return int|null The new file id if successful
     */
    public function handleRevisionElement($node)
    {
        $deployment = $this->getDeployment();
        $submission = $deployment->getSubmission();

        for ($childNode = $node->firstChild; $childNode !== null; $childNode = $childNode->nextSibling) {
            if ($childNode instanceof \DOMElement) {
                switch ($childNode->tagName) {
                    case 'href':
                        $temporaryFileManager = new TemporaryFileManager();
                        $temporaryFilename = tempnam($temporaryFileManager->getBasePath(), 'src');
                        $filesrc = $childNode->getAttribute('src');
                        $errorFlag = false;
                        if (preg_match('|\w+://.+|', $filesrc)) {
                            // process as a URL
                            $client = Application::get()->getHttpClient();
                            $response = $client->request('GET', $filesrc);
                            file_put_contents($temporaryFilename, $response->getBody());
                            if (!filesize($temporaryFilename)) {
                                $errorFlag = true;
                            }
                        } elseif (substr($filesrc, 0, 1) === '/') {
                            // local file (absolute path)
                            if (!copy($filesrc, $temporaryFilename)) {
                                $errorFlag = true;
                            }
                        } elseif (is_readable($deployment->getImportPath() . '/' . $filesrc)) {
                            // local file (relative path)
                            $filesrc = $deployment->getImportPath() . '/' . $filesrc;
                            if (!copy($filesrc, $temporaryFilename)) {
                                $errorFlag = true;
                            }
                        } else {
                            // unhandled file path
                            $errorFlag = true;
                        }
                        if ($errorFlag) {
                            $deployment->addError(PKPApplication::ASSOC_TYPE_SUBMISSION, $submission->getId(), __('plugins.importexport.common.error.temporaryFileFailed', ['dest' => $temporaryFilename, 'source' => $filesrc]));
                            $fileManager = new FileManager();
                            $fileManager->deleteByPath($temporaryFilename);
                            $temporaryFilename = '';
                        }
                        break;
                    case 'embed':
                        $temporaryFileManager = new TemporaryFileManager();
                        $temporaryFilename = tempnam($temporaryFileManager->getBasePath(), 'embed');
                        if (($e = $childNode->getAttribute('encoding')) != 'base64') {
                            $deployment->addError(PKPApplication::ASSOC_TYPE_SUBMISSION, $submission->getId(), __('plugins.importexport.common.error.unknownEncoding', ['param' => $e]));
                        } else {
                            $content = base64_decode($childNode->textContent, true);
                            $errorFlag = false;
                            if (!$content) {
                                $deployment->addError(PKPApplication::ASSOC_TYPE_SUBMISSION, $submission->getId(), __('plugins.importexport.common.error.encodingError', ['param' => $e]));
                                $errorFlag = true;
                            } elseif (!file_put_contents($temporaryFilename, $content)) {
                                $deployment->addError(PKPApplication::ASSOC_TYPE_SUBMISSION, $submission->getId(), __('plugins.importexport.common.error.temporaryFileFailed', ['dest' => $temporaryFilename, 'source' => 'embed']));
                                $errorFlag = true;
                            }
                            if ($errorFlag) {
                                $fileManager = new FileManager();
                                $fileManager->deleteByPath($temporaryFilename);
                                $temporaryFilename = '';
                            }
                        }
                        break;
                }
            }
        }

        $newFileId = null;
        if ($temporaryFilename) {
            $fileSizeOnDisk = filesize($temporaryFilename);
            $expectedFileSize = $node->getAttribute('filesize');
            if ($fileSizeOnDisk != $expectedFileSize) {
                $deployment->addWarning(Application::ASSOC_TYPE_SUBMISSION, $submission->getId(), __('plugins.importexport.common.error.filesizeMismatch', ['expected' => $expectedFileSize, 'actual' => $fileSizeOnDisk]));
            }
            clearstatcache(true, $temporaryFilename);
            $fileManager = new FileManager();
            $submissionDir = Repo::submissionFile()->getSubmissionDir($submission->getData('contextId'), $submission->getId());
            $newFileId = Services::get('file')->add(
                $temporaryFilename,
                $submissionDir . '/' . uniqid() . '.' . $node->getAttribute('extension')
            );
            $deployment->setFileDBId($node->getAttribute('id'), $newFileId);
        }

        return $newFileId;
    }

    /**
     * Parse an identifier node and set up the representation object accordingly
     *
     * @param \DOMElement $element
     * @param SubmissionFile $submissionFile
     */
    public function parseIdentifier($element, $submissionFile)
    {
        $deployment = $this->getDeployment();
        $context = $deployment->getContext();
        $advice = $element->getAttribute('advice');
        switch ($element->getAttribute('type')) {
            case 'internal':
                // "update" advice not supported yet.
                assert(!$advice || $advice == 'ignore');
                break;
            case 'public':
                if ($advice == 'update') {
                    $submissionFile->setStoredPubId('publisher-id', $element->textContent);
                }
                break;
            default:
                if ($advice == 'update') {
                    if ($element->getAttribute('type') == 'doi') {
                        $doiFound = Repo::doi()->getCollector()->filterByIdentifier($element->textContent)->getMany()->first();
                        if ($doiFound) {
                            $submissionFile->setData('doiId', $doiFound->getId());
                        } else {
                            $newDoiObject = Repo::doi()->newDataObject(
                                [
                                    'doi' => $element->textContent,
                                    'contextId' => $context->getId()
                                ]
                            );
                            $doiId = Repo::doi()->add($newDoiObject);
                            $submissionFile->setData('doiId', $doiId);
                        }
                    } else {
                        // Load pub id plugins
                        PluginRegistry::loadCategory('pubIds', true, $context->getId());
                        $submissionFile->setStoredPubId($element->getAttribute('type'), $element->textContent);
                    }
                }
        }
    }

    /**
     * Instantiate a submission file.
     *
     * @param string $tagName
     *
     * @return SubmissionFile
     */
    public function instantiateSubmissionFile($tagName)
    {
        assert(false); // Subclasses should override
    }
}
