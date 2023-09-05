<?php

/**
 * @file api/v1/announcements/PKPAnnouncementController.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPAnnouncementController
 *
 * @ingroup api_v1_announcement
 *
 * @brief Controller class to handle API requests for announcement operations.
 *
 */

namespace PKP\API\v1\announcements;

use APP\core\Application;
use APP\facades\Repo;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Route;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\announcement\Collector;
use PKP\context\Context;
use PKP\core\exceptions\StoryTemporaryFileException;
use PKP\db\DAORegistry;
use PKP\facades\Locale;
use PKP\jobs\notifications\NewAnnouncementNotifyUsers;
use PKP\mail\Mailer;
use PKP\notification\NotificationSubscriptionSettingsDAO;
use PKP\notification\PKPNotification;
use PKP\plugins\Hook;
use PKP\security\authorization\PolicySet;
use PKP\security\authorization\RoleBasedHandlerOperationPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Role;
use PKP\services\PKPSchemaService;

class PKPAnnouncementController extends PKPBaseController
{
    /** @var int The default number of announcements to return in one request */
    public const DEFAULT_COUNT = 30;

    /** @var int The maximum number of announcements to return in one request */
    public const MAX_COUNT = 100;

    /**
     * @copydoc \PKP\core\PKPBaseController::getHandlerPath()
     */
    public function getHandlerPath(): string
    {
        return 'announcements';
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::getRouteGroupMiddleware()
     */
    public function getRouteGroupMiddleware(): array
    {
        return [
            'has.user',
            'has.context',
            self::roleAuthorizer([
                Role::ROLE_ID_SITE_ADMIN,
                Role::ROLE_ID_MANAGER,
            ]),
        ];
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::getGroupRoutes()
     */
    public function getGroupRoutes(): void
    {
        Route::get('', $this->getMany(...))
            ->name('announcement.getMany');

        Route::get('{announcementId}', $this->get(...))
            ->name('announcement.getAnnouncement')
            ->whereNumber('announcementId');

        Route::post('', $this->add(...))
            ->name('announcement.add');

        Route::put('{announcementId}', $this->edit(...))
            ->name('announcement.edit')
            ->whereNumber('announcementId');

        Route::delete('{announcementId}', $this->delete(...))
            ->name('announcement.delete')
            ->whereNumber('announcementId');
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::authorize()
     */
    public function authorize(PKPRequest $request, array &$args, array $roleAssignments): bool
    {
        if (!$request->getContext()) {
            $roleAssignments = $this->getSiteRoleAssignments($roleAssignments);
        }

        $this->addPolicy(new UserRolesRequiredPolicy($request), true);

        $rolePolicy = new PolicySet(PolicySet::COMBINING_PERMIT_OVERRIDES);

        foreach ($roleAssignments as $role => $operations) {
            $rolePolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, $role, $operations));
        }

        $this->addPolicy($rolePolicy);

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Get a single submission
     */
    public function get(Request $illuminateRequest): JsonResponse
    {
        $announcement = Repo::announcement()->get((int) $illuminateRequest->route('announcementId'));

        if (!$announcement) {
            return response()->json([
                'error' => __('api.announcements.404.announcementNotFound')
            ], Response::HTTP_NOT_FOUND);
        }

        // The assocId in announcements should always point to the contextId
        if ($announcement->getData('assocId') !== $this->getRequest()->getContext()->getId()) {
            return response()->json([
                'error' => __('api.announcements.400.contextsNotMatched')
            ], Response::HTTP_BAD_REQUEST);
        }

        return response()->json(Repo::announcement()->getSchemaMap()->map($announcement), Response::HTTP_OK);
    }

    /**
     * Get a collection of announcements
     *
     * @hook API::announcements::params [$collector, $illuminateRequest]
     */
    public function getMany(Request $illuminateRequest): JsonResponse
    {
        $collector = Repo::announcement()->getCollector()
            ->limit(self::DEFAULT_COUNT)
            ->offset(0);

        foreach ($illuminateRequest->query() as $param => $val) {
            switch ($param) {
                case 'typeIds':
                    $collector->filterByTypeIds(
                        array_map('intval', paramToArray($val))
                    );
                    break;
                case 'count':
                    $collector->limit(min((int) $val, self::MAX_COUNT));
                    break;
                case 'offset':
                    $collector->offset((int) $val);
                    break;
                case 'searchPhrase':
                    $collector->searchPhrase($val);
                    break;
            }
        }

        if ($this->getRequest()->getContext()) {
            $collector->filterByContextIds([$this->getRequest()->getContext()->getId()]);
        } else {
            $collector->withSiteAnnouncements(Collector::SITE_ONLY);
        }


        Hook::run('API::announcements::params', [$collector, $illuminateRequest]);

        $announcements = $collector->getMany();

        return response()->json([
            'itemsMax' => $collector->limit(null)->offset(null)->getCount(),
            'items' => Repo::announcement()->getSchemaMap()->summarizeMany($announcements)->values(),
        ], Response::HTTP_OK);
    }

    /**
     * Add an announcement
     */
    public function add(Request $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();
        $context = $request->getContext();

        $params = $this->convertStringsToSchema(PKPSchemaService::SCHEMA_ANNOUNCEMENT, $illuminateRequest->input());
        $params['assocType'] = Application::get()->getContextAssocType();
        $params['assocId'] = $context?->getId();

        $primaryLocale = $context ? $context->getPrimaryLocale() : $request->getSite()->getPrimaryLocale();
        $allowedLocales = $context ? $context->getSupportedFormLocales() : $request->getSite()->getSupportedLocales();
        $errors = Repo::announcement()->validate(null, $params, $allowedLocales, $primaryLocale);

        if (!empty($errors)) {
            return response()->json($errors, Response::HTTP_BAD_REQUEST);
        }

        $announcement = Repo::announcement()->newDataObject($params);

        try {
            $announcementId = Repo::announcement()->add($announcement);
        } catch (StoryTemporaryFileException $e) {
            $announcement = Repo::announcement()->get($announcementId);
            Repo::announcement()->delete($announcement);
            return $response->withStatus(400)->withJson([
                'image' => __('api.400.errorUploadingImage')
            ]);
        }

        $announcement = Repo::announcement()->get($announcementId);

        $sendEmail = (bool) filter_var($params['sendEmail'], FILTER_VALIDATE_BOOLEAN);

        if ($context) {
            $this->notifyUsers($request, $context, $announcementId, $sendEmail);
        }

        return response()->json(Repo::announcement()->getSchemaMap()->map($announcement), Response::HTTP_OK);
    }

    /**
     * Edit an announcement
     */
    public function edit(Request $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();
        $context = $request->getContext();

        $announcement = Repo::announcement()->get((int) $illuminateRequest->route('announcementId'));

        if (!$announcement) {
            return response()->json([
                'error' => __('api.announcements.404.announcementNotFound')
            ], Response::HTTP_NOT_FOUND);
        }

        if ($announcement->getData('assocType') !== Application::get()->getContextAssocType()) {
            throw new Exception('Announcement has an assocType that did not match the context.');
        }

        // Don't allow to edit an announcement from one context from a different context's endpoint
        if ($request->getContext()->getId() !== $announcement->getData('assocId')) {
            return response()->json([
                'error' => __('api.announcements.400.contextsNotMatched')
            ], Response::HTTP_FORBIDDEN);
        }

        $params = $this->convertStringsToSchema(PKPSchemaService::SCHEMA_ANNOUNCEMENT, $illuminateRequest->input());
        $params['id'] = $announcement->getId();
        $params['typeId'] ??= null;

        $primaryLocale = $context ? $context->getPrimaryLocale() : $request->getSite()->getPrimaryLocale();
        $allowedLocales = $context ? $context->getSupportedFormLocales() : $request->getSite()->getSupportedLocales();

        $errors = Repo::announcement()->validate($announcement, $params, $allowedLocales, $primaryLocale);
        if (!empty($errors)) {
            return response()->json($errors, Response::HTTP_BAD_REQUEST);
        }

        try {
            Repo::announcement()->edit($announcement, $params);
        } catch (StoryTemporaryFileException $e) {
            Repo::announcement()->delete($announcement);
            return $response->withStatus(400)->withJson([
                'image' => __('api.400.errorUploadingImage')
            ]);
        }

        $announcement = Repo::announcement()->get($announcement->getId());

        return response()->json(Repo::announcement()->getSchemaMap()->map($announcement), Response::HTTP_OK);
    }

    /**
     * Delete an announcement
     */
    public function delete(Request $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();

        $announcement = Repo::announcement()->get((int) $illuminateRequest->route('announcementId'));

        if (!$announcement) {
            return response()->json([
                'error' => __('api.announcements.404.announcementNotFound')
            ], Response::HTTP_NOT_FOUND);
        }

        if ($announcement->getData('assocType') !== Application::get()->getContextAssocType()) {
            throw new Exception('Announcement has an assocType that did not match the context.');
        }

        // Don't allow to delete an announcement from one context from a different context's endpoint
        if ($request->getContext()->getId() !== $announcement->getData('assocId')) {
            return response()->json([
                'error' => __('api.announcements.400.contextsNotMatched')
            ], Response::HTTP_FORBIDDEN);
        }

        $announcementProps = Repo::announcement()->getSchemaMap()->map($announcement);

        Repo::announcement()->delete($announcement);

        return response()->json($announcementProps, Response::HTTP_OK);
    }

    /**
     * Modify the role assignments so that only
     * site admins have access
     */
    protected function getSiteRoleAssignments(array $roleAssignments): array
    {
        return array_filter($roleAssignments, fn($key) => $key == Role::ROLE_ID_SITE_ADMIN, ARRAY_FILTER_USE_KEY);
    }

    /**
     * Notify subscribed users
     *
     * This only works for context-level announcements. There is no way to
     * determine users who have subscribed to site-level announcements.
     *
     * @param bool $sendEmail Whether or not the editor chose to notify users by email
     */
    protected function notifyUsers(Request $request, Context $context, int $announcementId, bool $sendEmail): void
    {
        /** @var NotificationSubscriptionSettingsDAO $notificationSubscriptionSettingsDao */
        $notificationSubscriptionSettingsDao = DAORegistry::getDAO('NotificationSubscriptionSettingsDAO');

        // Notify users
        $userIdsToNotify = $notificationSubscriptionSettingsDao->getSubscribedUserIds(
            [NotificationSubscriptionSettingsDAO::BLOCKED_NOTIFICATION_KEY],
            [PKPNotification::NOTIFICATION_TYPE_NEW_ANNOUNCEMENT],
            [$context->getId()]
        );

        if ($sendEmail) {
            $userIdsToMail = $notificationSubscriptionSettingsDao->getSubscribedUserIds(
                [NotificationSubscriptionSettingsDAO::BLOCKED_NOTIFICATION_KEY, NotificationSubscriptionSettingsDAO::BLOCKED_EMAIL_NOTIFICATION_KEY],
                [PKPNotification::NOTIFICATION_TYPE_NEW_ANNOUNCEMENT],
                [$context->getId()]
            );

            $userIdsToNotifyAndMail = $userIdsToNotify->intersect($userIdsToMail);
            $userIdsToNotify = $userIdsToNotify->diff($userIdsToMail);
        }

        $sender = $request->getUser();
        $jobs = [];
        foreach ($userIdsToNotify->chunk(PKPNotification::NOTIFICATION_CHUNK_SIZE_LIMIT) as $notifyUserIds) {
            $jobs[] = new NewAnnouncementNotifyUsers(
                $notifyUserIds,
                $context->getId(),
                $announcementId,
                Locale::getPrimaryLocale()
            );
        }

        if (isset($userIdsToNotifyAndMail)) {
            foreach ($userIdsToNotifyAndMail->chunk(Mailer::BULK_EMAIL_SIZE_LIMIT) as $notifyAndMailUserIds) {
                $jobs[] = new NewAnnouncementNotifyUsers(
                    $notifyAndMailUserIds,
                    $context->getId(),
                    $announcementId,
                    Locale::getPrimaryLocale(),
                    $sender
                );
            }
        }

        Bus::batch($jobs)->dispatch();
    }
}
