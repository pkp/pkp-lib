<?php

/**
 * @file classes/mail/mailables/AnnouncementNotify.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AnnouncementNotify
 *
 * @brief Email sent to notify users about new announcement
 */

namespace PKP\mail\mailables;

use APP\core\Application;
use PKP\announcement\Announcement;
use PKP\context\Context;
use PKP\core\PKPApplication;
use PKP\facades\Locale;
use PKP\mail\Mailable;
use PKP\mail\traits\Configurable;
use PKP\mail\traits\Recipient;
use PKP\mail\traits\Sender;
use PKP\mail\traits\Unsubscribe;
use PKP\security\Role;

class AnnouncementNotify extends Mailable
{
    use Configurable;
    use Recipient;
    use Sender;
    use Unsubscribe;

    protected static ?string $name = 'mailable.announcementNotify.name';
    protected static ?string $description = 'mailable.announcementNotify.description';
    protected static ?string $emailTemplateKey = 'ANNOUNCEMENT';
    protected static array $groupIds = [self::GROUP_OTHER];
    protected static array $fromRoleIds = [Role::ROLE_ID_SUB_EDITOR];
    protected static array $toRoleIds = [Role::ROLE_ID_READER];

    protected static string $announcementTitle = 'announcementTitle';
    protected static string $announcementSummary = 'announcementSummary';
    protected static string $announcementUrl = 'announcementUrl';

    protected Announcement $announcement;
    protected Context $context;

    public function __construct(Context $context, Announcement $announcement)
    {
        parent::__construct(array_slice(func_get_args(), 0, -1));
        $this->announcement = $announcement;
        $this->context = $context;
    }

    /**
     * Add description to a new email template variables
     */
    public static function getDataDescriptions(): array
    {
        $variables = parent::getDataDescriptions();
        $variables[static::$announcementTitle] = __('emailTemplate.variable.announcementTitle');
        $variables[static::$announcementSummary] = __('emailTemplate.variable.announcementSummary');
        $variables[static::$announcementUrl] = __('emailTemplate.variable.announcementUrl');
        return $variables;
    }

    /**
     * Set localized email template variables
     */
    public function setData(?string $locale = null): void
    {
        parent::setData($locale);
        if (is_null($locale)) {
            $locale = Locale::getLocale();
        }

        $request = Application::get()->getRequest();
        $dispatcher = $request->getDispatcher();
        $this->viewData = array_merge(
            $this->viewData,
            [
                static::$announcementTitle => $this->announcement->getData('title', $locale),
                static::$announcementSummary => $this->announcement->getData('descriptionShort', $locale),
                static::$announcementUrl => $dispatcher->url(
                    $request,
                    PKPApplication::ROUTE_PAGE,
                    $this->context->getData('urlPath'),
                    'announcement',
                    'view',
                    $this->announcement->getId()
                ),
            ]
        );
    }

    /**
     * Adds a footer with unsubscribe link
     */
    protected function addFooter(string $locale): Mailable
    {
        $this->setupUnsubscribeFooter($locale, $this->context);
        return $this;
    }
}
