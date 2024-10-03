<?php

/**
 * @file classes/mail/traits/OrcidVariables.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class OrcidVariables
 *
 * @ingroup mail_traits
 *
 * @brief Mailable trait to set common ORCID mailable variables
 */

namespace PKP\mail\traits;

use APP\core\Application;
use APP\facades\Repo;
use PKP\context\Context;
use PKP\mail\Mailable;

trait OrcidVariables
{
    protected static string $authorOrcidUrl = 'authorOrcidUrl';
    protected static string $orcidAboutUrl = 'orcidAboutUrl';
    protected static string $principalContactSignature = 'principalContactSignature';
    abstract public function addData(array $data): Mailable;

    /**
     * Description of additional template variables
     */
    public static function getOrcidDataDescriptions(): array
    {
        return [
            self::$authorOrcidUrl => __('emailTemplate.variable.authorOrcidUrl'),
            self::$orcidAboutUrl => __('emailTemplate.variable.orcidAboutUrl'),
        ];
    }

    /**
     * Set values for additional email template variables
     */
    protected function setupOrcidVariables(string $oauthUrl, Context $context): void
    {
        $request = Application::get()->getRequest();
        $dispatcher = Application::get()->getDispatcher();
        $principalContact = Repo::user()->getByEmail($context->getData('contactEmail'));

        $this->addData([
            self::$authorOrcidUrl => $oauthUrl,
            self::$orcidAboutUrl => $dispatcher->url($request, Application::ROUTE_PAGE, null, 'orcid', 'about', urlLocaleForPage: ''),
            self::$principalContactSignature => $principalContact->getLocalizedSignature(),
        ]);
    }
}
