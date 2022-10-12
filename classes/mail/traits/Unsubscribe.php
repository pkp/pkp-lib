<?php

/**
 * @file classes/mail/traits/Unsubscribe.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Unsubscribe
 * @ingroup mail_traits
 *
 * @brief trait to embed footer with unsubscribe link to notification emails
 */

namespace PKP\mail\traits;

use APP\core\Application;
use APP\notification\Notification;
use APP\notification\NotificationManager;
use PKP\mail\Mailable;
use PKP\mail\variables\ContextEmailVariable as PKPContextEmailVariable;
use PKP\mail\variables\SubmissionEmailVariable;
use APP\mail\variables\ContextEmailVariable;
use Exception;

trait Unsubscribe
{
    protected static string $unsubscribeUrl = 'unsubscribeUrl';

    // Notification to generate unsubscribe link
    protected Notification $notification;

    /**
     * @var string[] template variables required for the unsubscribe footer
     */
    protected static array $requiredVariables = [PKPContextEmailVariable::class, SubmissionEmailVariable::class];

    abstract function addData(array $data): Mailable;
    abstract function getVariables(): array;

    /**
     * Use this public method to set footer for this email
     */
    public function allowUnsubscribe(Notification $notification): self
    {
        $this->notification = $notification;

        $includedVariables = $this->checkRequiredVariables();
        if (!$includedVariables) {
            throw new Exception(
                'Mailable should include the following template variables: ' .
                implode(",", self::$requiredVariables));
        }
        return $this;
    }

    /**
     * Setup footer with unsubscribe link if notification is deliberately set with self::allowUnsubscribe()
     */
    protected function setupUnsubscribeFooter(string $locale): void
    {
        if (!isset($this->notification)) return;

        $footer = __('emails.footer.unsubscribe', [], $locale); // variables to be compiled with the view/body
        $this->footer = $this->replaceToAppSpecificVariables($footer);

        $notificationManager = new NotificationManager(); /** @var NotificationManager $notificationManager */
        $request = Application::get()->getRequest();
        $this->addData([
            self::$unsubscribeUrl => $notificationManager->getUnsubscribeNotificationUrl($request, $this->notification),
        ]);
    }

    /**
     * @return bool whether mailable contains variables requires for the footer
     */
    protected function checkRequiredVariables(): bool
    {
        $included = [];
        foreach (self::$requiredVariables as $requiredVariable) {
            foreach ($this->getVariables() as $variable) {
                if (is_a($variable, $requiredVariable, true)) {
                    $included[] = $requiredVariable;
                    break;
                }
            }
        }

        return count($included) === 2;
    }

    /**
     * Replace email template variables in the locale string, so they correspond to the application,
     * e.g., contextName => journalName/pressName/serverName
     */
    protected function replaceToAppSpecificVariables(string $footer): string
    {
        $map = [
            '{$' . PKPContextEmailVariable::CONTEXT_NAME . '}' => '{$' . ContextEmailVariable::CONTEXT_NAME . '}',
            '{$' . PKPContextEmailVariable::CONTEXT_URL . '}' => '{$' . ContextEmailVariable::CONTEXT_URL . '}',
        ];

        return str_replace(array_keys($map), array_values($map), $footer);
    }
}
