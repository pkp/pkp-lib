<?php

/**
 * @file tests/classes/notification/PKPNotificationManagerTest.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPNotificationManagerTest
 * @ingroup tests_classes_notification
 *
 * @see Config
 *
 * @brief Tests for the PKPNotificationManager class.
 */

namespace PKP\tests\classes\notification;

use APP\notification\Notification;
use PKP\core\PKPRequest;
use PKP\db\DAORegistry;
use PKP\notification\NotificationDAO;
use PKP\notification\NotificationSettingsDAO;
use PKP\notification\PKPNotification;
use PKP\notification\PKPNotificationManager;
use PKP\tests\PKPTestCase;

class PKPNotificationManagerTest extends PKPTestCase
{
    private const NOTIFICATION_ID = 1;
    private PKPNotificationManager $notificationMgr;

    /**
     * @see PKPTestCase::getMockedRegistryKeys()
     */
    protected function getMockedRegistryKeys(): array
    {
        return [...parent::getMockedRegistryKeys(), 'request', 'application'];
    }

    /**
     * @see PKPTestCase::getMockedContainerKeys()
     */
    protected function getMockedContainerKeys(): array
    {
        return [...parent::getMockedContainerKeys(), \PKP\user\DAO::class];
    }

    /**
     * @covers PKPNotificationManager::getNotificationMessage
     */
    public function testGetNotificationMessage()
    {
        $notification = $this->getTrivialNotification();
        $notification->setType(PKPNotification::NOTIFICATION_TYPE_REVIEW_ASSIGNMENT);

        $requestDummy = $this->getMockBuilder(PKPRequest::class)->getMock();
        $result = $this->notificationMgr->getNotificationMessage($requestDummy, $notification);

        $this->assertEquals(__('notification.type.reviewAssignment'), $result);
    }

    /**
     * @covers PKPNotificationManager::createNotification
     * @dataProvider trivialNotificationDataProvider
     */
    public function testCreateNotification($notification, $notificationParams = [])
    {
        $notificationMgrStub = $this->getMgrStubForCreateNotificationTests();
        $this->injectNotificationDaoMock($notification);

        if (!empty($notificationParams)) {
            $this->injectNotificationSettingsDaoMock($notificationParams);
        }

        $result = $this->exerciseCreateNotification($notificationMgrStub, $notification, $notificationParams);

        $this->assertEquals($notification, $result);
    }

    /**
     * @covers PKPNotificationManager::createNotification
     */
    public function testCreateNotificationBlocked()
    {
        $trivialNotification = $this->getTrivialNotification();

        $blockedNotificationTypes = [$trivialNotification->getType()];
        $notificationMgrStub = $this->getMgrStubForCreateNotificationTests($blockedNotificationTypes);

        $result = $this->exerciseCreateNotification($notificationMgrStub, $trivialNotification);

        $this->assertEquals(null, $result);
    }

    /**
     * @covers PKPNotificationManager::createTrivialNotification
     * @dataProvider trivialNotificationDataProvider
     */
    public function testCreateTrivialNotification($notification, $notificationParams = [])
    {
        $trivialNotification = $notification;
        // Adapt the notification to the expected result.
        $trivialNotification->unsetData('assocId');
        $trivialNotification->unsetData('assocType');
        $trivialNotification->setType(PKPNotification::NOTIFICATION_TYPE_SUCCESS);

        $this->injectNotificationDaoMock($trivialNotification);
        if (!empty($notificationParams)) {
            $this->injectNotificationSettingsDaoMock($notificationParams);
        }

        $result = $this->notificationMgr->createTrivialNotification($trivialNotification->getUserId());

        $this->assertEquals($trivialNotification, $result);
    }

    /**
     * Provides data to be used by tests that expects two cases:
     * 1 - a trivial notification
     * 2 - a trivial notification and its parameters.
     *
     * @return array
     */
    public function trivialNotificationDataProvider()
    {
        $trivialNotification = $this->getTrivialNotification();
        $notificationParams = ['param1' => 'param1Value'];
        return [
            'Notification without params' => [$trivialNotification],
            'Notification with params' => [$trivialNotification, $notificationParams]
        ];
    }

    //
    // Protected methods.
    //
    /**
     * @see PKPTestCase::getMockedDAOs()
     */
    protected function getMockedDAOs(): array
    {
        return [...parent::getMockedDAOs(), 'NotificationDAO', 'NotificationSettingsDAO'];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->notificationMgr = new PKPNotificationManager();
    }

    //
    // Helper methods.
    //
    /**
     * Exercise the system for all test methods that covers the
     * PKPNotificationManager::createNotification() method.
     *
     * @param PKPNotificationManager $notificationMgr An instance of the
     * notification manager.
     * @param PKPNotification $notificationToCreate
     * @param array $notificationToCreateParams
     * @param mixed $request (optional)
     */
    private function exerciseCreateNotification($notificationMgr, $notificationToCreate, $notificationToCreateParams = [], $request = null)
    {
        if (is_null($request)) {
            $request = $this->getMockBuilder(PKPRequest::class)->getMock();
        }

        return $notificationMgr->createNotification(
            $request,
            $notificationToCreate->getUserId(),
            $notificationToCreate->getType(),
            $notificationToCreate->getContextId(),
            $notificationToCreate->getAssocType(),
            $notificationToCreate->getAssocId(),
            $notificationToCreate->getLevel(),
            $notificationToCreateParams
        );
    }

    /**
     * Get the notification manager stub for tests that
     * covers the PKPNotificationManager::createNotification() method.
     *
     * @param array $blockedNotifications (optional) Each notification type
     * that is blocked by user. Will be used as return value for the
     * getUserBlockedNotifications method.
     * @param array $emailedNotifications (optional) Each notification type
     * that user will be also notified by email.
     * @param array $extraOpToStub (optional) Method names to be stubbed.
     * Its expectations can be set on the returned object.
     *
     * @return PHPUnit_Framework_MockObject_MockObject
     */
    private function getMgrStubForCreateNotificationTests($blockedNotifications = [])
    {
        $notificationMgrStub = $this->getMockBuilder(PKPNotificationManager::class)
            ->onlyMethods(['getUserBlockedNotifications', 'getNotificationUrl'])
            ->getMock();

        $notificationMgrStub->expects($this->any())
            ->method('getUserBlockedNotifications')
            ->will($this->returnValue($blockedNotifications));

        $notificationMgrStub->expects($this->any())
            ->method('getNotificationUrl')
            ->will($this->returnValue('anyNotificationUrl'));

        return $notificationMgrStub;
    }

    /**
     * Setup NotificationDAO mock and register it.
     *
     * @param PKPNotification $notification A notification that is
     * expected to be inserted by the DAO.
     */
    private function injectNotificationDaoMock($notification)
    {
        $notificationDaoMock = $this->getMockBuilder(NotificationDAO::class)
            ->onlyMethods(['insertObject'])
            ->getMock();
        $notificationDaoMock->expects($this->once())
            ->method('insertObject')
            ->with($this->equalTo($notification))
            ->will($this->returnValue(self::NOTIFICATION_ID));

        DAORegistry::registerDAO('NotificationDAO', $notificationDaoMock);
    }

    /**
     * Setup NotificationSettingsDAO mock and register it.
     *
     * @param array $notificationParams Notification parameters.
     */
    private function injectNotificationSettingsDaoMock($notificationParams)
    {
        // Mock NotificationSettingsDAO.
        $notificationSettingsDaoMock = $this->getMockBuilder(NotificationSettingsDAO::class)->getMock();
        $notificationSettingsDaoMock->expects($this->any())
            ->method('updateNotificationSetting')
            ->with(
                $this->equalTo(self::NOTIFICATION_ID),
                $this->equalTo(key($notificationParams)),
                $this->equalTo(current($notificationParams))
            );

        // Inject notification settings DAO mock.
        DAORegistry::registerDAO('NotificationSettingsDAO', $notificationSettingsDaoMock);
    }

    /**
     * Get a trivial notification filled with test data.
     *
     * @return PKPNotification
     */
    private function getTrivialNotification()
    {
        /** @var NotificationDAO */
        $notificationDao = DAORegistry::getDAO('NotificationDAO');
        $notification = $notificationDao->newDataObject();
        $anyTestInteger = 1;
        $notification->setUserId($anyTestInteger);
        $notification->setType($anyTestInteger);
        $notification->setContextId(\PKP\core\PKPApplication::CONTEXT_ID_NONE);
        $notification->setAssocType($anyTestInteger);
        $notification->setAssocId($anyTestInteger);
        $notification->setLevel(Notification::NOTIFICATION_LEVEL_TRIVIAL);

        return $notification;
    }
}
