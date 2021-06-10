<?php
/**
 * @file classes/services/PKPPublicationService.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPPublicationService
 * @ingroup services
 *
 * @brief Helper class that encapsulates business logic for publications
 */

namespace PKP\services;

use APP\core\Application;
use APP\core\Services;
use APP\log\SubmissionEventLogEntry;
use PKP\core\Core;
use PKP\db\DAORegistry;
use PKP\db\DAOResultFactory;
use PKP\db\DBResultRange;
use PKP\log\SubmissionLog;
use PKP\observers\events\PublishedEvent;
use PKP\observers\events\UnpublishedEvent;
use PKP\plugins\HookRegistry;
use PKP\services\interfaces\EntityPropertyInterface;
use PKP\services\interfaces\EntityReadInterface;
use PKP\services\interfaces\EntityWriteInterface;
use PKP\services\queryBuilders\PKPPublicationQueryBuilder;

use PKP\statistics\PKPStatisticsHelper;
use PKP\submission\PKPSubmission;
use PKP\validation\ValidatorFactory;

class PKPPublicationService implements EntityPropertyInterface, EntityReadInterface, EntityWriteInterface
{
    /**
     * @copydoc \PKP\services\interfaces\EntityReadInterface::get()
     */
    public function get($publicationId)
    {
        $publicationDao = DAORegistry::getDAO('PublicationDAO'); /** @var PublicationDAO $publicationDao */
        return $publicationDao->getById($publicationId);
    }

    /**
     * @copydoc \PKP\services\interfaces\EntityReadInterface::getCount()
     */
    public function getCount($args = [])
    {
        return $this->getQueryBuilder($args)->getCount();
    }

    /**
     * @copydoc \PKP\services\interfaces\EntityReadInterface::getIds()
     */
    public function getIds($args = [])
    {
        return $this->getQueryBuilder($args)->getIds();
    }

    /**
     * Get publications
     *
     * @param array $args {
     *		@option int|array submissionIds
     * 		@option int count
     * 		@option int offset
     * }
     *
     * @return Iterator
     */
    public function getMany($args = [])
    {
        $range = null;
        if (isset($args['count'])) {
            $range = new DBResultRange($args['count'], null, $args['offset'] ?? 0);
        }
        // Pagination is handled by the DAO, so don't pass count and offset
        // arguments to the QueryBuilder.
        if (isset($args['count'])) {
            unset($args['count']);
        }
        if (isset($args['offset'])) {
            unset($args['offset']);
        }
        $publicationQO = $this->getQueryBuilder($args)->getQuery();
        $publicationDao = DAORegistry::getDAO('PublicationDAO'); /** @var PublicationDAO $publicationDao */
        $result = $publicationDao->retrieveRange($publicationQO->toSql(), $publicationQO->getBindings(), $range);
        $queryResults = new DAOResultFactory($result, $publicationDao, '_fromRow');

        return $queryResults->toIterator();
    }

    /**
     * @copydoc \PKP\services\interfaces\EntityReadInterface::getMax()
     */
    public function getMax($args = [])
    {
        // Don't accept args to limit the results
        if (isset($args['count'])) {
            unset($args['count']);
        }
        if (isset($args['offset'])) {
            unset($args['offset']);
        }
        return $this->getQueryBuilder($args)->getCount();
    }

    /**
     * @copydoc \PKP\services\interfaces\EntityReadInterface::getQueryBuilder()
     *
     * @return PKPPublicationQueryBuilder
     */
    public function getQueryBuilder($args = [])
    {
        $defaultArgs = [
            'contextIds' => [],
            'submissionIds' => [],
        ];

        $args = array_merge($defaultArgs, $args);

        $publicationQB = new PKPPublicationQueryBuilder();
        $publicationQB
            ->filterByContextIds($args['contextIds'])
            ->filterBySubmissionIds($args['submissionIds']);

        if (isset($args['count'])) {
            $publicationQB->limitTo($args['count']);
        }

        if (isset($args['offset'])) {
            $publicationQB->offsetBy($args['count']);
        }

        HookRegistry::call('Publication::getMany::queryBuilder', [&$publicationQB, $args]);

        return $publicationQB;
    }

    /**
     * @copydoc \PKP\services\interfaces\EntityPropertyInterface::getProperties()
     *
     * @param null|mixed $args
     */
    public function getProperties($publication, $props, $args = null)
    {
        $request = $args['request'];
        $dispatcher = $request->getDispatcher();

        // Get required submission and context
        $submission = !empty($args['submission'])
            ? $args['submission']
            : $args['submission'] = Services::get('submission')->get($publication->getData('submissionId'));

        $submissionContext = !empty($args['context'])
            ? $args['context']
            : $args['context'] = Services::get('context')->get($submission->getData('contextId'));

        // Users assigned as reviewers should not receive author details
        if (array_intersect(['authors', 'authorsString', 'authorsStringShort', 'galleys'], $props)) {
            $currentUserReviewAssignment = $args['currentUserReviewAssignment']
                ?? DAORegistry::getDAO('ReviewAssignmentDAO')
                    ->getLastReviewRoundReviewAssignmentByReviewer(
                        $submission->getId(),
                        $request->getUser()->getId()
                    );
        }

        $values = [];

        foreach ($props as $prop) {
            switch ($prop) {
                case '_href':
                    $values[$prop] = $dispatcher->url(
                        $request,
                        \PKPApplication::ROUTE_API,
                        $submissionContext->getData('urlPath'),
                        'submissions/' . $publication->getData('submissionId') . '/publications/' . $publication->getId()
                    );
                    break;
                case 'authors':
                    if ($currentUserReviewAssignment && $currentUserReviewAssignment->getReviewMethod() === SUBMISSION_REVIEW_METHOD_DOUBLEANONYMOUS) {
                        $values[$prop] = [];
                    } else {
                        $values[$prop] = array_map(
                            function ($author) use ($request) {
                                return Services::get('author')->getSummaryProperties($author, ['request' => $request]);
                            },
                            $publication->getData('authors')
                        );
                    }
                    break;
                case 'authorsString':
                    $values[$prop] = '';
                    if ((!$currentUserReviewAssignment || $currentUserReviewAssignment->getReviewMethod() !== SUBMISSION_REVIEW_METHOD_DOUBLEANONYMOUS)
                        && isset($args['userGroups'])) {
                        $values[$prop] = $publication->getAuthorString($args['userGroups']);
                    }
                    break;
                case 'authorsStringShort':
                    if ($currentUserReviewAssignment && $currentUserReviewAssignment->getReviewMethod() === SUBMISSION_REVIEW_METHOD_DOUBLEANONYMOUS) {
                        $values[$prop] = '';
                    } else {
                        $values[$prop] = $publication->getShortAuthorString();
                    }
                    break;
                case 'citations':
                    $citationDao = DAORegistry::getDAO('CitationDAO'); /** @var CitationDAO $citationDao */
                    $values[$prop] = array_map(
                        function ($citation) {
                            return $citation->getCitationWithLinks();
                        },
                        $citationDao->getByPublicationId($publication->getId())->toArray()
                    );
                    break;
                case 'fullTitle':
                    $values[$prop] = $publication->getFullTitles();
                    break;
                case 'galleys':
                    if ($currentUserReviewAssignment && $currentUserReviewAssignment->getReviewMethod() === SUBMISSION_REVIEW_METHOD_DOUBLEANONYMOUS) {
                        $values[$prop] = [];
                    } else {
                        $galleyArgs = array_merge($args, ['publication' => $publication]);
                        $values[$prop] = array_map(
                            function ($galley) use ($request, $galleyArgs) {
                                return Services::get('galley')->getSummaryProperties($galley, $galleyArgs);
                            },
                            $publication->getData('galleys')
                        );
                    }
                    break;
                default:
                    $values[$prop] = $publication->getData($prop);
                    break;
            }
        }

        $values = Services::get('schema')->addMissingMultilingualValues(PKPSchemaService::SCHEMA_PUBLICATION, $values, $submissionContext->getSupportedSubmissionLocales());

        HookRegistry::call('Publication::getProperties', [&$values, $publication, $props, $args]);

        ksort($values);

        return $values;
    }

    /**
     * @copydoc \PKP\services\interfaces\EntityPropertyInterface::getSummaryProperties()
     *
     * @param null|mixed $args
     */
    public function getSummaryProperties($publication, $args = null)
    {
        $props = Services::get('schema')->getSummaryProps(PKPSchemaService::SCHEMA_PUBLICATION);

        return $this->getProperties($publication, $props, $args);
    }

    /**
     * @copydoc \PKP\services\interfaces\EntityPropertyInterface::getFullProperties()
     *
     * @param null|mixed $args
     */
    public function getFullProperties($publication, $args = null)
    {
        $props = Services::get('schema')->getFullProps(PKPSchemaService::SCHEMA_PUBLICATION);

        return $this->getProperties($publication, $props, $args);
    }

    /**
     * Get the oldest and most recent published dates of matching publications
     *
     * @param array $args Supports all args of self::getMany()
     *
     * @return array [oldest, newest]
     */
    public function getDateBoundaries($args)
    {
        $publicationQO = $this->getQueryBuilder($args)->getDateBoundaries();
        $publicationDao = DAORegistry::getDAO('PublicationDAO'); /** @var PublicationDAO $publicationDao */
        $result = $publicationDao->retrieve($publicationQO->toSql(), $publicationQO->getBindings());
        $row = $result->current();
        import('classes.statistics.StatisticsHelper');
        return $row ?
            [$row->min_date_published, $row->max_date_published] :
            [PKPStatisticsHelper::STATISTICS_EARLIEST_DATE, date('Y-m-d', strtotime('yesterday'))];
    }

    /**
     * @copydoc \PKP\services\entityProperties\EntityWriteInterface::validate()
     */
    public function validate($action, $props, $allowedLocales, $primaryLocale)
    {
        \AppLocale::requireComponents(LOCALE_COMPONENT_PKP_SUBMISSION, LOCALE_COMPONENT_APP_SUBMISSION);
        $schemaService = Services::get('schema');

        $validator = ValidatorFactory::make(
            $props,
            $schemaService->getValidationRules(PKPSchemaService::SCHEMA_PUBLICATION, $allowedLocales),
            [
                'locale.regex' => __('validator.localeKey'),
                'datePublished.date_format' => __('publication.datePublished.errorFormat'),
                'urlPath.regex' => __('validator.alpha_dash'),
            ]
        );

        // Check required fields
        ValidatorFactory::required(
            $validator,
            $action,
            $schemaService->getRequiredProps(PKPSchemaService::SCHEMA_PUBLICATION),
            $schemaService->getMultilingualProps(PKPSchemaService::SCHEMA_PUBLICATION),
            $allowedLocales,
            $primaryLocale
        );

        // Check for input from disallowed locales
        ValidatorFactory::allowedLocales($validator, $schemaService->getMultilingualProps(PKPSchemaService::SCHEMA_PUBLICATION), $allowedLocales);

        // The submissionId must match an existing submission
        if (isset($props['submissionId'])) {
            $validator->after(function ($validator) use ($props) {
                if (!$validator->errors()->get('submissionId')) {
                    $submission = Services::get('submission')->get($props['submissionId']);
                    if (!$submission) {
                        $validator->errors()->add('submissionId', __('publication.invalidSubmission'));
                    }
                }
            });
        }

        // The urlPath must not be used in a publication attached to
        // any submission other than this publication's submission
        if (!empty($props['urlPath'])) {
            $validator->after(function ($validator) use ($action, $props) {
                if (!$validator->errors()->get('urlPath')) {
                    if (ctype_digit((string) $props['urlPath'])) {
                        $validator->errors()->add('urlPath', __('publication.urlPath.numberInvalid'));
                        return;
                    }

                    // If there is no submissionId the validator will throw it back anyway
                    if ($action === EntityWriteInterface::VALIDATE_ACTION_ADD && !empty($props['submissionId'])) {
                        $submission = Services::get('submission')->get($props['submissionId']);
                    } elseif ($action === EntityWriteInterface::VALIDATE_ACTION_EDIT) {
                        $publication = Services::get('publication')->get($props['id']);
                        $submission = Services::get('submission')->get($publication->getData('submissionId'));
                    }

                    // If there's no submission we can't validate but the validator should
                    // fail anyway, so we can return without setting a separate validation
                    // error.
                    if (!$submission) {
                        return;
                    }

                    $qb = new PKPPublicationQueryBuilder();
                    if ($qb->isDuplicateUrlPath($props['urlPath'], $submission->getId(), $submission->getData('contextId'))) {
                        $validator->errors()->add('urlPath', __('publication.urlPath.duplicate'));
                    }
                }
            });
        }

        // If a new file has been uploaded, check that the temporary file exists and
        // the current user owns it
        $user = Application::get()->getRequest()->getUser();
        ValidatorFactory::temporaryFilesExist(
            $validator,
            ['coverImage'],
            ['coverImage'],
            $props,
            $allowedLocales,
            $user ? $user->getId() : null
        );

        if ($validator->fails()) {
            $errors = $schemaService->formatValidationErrors($validator->errors(), $schemaService->get(PKPSchemaService::SCHEMA_PUBLICATION), $allowedLocales);
        }

        HookRegistry::call('Publication::validate', [&$errors, $action, $props, $allowedLocales, $primaryLocale]);

        return $errors;
    }

    /**
     * Validate a publication against publishing requirements
     *
     * This validation check should return zero errors before
     * calling self::publish().
     *
     * It should not be necessary to repeat validation rules from
     * self::validate(). These rules should be applied during all add
     * or edit actions.
     *
     * This additional check should be used when a journal or press
     * wants to enforce particular publishing requirements, such as
     * requiring certain metadata or other information.
     *
     * @param Publication $publication
     * @param Submission $submission
     * @param array $allowedLocales array Which locales are allowed
     * @param string $primaryLocale string
     *
     * @return array List of error messages. The array keys are property names
     */
    public function validatePublish($publication, $submission, $allowedLocales, $primaryLocale)
    {
        $errors = [];

        // Don't allow declined submissions to be published
        if ($submission->getData('status') === PKPSubmission::STATUS_DECLINED) {
            $errors['declined'] = __('publication.required.declined');
        }

        // Don't allow a publication to be published before passing the review stage
        if ($submission->getData('stageId') < WORKFLOW_STAGE_ID_EXTERNAL_REVIEW) {
            $errors['reviewStage'] = __('publication.required.reviewStage');
        }

        HookRegistry::call('Publication::validatePublish', [&$errors, $publication, $submission, $allowedLocales, $primaryLocale]);

        return $errors;
    }

    /**
     * @copydoc \PKP\services\entityProperties\EntityWriteInterface::add()
     */
    public function add($publication, $request)
    {
        $publication->stampModified();
        $publicationDao = DAORegistry::getDAO('PublicationDAO'); /** @var PublicationDAO $publicationDao */
        $publicationId = $publicationDao->insertObject($publication);
        $publication = $this->get($publicationId);
        $submission = Services::get('submission')->get($publication->getData('submissionId'));

        // Parse the citations
        if ($publication->getData('citationsRaw')) {
            $citationDao = DAORegistry::getDAO('CitationDAO'); /** @var CitationDAO $citationDao */
            $citationDao->importCitations($publication->getId(), $publication->getData('citationsRaw'));
        }

        // Move uploaded files into place and update the settings
        if ($publication->getData('coverImage')) {
            $userId = $request->getUser() ? $request->getUser()->getId() : null;

            $submissionContext = Application::get()->getRequest()->getContext();
            if ($submissionContext->getId() !== $submission->getData('contextId')) {
                $submissionContext = Services::get('context')->get($submission->getData('contextId'));
            }

            $supportedLocales = $submissionContext->getSupportedSubmissionLocales();
            foreach ($supportedLocales as $localeKey) {
                if (!array_key_exists($localeKey, $publication->getData('coverImage'))) {
                    continue;
                }
                $value[$localeKey] = $this->_saveFileParam($publication, $submission, $publication->getData('coverImage', $localeKey), 'coverImage', $userId, $localeKey, true);
            }

            $publication = $this->edit($publication, ['coverImage' => $value], $request);
        }

        HookRegistry::call('Publication::add', [&$publication, $request]);

        // Update a submission's status based on the status of its publications
        $submission = Services::get('submission')->updateStatus($submission);

        return $publication;
    }

    /**
     * Create a new version of a publication
     *
     * Make a copy of an existing publication, without the datePublished,
     * and make copies of all associated objects.
     *
     * @param Publication $publication The publication to copy
     * @param Request
     *
     * @return Publication The new publication
     */
    public function version($publication, $request)
    {
        $newPublication = clone $publication;
        $newPublication->setData('id', null);
        $newPublication->setData('datePublished', null);
        $newPublication->setData('status', PKPSubmission::STATUS_QUEUED);
        $newPublication->setData('version', $publication->getData('version') + 1);
        $newPublication->stampModified();
        $newPublication = $this->add($newPublication, $request);

        $authors = $publication->getData('authors');
        if (!empty($authors)) {
            foreach ($authors as $author) {
                $newAuthor = clone $author;
                $newAuthor->setData('id', null);
                $newAuthor->setData('publicationId', $newPublication->getId());
                $newAuthor = Services::get('author')->add($newAuthor, $request);

                if ($author->getId() === $publication->getData('primaryContactId')) {
                    $newPublication = $this->edit($newPublication, ['primaryContactId' => $newAuthor->getId()], $request);
                }
            }
        }

        if (!empty($newPublication->getData('citationsRaw'))) {
            $citationDao = DAORegistry::getDAO('CitationDAO'); /** @var CitationDAO $citationDao */
            $citationDao->importCitations($newPublication->getId(), $newPublication->getData('citationsRaw'));
        }

        $newPublication = $this->get($newPublication->getId());

        HookRegistry::call('Publication::version', [&$newPublication, $publication, $request]);

        $submission = Services::get('submission')->get($newPublication->getData('submissionId'));
        SubmissionLog::logEvent(Application::get()->getRequest(), $submission, SubmissionEventLogEntry::SUBMISSION_LOG_CREATE_VERSION, 'publication.event.versionCreated');

        return $newPublication;
    }

    /**
     * @copydoc \PKP\services\entityProperties\EntityWriteInterface::edit()
     */
    public function edit($publication, $params, $request)
    {
        $submission = Services::get('submission')->get($publication->getData('submissionId'));

        // Move uploaded files into place and update the params
        if (array_key_exists('coverImage', $params)) {
            $userId = $request->getUser() ? $request->getUser()->getId() : null;

            $submissionContext = Application::get()->getRequest()->getContext();
            if ($submissionContext->getId() !== $submission->getData('contextId')) {
                $submissionContext = Services::get('context')->get($submission->getData('contextId'));
            }

            $supportedLocales = $submissionContext->getSupportedSubmissionLocales();
            foreach ($supportedLocales as $localeKey) {
                if (!array_key_exists($localeKey, $params['coverImage'])) {
                    continue;
                }
                $params['coverImage'][$localeKey] = $this->_saveFileParam($publication, $submission, $params['coverImage'][$localeKey], 'coverImage', $userId, $localeKey, true);
            }
        }

        $publicationDao = DAORegistry::getDAO('PublicationDAO'); /** @var PublicationDAO $publicationDao */
        $newPublication = $publicationDao->newDataObject();
        $newPublication->_data = array_merge($publication->_data, $params);
        $newPublication->stampModified();

        HookRegistry::call('Publication::edit', [&$newPublication, $publication, $params, $request]);

        $publicationDao->updateObject($newPublication);
        $newPublication = $this->get($newPublication->getId());

        // Parse the citations
        if (array_key_exists('citationsRaw', $params) && $publication->getData('citationsRaw') != $newPublication->getData('citationsRaw')) {
            $citationDao = DAORegistry::getDAO('CitationDAO'); /** @var \CitationDAO $citationDao */
            $citationDao->importCitations($newPublication->getId(), $newPublication->getData('citationsRaw'));
        }

        $submission = Services::get('submission')->get($newPublication->getData('submissionId'));

        // Log an event when publication data is updated
        SubmissionLog::logEvent($request, $submission, SubmissionEventLogEntry::SUBMISSION_LOG_METADATA_UPDATE, 'submission.event.general.metadataUpdated');

        return $newPublication;
    }

    /**
     * Publish a publication or schedule it for publication at a
     * future date
     *
     * @param Publication $publication
     *
     * @return Publication
     */
    public function publish($publication)
    {
        $newPublication = clone $publication;

        if (!$newPublication->getData('datePublished')) {
            $newPublication->setData('datePublished', Core::getCurrentDate());
        }

        $newPublicationStatus = PKPSubmission::STATUS_SCHEDULED;

        if (strtotime($newPublication->getData('datePublished')) <= strtotime(Core::getCurrentDate())) {
            $newPublicationStatus = PKPSubmission::STATUS_PUBLISHED;
        }

        $newPublication->setData('status', $newPublicationStatus);

        $newPublication->stampModified();

        // Set the copyright and license information
        $submission = Services::get('submission')->get($newPublication->getData('submissionId'));


        $itsPublished = ($newPublication->getData('status') === PKPSubmission::STATUS_PUBLISHED);
        if ($itsPublished && !$newPublication->getData('copyrightHolder')) {
            $newPublication->setData(
                'copyrightHolder',
                $submission->_getContextLicenseFieldValue(
                    null,
                    PERMISSIONS_FIELD_COPYRIGHT_HOLDER,
                    $newPublication
                )
            );
        }

        if ($itsPublished && !$newPublication->getData('copyrightYear')) {
            $newPublication->setData(
                'copyrightYear',
                $submission->_getContextLicenseFieldValue(
                    null,
                    PERMISSIONS_FIELD_COPYRIGHT_YEAR,
                    $newPublication
                )
            );
        }

        if ($itsPublished && !$newPublication->getData('licenseUrl')) {
            $newPublication->setData(
                'licenseUrl',
                $submission->_getContextLicenseFieldValue(
                    null,
                    PERMISSIONS_FIELD_LICENSE_URL,
                    $newPublication
                )
            );
        }

        HookRegistry::call('Publication::publish::before', [&$newPublication, $publication]);

        $publicationDao = DAORegistry::getDAO('PublicationDAO'); /** @var PublicationDAO $publicationDao */
        $publicationDao->updateObject($newPublication);

        $newPublication = $this->get($newPublication->getId());
        $submission = Services::get('submission')
            ->get($newPublication->getData('submissionId'));

        // Update a submission's status based on the status of its publications
        if ($newPublication->getData('status') !== $publication->getData('status')) {
            $submission = Services::get('submission')->updateStatus($submission);
        }

        // Log an event when publication is published. Adjust the message depending
        // on whether this is the first publication or a subsequent version
        if (count($submission->getData('publications')) > 1) {
            $msg = $newPublication->getData('status') === PKPSubmission::STATUS_SCHEDULED ? 'publication.event.versionScheduled' : 'publication.event.versionPublished';
        } else {
            $msg = $newPublication->getData('status') === PKPSubmission::STATUS_SCHEDULED ? 'publication.event.scheduled' : 'publication.event.published';
        }

        SubmissionLog::logEvent(
            Application::get()->getRequest(),
            $submission,
            SubmissionEventLogEntry::SUBMISSION_LOG_METADATA_PUBLISH,
            $msg
        );

        HookRegistry::call(
            'Publication::publish',
            [
                &$newPublication,
                $publication,
                $submission
            ]
        );
        event(new PublishedEvent($newPublication, $publication, $submission));

        return $newPublication;
    }

    /**
     * Unpublish a publication that has already been published
     *
     * @param Publication $publication
     *
     * @return Publication
     */
    public function unpublish($publication)
    {
        $newPublication = clone $publication;
        $newPublication->setData('status', PKPSubmission::STATUS_QUEUED);
        $newPublication->stampModified();

        HookRegistry::call(
            'Publication::unpublish::before',
            [
                &$newPublication,
                $publication
            ]
        );

        $publicationDao = DAORegistry::getDAO('PublicationDAO'); /** @var PublicationDAO $publicationDao */
        $publicationDao->updateObject($newPublication);
        $newPublication = $this->get($newPublication->getId());
        $submission = Services::get('submission')
            ->get($newPublication->getData('submissionId'));

        // Update a submission's status based on the status of its publications
        if ($newPublication->getData('status') !== $publication->getData('status')) {
            $submission = Services::get('submission')->updateStatus($submission);
        }

        // Log an event when publication is unpublished. Adjust the message depending
        // on whether this is the first publication or a subsequent version
        $msg = 'publication.event.unpublished';

        if (count($submission->getData('publications')) > 1) {
            $msg = 'publication.event.versionUnpublished';
        }

        SubmissionLog::logEvent(
            Application::get()->getRequest(),
            $submission,
            SubmissionEventLogEntry::SUBMISSION_LOG_METADATA_UNPUBLISH,
            $msg
        );

        HookRegistry::call(
            'Publication::unpublish',
            [
                &$newPublication,
                $publication,
                $submission
            ]
        );

        event(new UnpublishedEvent($newPublication, $publication, $submission));

        return $newPublication;
    }

    /**
     * @copydoc \PKP\services\entityProperties\EntityWriteInterface::delete()
     */
    public function delete($publication)
    {
        HookRegistry::call('Publication::delete::before', [&$publication]);

        $publicationDao = DAORegistry::getDAO('PublicationDAO'); /** @var PublicationDAO $publicationDao */
        $publicationDao->deleteObject($publication);

        // Update a submission's status based on the status of its remaining publications
        $submission = Services::get('submission')
            ->get($publication->getData('submissionId'));
        Services::get('submission')->updateStatus($submission);

        HookRegistry::call('Publication::delete', [&$publication]);
    }

    /**
     * Handle a publication setting for an uploaded file
     *
     * - Moves the temporary file to the public directory
     * - Resets the param value to what is expected to be stored in the db
     * - If a null value is passed, deletes any existing file
     *
     * This method is protected because all operations which edit publications should
     * go through the add and edit methods in order to ensure that
     * the appropriate hooks are fired.
     *
     * @param Publication $publication The publication being edited
     * @param Submission $submission The submission this publication is part of
     * @param mixed $value The param value to be saved. Contains the temporary
     *  file ID if a new file has been uploaded.
     * @param string $settingName The name of the setting to save, typically used
     *  in the filename.
     * @param integer $userId ID of the user who owns the temporary file
     * @param string $localeKey Optional. Pass if the setting is multilingual
     * @param boolean $isImage Optional. For image files which include alt text in value
     *
     * @return string|array|null New param value or null on failure
     */
    protected function _saveFileParam($publication, $submission, $value, $settingName, $userId, $localeKey = '', $isImage = false)
    {

        // If the value is null, delete any existing unused file in the system
        if (is_null($value)) {
            $oldPublication = Services::get('publication')->get($publication->getId());
            $oldValue = $oldPublication->getData($settingName, $localeKey);
            $fileName = $oldValue['uploadName'] ?? null;
            if ($fileName) {
                // File may be in use by other publications
                $fileInUse = false;
                foreach ((array) $submission->getData('publications') as $iPublication) {
                    if ($publication->getId() === $iPublication->getId()) {
                        continue;
                    }
                    $iValue = $iPublication->getData($settingName, $localeKey);
                    if (!empty($iValue['uploadName']) && $iValue['uploadName'] === $fileName) {
                        $fileInUse = true;
                        continue;
                    }
                }
                if (!$fileInUse) {
                    $publicFileManager = new \PublicFileManager();
                    $publicFileManager->removeContextFile($submission->getData('contextId'), $fileName);
                }
            }
            return null;
        }

        // Check if there is something to upload
        if (empty($value['temporaryFileId'])) {
            return $value;
        }

        // Get the submission context
        $submissionContext = Application::get()->getRequest()->getContext();
        if ($submissionContext->getId() !== $submission->getData('contextId')) {
            $submissionContext = Services::get('context')->get($submission->getData('contextId'));
        }

        $temporaryFileManager = new \TemporaryFileManager();
        $temporaryFile = $temporaryFileManager->getFile((int) $value['temporaryFileId'], $userId);
        $fileNameBase = join('_', ['submission', $submission->getId(), $publication->getId(), $settingName]); // eg - submission_1_1_coverImage
        $fileName = Services::get('context')->moveTemporaryFile($submissionContext, $temporaryFile, $fileNameBase, $userId, $localeKey);

        if ($fileName) {
            if ($isImage) {
                return [
                    'altText' => !empty($value['altText']) ? $value['altText'] : '',
                    'dateUploaded' => \Core::getCurrentDate(),
                    'uploadName' => $fileName,
                ];
            } else {
                return [
                    'dateUploaded' => \Core::getCurrentDate(),
                    'uploadName' => $fileName,
                ];
            }
        }

        return false;
    }
}
