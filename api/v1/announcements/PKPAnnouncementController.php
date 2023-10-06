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
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Bus;
use PKP\core\PKPRequest;
use PKP\core\PKPBaseController;
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
            "has.user",
            "has.context",
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
            ->name('announcement.delete');
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::authorize()
     */
    public function authorize(PKPRequest $request, array &$args, array $roleAssignments): bool
    {
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

        $collector->filterByContextIds([$this->getRequest()->getContext()->getId()]);

        Hook::call('API::submissions::params', [$collector, $illuminateRequest]);

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

        if (!$context) {
            throw new Exception('You can not add an announcement without sending a request to the API endpoint of a particular context.');
        }

        $params = $this->convertStringsToSchema(PKPSchemaService::SCHEMA_ANNOUNCEMENT, $illuminateRequest->input());
        $params['assocType'] = Application::get()->getContextAssocType();
        $params['assocId'] = $request->getContext()->getId();

        $primaryLocale = $context->getPrimaryLocale();
        $allowedLocales = $context->getSupportedFormLocales();
        $errors = Repo::announcement()->validate(null, $params, $allowedLocales, $primaryLocale);

        if (!empty($errors)) {
            return response()->json($errors, Response::HTTP_BAD_REQUEST);
        }

        $announcement = Repo::announcement()->newDataObject($params);
        $announcementId = Repo::announcement()->add($announcement);
        $sendEmail = (bool) filter_var($params['sendEmail'], FILTER_VALIDATE_BOOLEAN);
        $contextId = $context->getId();

        /** @var NotificationSubscriptionSettingsDAO $notificationSubscriptionSettingsDao */
        $notificationSubscriptionSettingsDao = DAORegistry::getDAO('NotificationSubscriptionSettingsDAO');

        // Notify users
        $userIdsToNotify = $notificationSubscriptionSettingsDao->getSubscribedUserIds(
            [NotificationSubscriptionSettingsDAO::BLOCKED_NOTIFICATION_KEY],
            [PKPNotification::NOTIFICATION_TYPE_NEW_ANNOUNCEMENT],
            [$contextId]
        );

        if ($sendEmail) {
            $userIdsToMail = $notificationSubscriptionSettingsDao->getSubscribedUserIds(
                [NotificationSubscriptionSettingsDAO::BLOCKED_NOTIFICATION_KEY, NotificationSubscriptionSettingsDAO::BLOCKED_EMAIL_NOTIFICATION_KEY],
                [PKPNotification::NOTIFICATION_TYPE_NEW_ANNOUNCEMENT],
                [$contextId]
            );

            $userIdsToNotifyAndMail = $userIdsToNotify->intersect($userIdsToMail);
            $userIdsToNotify = $userIdsToNotify->diff($userIdsToMail);
        }

        $sender = $request->getUser();
        $jobs = [];
        foreach ($userIdsToNotify->chunk(PKPNotification::NOTIFICATION_CHUNK_SIZE_LIMIT) as $notifyUserIds) {
            $jobs[] = new NewAnnouncementNotifyUsers(
                $notifyUserIds,
                $contextId,
                $announcementId,
                Locale::getPrimaryLocale()
            );
        }

        if (isset($userIdsToNotifyAndMail)) {
            foreach ($userIdsToNotifyAndMail->chunk(Mailer::BULK_EMAIL_SIZE_LIMIT) as $notifyAndMailUserIds) {
                $jobs[] = new NewAnnouncementNotifyUsers(
                    $notifyAndMailUserIds,
                    $contextId,
                    $announcementId,
                    Locale::getPrimaryLocale(),
                    $sender
                );
            }
        }

        Bus::batch($jobs)->dispatch();

        return response()->json(Repo::announcement()->getSchemaMap()->map($announcement), Response::HTTP_OK);
    }

    /**
     * Edit an announcement
     */
    public function edit(Request $illuminateRequest): JsonResponse
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

        // Don't allow to edit an announcement from one context from a different context's endpoint
        if ($request->getContext()->getId() !== $announcement->getData('assocId')) {
            return response()->json([
                'error' => __('api.announcements.400.contextsNotMatched')
            ], Response::HTTP_FORBIDDEN);
        }

        $params = $this->convertStringsToSchema(PKPSchemaService::SCHEMA_ANNOUNCEMENT, $illuminateRequest->input());
        $params['id'] = $announcement->getId();
        $params['typeId'] ??= null;

        $context = $request->getContext();
        $primaryLocale = $context->getPrimaryLocale();
        $allowedLocales = $context->getSupportedFormLocales();

        $errors = Repo::announcement()->validate($announcement, $params, $allowedLocales, $primaryLocale);
        if (!empty($errors)) {
            return response()->json($errors, Response::HTTP_BAD_REQUEST);
        }

        Repo::announcement()->edit($announcement, $params);

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
}
