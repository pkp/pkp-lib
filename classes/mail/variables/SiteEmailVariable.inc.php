<?php

/**
 * @file classes/mail/variables/SiteEmailVariable.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SiteEmailVariable
 * @ingroup mail_variables
 *
 * @brief Represents variables that are associated with a website
 */

namespace PKP\mail\variables;

use PKP\site\Site;

class SiteEmailVariable extends Variable
{
    const SITE_TITLE = 'siteTitle';
    const SITE_CONTACT = 'siteContactName';

    protected Site $site;

    public function __construct(Site $site)
    {
        $this->site = $site;
    }

    /**
     * @copydoc Variable::description()
     */
    protected static function description(): array
    {
        return
        [
            self::SITE_TITLE => __('emailTemplate.variable.site.siteTitle'),
            self::SITE_CONTACT => __('emailTemplate.variable.site.siteContactName'),
        ];
    }

    /**
     * @copydoc Variable::values()
     */
    protected function values(): array
    {
       return
       [
           self::SITE_TITLE => $this->getSiteTitle(),
           self::SITE_CONTACT => $this->getSiteContactName(),
       ];
    }

    protected function getSiteTitle() : ?array
    {
        return $this->site->getData('title');
    }

    /**
     * Name of a person indicated as website's primary contact in supported locales
     */
    protected function getSiteContactName() : ?array
    {
        return $this->site->getData('contactName');
    }
}
