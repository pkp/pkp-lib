<?php

/**
 * @file api/v1/announcements/PKPAnnouncementHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPAnnouncementHandler
 *
 * @ingroup api_v1_announcement
 *
 * @brief Handle API requests for announcement operations.
 *
 */

namespace PKP\API\v1\announcements;

use APP\core\Application;
use APP\core\Request;
use APP\facades\Repo;
use Exception;
use Illuminate\Support\Facades\Bus;
use PKP\announcement\Collector;
use PKP\config\Config;
use PKP\context\Context;
use PKP\core\exceptions\StoreTemporaryFileException;
use PKP\db\DAORegistry;
use PKP\facades\Locale;
use PKP\handler\APIHandler;
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

class PKPAnnouncementHandler extends APIHandler
{
    /** @var int The default number of announcements to return in one request */
    public const DEFAULT_COUNT = 30;

    /** @var int The maximum number of announcements to return in one request */
    public const MAX_COUNT = 100;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->_handlerPath = 'announcements';
        $this->_endpoints = [
            'GET' => [
                [
                    'pattern' => $this->getEndpointPattern(),
                    'handler' => [$this, 'getMany'],
                    'roles' => [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN],
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/{announcementId:\d+}',
                    'handler' => [$this, 'get'],
                    'roles' => [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN],
                ],
            ],
            'POST' => [
                [
                    'pattern' => $this->getEndpointPattern(),
                    'handler' => [$this, 'add'],
                    'roles' => [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN],
                ],
            ],
            'PUT' => [
                [
                    'pattern' => $this->getEndpointPattern() . '/{announcementId:\d+}',
                    'handler' => [$this, 'edit'],
                    'roles' => [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN],
                ],
            ],
            'DELETE' => [
                [
                    'pattern' => $this->getEndpointPattern() . '/{announcementId:\d+}',
                    'handler' => [$this, 'delete'],
                    'roles' => [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN],
                ],
            ],
        ];
        parent::__construct();
    }

    /**
     * @copydoc PKPHandler::authorize
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        if (!Config::getVar('features', 'site_announcements') && !$request->getContext()) {
            return false;
        }

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
     *
     * @param \Slim\Http\Request $slimRequest Slim request object
     * @param \PKP\core\APIResponse $response object
     * @param array $args arguments
     *
     * @return \PKP\core\APIResponse
     */
    public function get($slimRequest, $response, $args)
    {
        $announcement = Repo::announcement()->get((int) $args['announcementId']);

        if (!$announcement) {
            return $response->withStatus(404)->withJsonError('api.announcements.404.announcementNotFound');
        }

        // The assocId in announcements should always point to the contextId
        if ($announcement->getData('assocId') !== $this->getRequest()->getContext()?->getId()) {
            return $response->withStatus(404)->withJsonError('api.announcements.400.contextsNotMatched');
        }

        return $response->withJson(Repo::announcement()->getSchemaMap()->map($announcement), 200);
    }

    /**
     * Get a collection of announcements
     *
     * @param \Slim\Http\Request $slimRequest Slim request object
     * @param \PKP\core\APIResponse $response object
     * @param array $args arguments
     *
     * @return \PKP\core\APIResponse
     */
    public function getMany($slimRequest, $response, $args)
    {
        $collector = Repo::announcement()->getCollector()
            ->limit(self::DEFAULT_COUNT)
            ->offset(0);

        foreach ($slimRequest->getQueryParams() as $param => $val) {
            switch ($param) {
                case 'typeIds':
                    $collector->filterByTypeIds(
                        array_map('intval', $this->paramToArray($val))
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


        Hook::call('API::submissions::params', [$collector, $slimRequest]);

        $announcements = $collector->getMany();

        return $response->withJson([
            'itemsMax' => $collector->limit(null)->offset(null)->getCount(),
            'items' => Repo::announcement()->getSchemaMap()->summarizeMany($announcements)->values(),
        ], 200);
    }

    /**
     * Add an announcement
     *
     * @param \Slim\Http\Request $slimRequest Slim request object
     * @param \PKP\core\APIResponse $response object
     * @param array $args arguments
     *
     * @return \PKP\core\APIResponse
     */
    public function add($slimRequest, $response, $args)
    {
        $request = $this->getRequest();
        $context = $request->getContext();

        $params = $this->convertStringsToSchema(PKPSchemaService::SCHEMA_ANNOUNCEMENT, $slimRequest->getParsedBody());
        $params['assocType'] = Application::get()->getContextAssocType();
        $params['assocId'] = $context?->getId();

        $primaryLocale = $context ? $context->getPrimaryLocale() : $request->getSite()->getPrimaryLocale();
        $allowedLocales = $context ? $context->getSupportedFormLocales() : $request->getSite()->getSupportedLocales();
        $errors = Repo::announcement()->validate(null, $params, $allowedLocales, $primaryLocale);

        if (!empty($errors)) {
            return $response->withStatus(400)->withJson($errors);
        }

        $announcement = Repo::announcement()->newDataObject($params);

        try {
            $announcementId = Repo::announcement()->add($announcement);
        } catch (StoreTemporaryFileException $e) {
            $announcementId = $e->dataObject->getId();
            if ($announcementId) {
                $announcement = Repo::announcement()->get($announcementId);
                Repo::announcement()->delete($announcement);
            }
            return $response->withStatus(400)->withJson([
                'image' => [__('api.400.errorUploadingImage')]
            ]);
        }

        $announcement = Repo::announcement()->get($announcementId);

        $sendEmail = (bool) filter_var($params['sendEmail'], FILTER_VALIDATE_BOOLEAN);

        if ($context) {
            $this->notifyUsers($request, $context, $announcementId, $sendEmail);
        }

        return $response->withJson(Repo::announcement()->getSchemaMap()->map($announcement), 200);
    }

    /**
     * Edit an announcement
     *
     * @param \Slim\Http\Request $slimRequest Slim request object
     * @param \PKP\core\APIResponse $response object
     * @param array $args arguments
     *
     * @return \PKP\core\APIResponse
     */
    public function edit($slimRequest, $response, $args)
    {
        $request = $this->getRequest();
        $context = $request->getContext();

        $announcement = Repo::announcement()->get((int) $args['announcementId']);

        if (!$announcement) {
            return $response->withStatus(404)->withJsonError('api.announcements.404.announcementNotFound');
        }

        if ($announcement->getData('assocType') !== Application::get()->getContextAssocType()) {
            throw new Exception('Announcement has an assocType that did not match the context.');
        }

        // Don't allow to edit an announcement from one context from a different context's endpoint
        if ($context?->getId() !== $announcement->getData('assocId')) {
            return $response->withStatus(403)->withJsonError('api.announcements.400.contextsNotMatched');
        }

        $params = $this->convertStringsToSchema(PKPSchemaService::SCHEMA_ANNOUNCEMENT, $slimRequest->getParsedBody());
        $params['id'] = $announcement->getId();
        $params['typeId'] ??= null;

        $primaryLocale = $context ? $context->getPrimaryLocale() : $request->getSite()->getPrimaryLocale();
        $allowedLocales = $context ? $context->getSupportedFormLocales() : $request->getSite()->getSupportedLocales();

        $errors = Repo::announcement()->validate($announcement, $params, $allowedLocales, $primaryLocale);
        if (!empty($errors)) {
            return $response->withStatus(400)->withJson($errors);
        }

        try {
            Repo::announcement()->edit($announcement, $params);
        } catch (StoreTemporaryFileException $e) {
            Repo::announcement()->delete($announcement);
            return $response->withStatus(400)->withJson([
                'image' => __('api.400.errorUploadingImage')
            ]);
        }

        $announcement = Repo::announcement()->get($announcement->getId());

        return $response->withJson(Repo::announcement()->getSchemaMap()->map($announcement), 200);
    }

    /**
     * Delete an announcement
     *
     * @param \Slim\Http\Request $slimRequest Slim request object
     * @param \PKP\core\APIResponse $response object
     * @param array $args arguments
     *
     * @return \PKP\core\APIResponse
     */
    public function delete($slimRequest, $response, $args)
    {
        $request = $this->getRequest();

        $announcement = Repo::announcement()->get((int) $args['announcementId']);

        if (!$announcement) {
            return $response->withStatus(404)->withJsonError('api.announcements.404.announcementNotFound');
        }

        if ($announcement->getData('assocType') !== Application::get()->getContextAssocType()) {
            throw new Exception('Announcement has an assocType that did not match the context.');
        }

        // Don't allow to delete an announcement from one context from a different context's endpoint
        if ($request->getContext()?->getId() !== $announcement->getData('assocId')) {
            return $response->withStatus(403)->withJsonError('api.announcements.400.contextsNotMatched');
        }

        $announcementProps = Repo::announcement()->getSchemaMap()->map($announcement);

        Repo::announcement()->delete($announcement);

        return $response->withJson($announcementProps, 200);
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
