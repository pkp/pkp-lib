<?php

/**
 * @file plugins/importexport/native/filter/NativeXmlSubmissionFileFilter.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NativeXmlSubmissionFileFilter
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
use PKP\plugins\PluginRegistry;
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

    //
    // Implement template methods from PersistableFilter
    //
    /**
     * @copydoc PersistableFilter::getClassName()
     */
    public function getClassName()
    {
        return 'lib.pkp.plugins.importexport.native.filter.NativeXmlSubmissionFileFilter';
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
        $submissionFileId = $node->getAttribute('id');
        $stageNameIdMapping = $deployment->getStageNameStageIdMapping();
        assert(isset($stageNameIdMapping[$stageName]));
        $stageId = $stageNameIdMapping[$stageName];
        $request = Application::get()->getRequest();
        $errorOccured = false;

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
                $errorOccured = true;
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

        // Handle metadata in subelements
        $allRevisionIds = [];
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
                        if ($deployment->getFileDBId($childNode->getAttribute('id'))) {
                            $newFileId = $deployment->getFileDBId($childNode->getAttribute('id'));
                        } else {
                            $newFileId = $this->handleRevisionElement($childNode);
                        }
                        if ($newFileId) {
                            $allRevisionIds[] = $newFileId;
                        }
                        // If this is the current file revision, set the submission file id
                        if ($childNode->getAttribute('id') == $node->getAttribute('file_id')) {
                            $submissionFile->setData('fileId', $newFileId);
                        }
                        unset($newFileId);

                        break;
                    default:
                        $deployment->addWarning(PKPApplication::ASSOC_TYPE_SUBMISSION, $submission->getId(), __('plugins.importexport.common.error.unknownElement', ['param' => $node->tagName]));
                }
            }
        }

        if ($errorOccured) {
            return null;
        }

        // Add and edit the submission file revisions one-by-one so that a useful activity
        // log is built and past revisions can be accessed
        if (count($allRevisionIds) < 2) {
            $submissionFileId = Repo::submissionFile()->add($submissionFile);

            $submissionFile = Repo::submissionFile()->get($submissionFileId);
        } else {
            $currentFileId = $submissionFile->getData('fileId');
            $allRevisionIds = array_filter($allRevisionIds, function ($fileId) use ($currentFileId) {
                return $fileId !== $currentFileId;
            });
            $allRevisionIds = array_values($allRevisionIds);

            $submissionFileId = $submissionFile->getId();

            foreach ($allRevisionIds as $i => $fileId) {
                if ($i === 0) {
                    $submissionFile->setData('fileId', $fileId);
                    $id = Repo::submissionFile()->add($submissionFile);

                    $submissionFile = Repo::submissionFile()->get($submissionFileId);
                } else {
                    Repo::submissionFile()->edit($submissionFile, ['fileId' => $fileId]);
                }
            }

            Repo::submissionFile()->edit(
                $submissionFile,
                ['fileId' => $currentFileId]
            );

            $submissionFile = Repo::submissionFile()->get($submissionFileId);
        }

        $deployment->setSubmissionFileDBId($submissionFileId, $submissionFile->getId());

        return $submissionFile;
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
                $deployment->addWarning(ASSOC_TYPE_SUBMISSION, $submission->getId(), __('plugins.importexport.common.error.filesizeMismatch', ['expected' => $expectedFileSize, 'actual' => $fileSizeOnDisk]));
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
                    // Load pub id plugins
                    $pubIdPlugins = PluginRegistry::loadCategory('pubIds', true, $deployment->getContext()->getId());
                    $submissionFile->setStoredPubId($element->getAttribute('type'), $element->textContent);
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
