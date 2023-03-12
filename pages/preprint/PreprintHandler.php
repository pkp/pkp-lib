<?php

/**
 * @file pages/preprint/PreprintHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PreprintHandler
 * @ingroup pages_preprint
 *
 * @brief Handle requests for preprint functions.
 *
 */

namespace APP\pages\preprint;

use APP\core\Application;
use APP\core\Services;
use APP\facades\Repo;
use APP\handler\Handler;
use APP\observers\events\UsageEvent;
use APP\security\authorization\OpsServerMustPublishPolicy;
use APP\template\TemplateManager;
use Firebase\JWT\JWT;
use PKP\config\Config;
use PKP\core\PKPApplication;
use PKP\db\DAORegistry;
use PKP\plugins\Hook;
use PKP\plugins\PluginRegistry;
use PKP\security\authorization\ContextRequiredPolicy;
use PKP\submission\Genre;
use PKP\submission\PKPSubmission;

use PKP\submissionFile\SubmissionFile;

class PreprintHandler extends Handler
{
    /** @var \PKP\context\Context context associated with the request */
    public $context;

    /** @var \APP\submission\Submission submission associated with the request */
    public $preprint;

    /** @var \APP\publication\Publication publication associated with the request */
    public $publication;

    /** @var \PKP\galley\Galley galley associated with the request */
    public $galley;

    /** @var int submissionFileId associated with the request */
    public $submissionFileId;


    /**
     * @copydoc PKPHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        // Permit the use of the Authorization header and an API key for access to unpublished/subscription content
        if ($header = array_search('Authorization', array_flip(getallheaders()))) {
            [$bearer, $jwt] = explode(' ', $header);
            if (strcasecmp($bearer, 'Bearer') == 0) {
                $apiToken = JWT::decode($jwt, Config::getVar('security', 'api_key_secret', ''), ['HS256']);
                // Compatibility with old API keys
                // https://github.com/pkp/pkp-lib/issues/6462
                if (substr($apiToken, 0, 2) === '""') {
                    $apiToken = json_decode($apiToken);
                }
                $this->setApiToken($apiToken);
            }
        }

        $this->addPolicy(new ContextRequiredPolicy($request));
        $this->addPolicy(new OpsServerMustPublishPolicy($request));

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * @see PKPHandler::initialize()
     *
     * @param array $args Arguments list
     */
    public function initialize($request, $args = [])
    {
        $urlPath = empty($args) ? 0 : array_shift($args);

        // Get the submission that matches the requested urlPath
        $submission = Repo::submission()->getByUrlPath($urlPath, $request->getContext()->getId());

        if (!$submission && ctype_digit((string) $urlPath)) {
            $submission = Repo::submission()->get($urlPath);
            if ($submission && $request->getContext()->getId() != $submission->getContextId()) {
                $submission = null;
            }
        }

        $user = $request->getUser();

        // Serve 404 if no submission available OR submission is unpublished and no user is logged in OR submission is unpublished and we have a user logged in but the user does not have access to preview
        if (!$submission || ($submission->getData('status') !== PKPSubmission::STATUS_PUBLISHED && !$user) || ($submission->getData('status') !== PKPSubmission::STATUS_PUBLISHED && $user && !Repo::submission()->canPreview($user, $submission))) {
            $request->getDispatcher()->handle404();
        }

        // If the urlPath does not match the urlPath of the current
        // publication, redirect to the current URL
        $currentUrlPath = $submission->getBestId();
        if ($currentUrlPath && $currentUrlPath != $urlPath) {
            $newArgs = $args;
            $newArgs[0] = $currentUrlPath;
            $request->redirect(null, $request->getRequestedPage(), $request->getRequestedOp(), $newArgs);
        }

        $this->preprint = $submission;
        // Get the requested publication or if none requested get the current publication
        $subPath = empty($args) ? 0 : array_shift($args);
        if ($subPath === 'version') {
            $publicationId = (int) array_shift($args);
            $galleyId = empty($args) ? 0 : array_shift($args);
            foreach ($this->preprint->getData('publications') as $publication) {
                if ($publication->getId() === $publicationId) {
                    $this->publication = $publication;
                }
            }
            if (!$this->publication) {
                $request->getDispatcher()->handle404();
            }
        } else {
            $this->publication = $this->preprint->getCurrentPublication();
            $galleyId = $subPath;
        }

        if ($this->publication->getData('status') !== PKPSubmission::STATUS_PUBLISHED && !Repo::submission()->canPreview($user, $submission)) {
            $request->getDispatcher()->handle404();
        }

        if ($galleyId && in_array($request->getRequestedOp(), ['view', 'download'])) {
            $galleys = $this->publication->getData('galleys');
            foreach ($galleys as $galley) {
                if ($galley->getBestGalleyId() == $galleyId) {
                    $this->galley = $galley;
                    break;
                }
            }
            // Redirect to the most recent version of the submission if the request
            // points to an outdated galley but doesn't use the specific versioned
            // URL. This can happen when a galley's urlPath is changed between versions.
            if (!$this->galley) {
                $publications = $submission->getPublishedPublications();
                foreach ($publications as $publication) {
                    foreach ($publication->getData('galleys') as $galley) {
                        if ($galley->getBestGalleyId() == $galleyId) {
                            $request->redirect(null, $request->getRequestedPage(), $request->getRequestedOp(), [$submission->getBestId()]);
                        }
                    }
                }
                $request->getDispatcher()->handle404();
            }

            // Store the file id if it exists
            if (!empty($args)) {
                $this->submissionFileId = array_shift($args);
            }
        }
    }

    /**
     * View Preprint. (Either preprint landing page or galley view.)
     *
     * @param array $args
     * @param \APP\core\Request $request
     */
    public function view($args, $request)
    {
        $context = $request->getContext();
        $user = $request->getUser();
        $preprint = $this->preprint;
        $publication = $this->publication;

        // Get the earliest published publication
        $firstPublication = $preprint->getData('publications')->reduce(function ($a, $b) {
            return empty($a) || strtotime((string) $b->getData('datePublished')) < strtotime((string) $a->getData('datePublished')) ? $b : $a;
        }, 0);

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'preprint' => $preprint,
            'publication' => $publication,
            'firstPublication' => $firstPublication,
            'currentPublication' => $preprint->getCurrentPublication(),
            'galley' => $this->galley,
            'fileId' => $this->submissionFileId, // DEPRECATED in 3.4.0: https://github.com/pkp/pkp-lib/issues/6545
            'submissionFileId' => $this->submissionFileId,
        ]);
        $this->setupTemplate($request);

        $publicationCategories = Repo::category()->getCollector()
            ->filterByPublicationIds([$publication->getId()])
            ->getMany();

        $categories = [];
        foreach ($publicationCategories as $category) {
            $title = $category->getLocalizedTitle();
            if ($category->getParentId()) {
                $title = Repo::category()->get($category->getParentId())->getLocalizedTitle() . ' > ' . $title;
            }
            $categories[] = [
                'path' => $category->getPath(),
                'title' => $title,
            ];
        }

        $templateMgr->assign([
            'ccLicenseBadge' => Application::get()->getCCLicenseBadge($publication->getData('licenseUrl')),
            'publication' => $publication,
            'section' => Repo::section()->get($publication->getData('sectionId')),
            'categories' => $categories,
        ]);



        if ($this->galley && !$this->userCanViewGalley($request)) {
            fatalError('Cannot view galley.');
        }

        // Get galleys sorted into primary and supplementary groups
        $galleys = $publication->getData('galleys');
        $primaryGalleys = [];
        $supplementaryGalleys = [];
        if ($galleys) {
            $genreDao = DAORegistry::getDAO('GenreDAO');
            $primaryGenres = $genreDao->getPrimaryByContextId($context->getId())->toArray();
            $primaryGenreIds = array_map(function ($genre) {
                return $genre->getId();
            }, $primaryGenres);
            $supplementaryGenres = $genreDao->getBySupplementaryAndContextId(true, $context->getId())->toArray();
            $supplementaryGenreIds = array_map(function ($genre) {
                return $genre->getId();
            }, $supplementaryGenres);

            foreach ($galleys as $galley) {
                $remoteUrl = $galley->getRemoteURL();
                $file = $galley->getFile();
                if (!$remoteUrl && !$file) {
                    continue;
                }
                if ($remoteUrl || in_array($file->getGenreId(), $primaryGenreIds)) {
                    $primaryGalleys[] = $galley;
                } elseif (in_array($file->getGenreId(), $supplementaryGenreIds)) {
                    $supplementaryGalleys[] = $galley;
                }
            }
        }
        $templateMgr->assign([
            'primaryGalleys' => $primaryGalleys,
            'supplementaryGalleys' => $supplementaryGalleys,
        ]);

        // Citations
        if ($publication->getData('citationsRaw')) {
            $citationDao = DAORegistry::getDAO('CitationDAO'); /** @var CitationDAO $citationDao */
            $parsedCitations = $citationDao->getByPublicationId($publication->getId());
            $templateMgr->assign([
                'parsedCitations' => $parsedCitations->toArray(),
            ]);
        }

        // Assign deprecated values to the template manager for
        // compatibility with older themes
        $templateMgr->assign([
            'licenseTerms' => $context->getLocalizedData('licenseTerms'),
            'licenseUrl' => $publication->getData('licenseUrl'),
            'copyrightHolder' => $publication->getLocalizedData('copyrightHolder'),
            'copyrightYear' => $publication->getData('copyrightYear'),
            'pubIdPlugins' => PluginRegistry::loadCategory('pubIds', true),
            'keywords' => $publication->getData('keywords'),
        ]);

        // Fetch and assign the galley to the template
        if ($this->galley && $this->galley->getRemoteURL()) {
            $request->redirectUrl($this->galley->getRemoteURL());
        }

        if (empty($this->galley)) {
            // No galley: Prepare the preprint landing page.

            // Ask robots not to index outdated versions and point to the canonical url for the latest version
            if ($publication->getId() !== $preprint->getCurrentPublication()->getId()) {
                $templateMgr->addHeader('noindex', '<meta name="robots" content="noindex">');
                $url = $request->getDispatcher()->url($request, PKPApplication::ROUTE_PAGE, null, 'preprint', 'view', $preprint->getBestId());
                $templateMgr->addHeader('canonical', '<link rel="canonical" href="' . $url . '">');
            }

            if (!Hook::call('PreprintHandler::view', [&$request, &$preprint, $publication])) {
                $templateMgr->display('frontend/pages/preprint.tpl');
                event(new UsageEvent(Application::ASSOC_TYPE_SUBMISSION, $context, $preprint));
                return;
            }
        } else {

            // Ask robots not to index outdated versions
            if ($publication->getId() !== $preprint->getCurrentPublication()->getId()) {
                $templateMgr->addHeader('noindex', '<meta name="robots" content="noindex">');
            }

            // Galley: Prepare the galley file download.
            if (!Hook::call('PreprintHandler::view::galley', [&$request, &$this->galley, &$preprint, $publication])) {
                if ($this->publication->getId() !== $this->preprint->getCurrentPublication()->getId()) {
                    $redirectArgs = [
                        $preprint->getBestId(),
                        'version',
                        $publication->getId(),
                        $this->galley->getBestGalleyId()
                    ];
                } else {
                    $redirectArgs = [
                        $preprint->getId(),
                        $this->galley->getBestGalleyId()
                    ];
                }
                $request->redirect(null, null, 'download', $redirectArgs);
            }
        }
    }

    /**
     * Download an preprint file
     *
     * @param array $args
     * @param \PKP\core\PKPRequest $request
     */
    public function download($args, $request)
    {
        if (!isset($this->galley)) {
            $request->getDispatcher()->handle404();
        }
        if ($this->galley->getRemoteURL()) {
            $request->redirectUrl($this->galley->getRemoteURL());
        } elseif ($this->userCanViewGalley($request)) {
            if (!$this->submissionFileId) {
                $this->submissionFileId = $this->galley->getData('submissionFileId');
            }

            // If no file ID could be determined, treat it as a 404.
            if (!$this->submissionFileId) {
                $request->getDispatcher()->handle404();
            }

            // If the file ID is not the galley's file ID, ensure it is a dependent file, or else 404.
            if ($this->submissionFileId != $this->galley->getData('submissionFileId')) {
                $dependentFileIds = Repo::submissionFile()
                    ->getCollector()
                    ->filterByAssoc(
                        Application::ASSOC_TYPE_SUBMISSION_FILE,
                        [$this->galley->getData('submissionFileId')]
                    )->filterByFileStages([SubmissionFile::SUBMISSION_FILE_DEPENDENT])
                    ->includeDependentFiles()
                    ->getIds()
                    ->toArray();

                if (!in_array($this->submissionFileId, $dependentFileIds)) {
                    $request->getDispatcher()->handle404();
                }
            }

            if (!Hook::call('PreprintHandler::download', [$this->preprint, &$this->galley, &$this->submissionFileId])) {
                $submissionFile = Repo::submissionFile()->get($this->submissionFileId);

                if (!Services::get('file')->fs->has($submissionFile->getData('path'))) {
                    $request->getDispatcher()->handle404();
                }

                $filename = Services::get('file')->formatFilename($submissionFile->getData('path'), $submissionFile->getLocalizedData('name'));

                // if the file is a gallay file (i.e. not a dependent file e.g. CSS or images), fire an usage event.
                if ($this->galley->getData('submissionFileId') == $this->submissionFileId) {
                    $assocType = Application::ASSOC_TYPE_SUBMISSION_FILE;
                    $genreDao = DAORegistry::getDAO('GenreDAO');
                    $genre = $genreDao->getById($submissionFile->getData('genreId'));
                    // TO-DO: is this correct ?
                    if ($genre->getCategory() != Genre::GENRE_CATEGORY_DOCUMENT || $genre->getSupplementary() || $genre->getDependent()) {
                        $assocType = Application::ASSOC_TYPE_SUBMISSION_FILE_COUNTER_OTHER;
                    }
                    event(new UsageEvent($assocType, $request->getContext(), $this->preprint, $this->galley, $submissionFile));
                }
                $returner = true;
                Hook::call('FileManager::downloadFileFinished', [&$returner]);

                Services::get('file')->download($submissionFile->getData('fileId'), $filename);
            }
        } else {
            header('HTTP/1.0 403 Forbidden');
            echo '403 Forbidden<br>';
        }
    }

    /**
     * Determines whether a user can view this preprint galley or not.
     *
     * @param \APP\core\Request $request
     */
    public function userCanViewGalley($request)
    {
        $submission = $this->preprint;
        $user = $request->getUser();

        // If the preprint is posted OR author or server manager who can view unposted preprints
        if (($submission && $submission->getStatus() === PKPSubmission::STATUS_PUBLISHED) || ($submission && $user && Repo::submission()->canPreview($user, $submission))) {
            return true;
        }
        return false;
    }
}
