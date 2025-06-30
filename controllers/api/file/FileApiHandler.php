<?php

/**
 * @defgroup controllers_api_file File API controller
 */

/**
 * @file controllers/api/file/FileApiHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FileApiHandler
 *
 * @ingroup controllers_api_file
 *
 * @brief Class defining an AJAX API for supplying file information.
 */

namespace PKP\controllers\api\file;

use APP\core\Application;
use APP\core\Request;
use APP\facades\Repo;
use APP\handler\Handler;
use Exception;
use Illuminate\Support\Str;
use PKP\config\Config;
use PKP\core\JSONMessage;
use PKP\db\DAORegistry;
use PKP\file\FileArchive;
use PKP\file\FileManager;
use PKP\pages\libraryFiles\LibraryFileHandler;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\authorization\PolicySet;
use PKP\security\authorization\SubmissionFileAccessPolicy;
use PKP\security\Role;
use PKP\submission\genre\Genre;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\submissionFile\SubmissionFile;

class FileApiHandler extends Handler
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->addRoleAssignment(
            [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT, Role::ROLE_ID_REVIEWER, Role::ROLE_ID_AUTHOR],
            ['downloadFile', 'downloadLibraryFile', 'downloadAllFiles', 'recordDownload', 'enableLinkAction']
        );
    }

    //
    // Implement methods from PKPHandler
    //
    public function authorize($request, &$args, $roleAssignments)
    {
        $submissionId = (int) $request->getUserVar('submissionId');
        $submissionFileId = (int) $request->getUserVar('submissionFileId');
        $fileStage = (int) $request->getUserVar('fileStage');
        $libraryFileId = $request->getUserVar('libraryFileId');

        if (!empty($submissionFileId)) {
            $this->addPolicy(new SubmissionFileAccessPolicy($request, $args, $roleAssignments, SubmissionFileAccessPolicy::SUBMISSION_FILE_ACCESS_READ, $submissionFileId));
        } elseif (is_numeric($libraryFileId)) {
            $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));
        } elseif (!empty($fileStage) && empty($submissionFileId)) {
            $submissionFileIds = Repo::submissionFile()
                ->getCollector()
                ->filterBySubmissionIds([$submissionId])
                ->filterByFileStages([$fileStage])
                ->includeDependentFiles($fileStage === SubmissionFile::SUBMISSION_FILE_DEPENDENT)
                ->getIds();
            $allFilesAccessPolicy = new PolicySet(PolicySet::COMBINING_DENY_OVERRIDES);
            foreach ($submissionFileIds as $submissionFileId) {
                $allFilesAccessPolicy->addPolicy(new SubmissionFileAccessPolicy($request, $args, $roleAssignments, SubmissionFileAccessPolicy::SUBMISSION_FILE_ACCESS_READ, $submissionFileId));
            }
            $this->addPolicy($allFilesAccessPolicy);
        }

        return parent::authorize($request, $args, $roleAssignments);
    }

    //
    // Public handler methods
    //
    /**
     * Download a file.
     *
     * @param array $args
     * @param Request $request
     */
    public function downloadFile($args, $request)
    {
        $submissionFile = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION_FILE);
        $fileId = $request->getUserVar('fileId') ?? $submissionFile->getData('fileId');
        $revisions = Repo::submissionFile()
            ->getRevisions($submissionFile->getId());
        $file = null;
        foreach ($revisions as $revision) {
            if ($revision->fileId == $fileId) {
                $file = $revision;
            }
        }
        if (!$file) {
            throw new Exception('File ' . $fileId . ' is not a revision of submission file ' . $submissionFile->getId());
        }
        if (!app()->get('file')->fs->has($file->path)) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
        }

        $filename = $request->getUserVar('filename') ?? $submissionFile->getLocalizedData('name');

        // Enforce anonymous filenames for anonymous review assignments
        $reviewAssignment = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_REVIEW_ASSIGNMENT);
        if ($reviewAssignment
                && $reviewAssignment->getReviewMethod() == ReviewAssignment::SUBMISSION_REVIEW_METHOD_DOUBLEANONYMOUS
                && $reviewAssignment->getReviewerId() == $request->getUser()->getId()) {
            $genre = Genre::find($submissionFile->getData('genreId'));
            $filename = sprintf(
                '%s-%s-%d-%s-%d',
                Str::lower($request->getContext()->getLocalizedData('acronym')),
                Str::of(__('submission.list.reviewAssignment'))->kebab(),
                $submissionFile->getData('submissionId'),
                $genre ? Str::of($genre->getLocalizedName())->kebab() : 'none',
                $submissionFile->getId()
            );
        }

        $filename = app()->get('file')->formatFilename($file->path, $filename);
        app()->get('file')->download((int) $fileId, $filename);
    }

    /**
     * Download a library file.
     *
     * @param array $args
     * @param Request $request
     */
    public function downloadLibraryFile($args, $request)
    {
        $libraryFileHandler = new LibraryFileHandler($this);
        return $libraryFileHandler->downloadLibraryFile($args, $request);
    }

    /**
     * Download all passed files.
     *
     * @param array $args
     * @param Request $request
     */
    public function downloadAllFiles($args, $request)
    {
        // Retrieve the authorized objects.
        $submissionFiles = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION_FILES);

        $files = [];
        foreach ($submissionFiles as $submissionFile) {
            $path = $submissionFile->getData('path');
            $files[$path] = app()->get('file')->formatFilename($path, $submissionFile->getLocalizedData('name'));
        }

        $filename = !empty($args['nameLocaleKey'])
            ? __($args['nameLocaleKey'])
            : __('submission.files');
        $filename = $args['submissionId'] . '-' . $filename;
        $filename = (string) Str::of($filename)->kebab()->replaceMatches('[^a-z0-9\-\_.]', '');

        $fileArchive = new FileArchive();
        $archivePath = $fileArchive->create($files, rtrim(Config::getVar('files', 'files_dir'), '/'));
        if (file_exists($archivePath)) {
            $fileManager = new FileManager();
            if ($fileArchive->zipFunctional()) {
                $fileManager->downloadByPath($archivePath, 'application/x-zip', false, $filename . '.zip');
            } else {
                $fileManager->downloadByPath($archivePath, 'application/x-gtar', false, $filename . '.tar.gz');
            }
            $fileManager->deleteByPath($archivePath);
        } else {
            throw new Exception('Creating archive with submission files failed!');
        }
    }

    /**
     * Record file download and return js event to update grid rows.
     *
     * @param array $args
     * @param Request $request
     *
     * @return JSONMessage
     */
    public function recordDownload($args, $request)
    {
        return $this->enableLinkAction($args, $request);
    }

    /**
     * Returns a data changd event to re-enable the link action.  Refactored out of
     *  recordDownload since library files do not have downloads recorded and are in a
     *  different context.
     *
     * @param array $args
     * @param Request $request
     *
     * @return JSONMessage JSON object
     */
    public function enableLinkAction($args, $request)
    {
        return \PKP\db\DAO::getDataChangedEvent();
    }
}
