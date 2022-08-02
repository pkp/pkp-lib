<?php

/**
 * @file classes/mail/mailables/EditorialReminder.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EditorialReminder
 * @ingroup mail_mailables
 *
 * @brief Email is sent automatically to an editor to remind them of outstanding tasks
 */

namespace PKP\mail\mailables;

use APP\core\Application;
use APP\publication\Publication;
use APP\submission\Submission;
use PKP\context\Context;
use PKP\mail\Mailable;
use PKP\mail\traits\Configurable;
use PKP\mail\traits\Recipient;
use PKP\security\Role;

class EditorialReminder extends Mailable
{
    use Configurable;
    use Recipient;

    public const OUTSTANDING_TASKS = 'outstandingTasks';
    public const NUMBER_OF_SUBMISSIONS = 'numberOfSubmissions';

    protected static ?string $name = 'mailable.editorialReminder.name';
    protected static ?string $description = 'mailable.editorialReminder.description';
    protected static ?string $emailTemplateKey = 'EDITORIAL_REMINDER';
    protected static array $groupIds = [self::GROUP_REVIEW];
    protected static array $toRoleIds = [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR];

    protected Context $context;
    protected array $outstanding = [];

    /** @var Submission[] $submissions */
    protected array $submissions = [];

    /** @TODO: docblock for $outstanding */
    public function __construct(Context $context)
    {
        parent::__construct(func_get_args());
        $this->context = $context;
    }

    /**
     * Add a {$outstandingTasks} variable
     *
     * @TODO docblock params
     */
    public function setOutstandingTasks(array $outstanding, array $submissions, int $numberOfSubmissions): self
    {
        $outstandingTasks = [];
        foreach ($outstanding as $submissionId => $task) {
            /** @var Submission $submission */
            $submission = $submissions[$submissionId];
            /** @var Publication $publication */
            $publication = $submission->getCurrentPublication();
            $url = Application::get()->getRequest()->getDispatcher()->url(
                Application::get()->getRequest(),
                Application::ROUTE_PAGE,
                $this->context->getPath(),
                'workflow',
                'access',
                $submission->getId()
            );

            $outstandingTasks[] = '
    <tr>
        <td style="color: red; vertical-align: top; width: 25px;">⬤</td>
        <td style="vertical-align: top">
            ' . $task . '<br />
            <a href="' . $url . '">'
                . $submission->getId()
                . ' — '
                . htmlspecialchars($publication->getShortAuthorString())
                . ' — '
                . htmlspecialchars($publication->getLocalizedFullTitle())
                . '</a><br />
            <br />
        </td>
    </tr>';
        }

        $this->addData([
            self::OUTSTANDING_TASKS => '<table>' . join('', $outstandingTasks) . '</table>',
            self::NUMBER_OF_SUBMISSIONS => $numberOfSubmissions,
        ]);

        return $this;
    }

    /**
     * @copydoc Mailable::getDataDescriptions()
     */
    public static function getDataDescriptions(): array
    {
        $variables = parent::getDataDescriptions();
        $variables[static::OUTSTANDING_TASKS] = __('emailTemplate.variable.editorialReminder.outstandingTasks');
        $variables[static::NUMBER_OF_SUBMISSIONS] = __('emailTemplate.variable.editorialReminder.numberOfSubmissions');
        return $variables;
    }
}
