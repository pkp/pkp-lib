<?php

namespace PKP\mail\traits;

use APP\core\Application;
use PKP\mail\Mailable;

trait OrcidVariables
{
    protected static string $authorOrcidUrl = 'authorOrcidUrl';
    protected static string $orcidAboutUrl = 'orcidAboutUrl';

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
    protected function setupOrcidVariables(string $oauthUrl): void
    {
        $request = Application::get()->getRequest();
        $dispatcher = Application::get()->getDispatcher();
        $this->addData([
            self::$authorOrcidUrl => $oauthUrl,
            self::$orcidAboutUrl => $dispatcher->url($request, Application::ROUTE_PAGE, null, 'orcidapi', 'about'),
        ]);
    }
}
