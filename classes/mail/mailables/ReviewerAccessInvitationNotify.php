<?php

namespace PKP\mail\mailables;

use APP\facades\Repo;
use PKP\context\Context;
use PKP\core\Core;
use PKP\facades\Locale;
use PKP\invitation\core\enums\InvitationAction;
use PKP\invitation\invitations\reviewerAccess\ReviewerAccessInvite;
use PKP\mail\Mailable;
use PKP\mail\traits\Configurable;
use PKP\mail\traits\Recipient;
use PKP\mail\traits\Sender;
use PKP\security\Role;

class ReviewerAccessInvitationNotify extends Mailable
{
    use Recipient;
    use Configurable;
    use Sender;

    protected static ?string $name = 'mailable.reviewerAccessInvitationNotify.name';
    protected static ?string $description = 'mailable.reviewerAccessInvitationNotify.description';
    protected static ?string $emailTemplateKey = 'REVIEWER_ACCESS_INVITATION';
    protected static array $groupIds = [self::GROUP_OTHER];
    protected static array $fromRoleIds = [
        self::FROM_SYSTEM,
    ];
    protected static array $toRoleIds = [
        Role::ROLE_ID_SUB_EDITOR,
        Role::ROLE_ID_ASSISTANT,
        Role::ROLE_ID_AUTHOR,
        Role::ROLE_ID_READER,
        Role::ROLE_ID_REVIEWER,
        Role::ROLE_ID_SUBSCRIPTION_MANAGER,
    ];

    protected static string $recipientName = 'recipientName';
    protected static string $inviterName = 'inviterName';
    protected static string $submissionTitle= 'submissionTitle';
    protected static string $submissionAbstract = 'submissionAbstract';
    protected static string $reviewDueDate = 'reviewDueDate';
    protected static string $contextName = 'contextName';
    protected static string $acceptUrl = 'acceptUrl';
    protected static string $declineUrl = 'declineUrl';

    private ReviewerAccessInvite $invitation;

    public function __construct(Context $context, ReviewerAccessInvite $invitation)
    {
        parent::__construct(array_slice(func_get_args(), 0, -1));

        $this->invitation = $invitation;
    }

    /**
     * Add description to a new email template variables
     */
    public static function getDataDescriptions(): array
    {
        $variables = parent::getDataDescriptions();

        $variables[static::$recipientName] = __('emailTemplate.variable.invitation.recipientName');
        $variables[static::$inviterName] = __('emailTemplate.variable.invitation.inviterName');
        $variables[static::$submissionTitle] = __('emailTemplate.variable.reviewerInvitation.submissionTitle');
        $variables[static::$submissionAbstract] = __('emailTemplate.variable.reviewerInvitation.submissionAbstract');
        $variables[static::$reviewDueDate] = __('emailTemplate.variable.reviewerInvitation.reviewDueDate');
        $variables[static::$acceptUrl] = __('emailTemplate.variable.invitation.acceptUrl');
        $variables[static::$declineUrl] = __('emailTemplate.variable.invitation.declineUrl');

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

        // Invitation User
        $sendIdentity = $this->invitation->getMailableReceiver($locale);

        // Inviter
        $inviter = $this->invitation->getInviter();

        //submission
        $submission = Repo::submission()->get((int) $this->invitation->getPayload()->submissionId);
        if (!$submission) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
        }
        $publication = $submission->getCurrentPublication();

        $context = $this->invitation->getContext();

        $targetPath = Core::getBaseDir() . '/lib/pkp/styles/mailables/style.css';
        $emailTemplateStyle = file_get_contents($targetPath);

        $recipientName = !empty($sendIdentity->getFullName()) ? $sendIdentity->getFullName() : $sendIdentity->getEmail();

        // Set view data for the template
        $this->viewData = array_merge(
            $this->viewData,
            [
                static::$recipientName => $recipientName,
                static::$inviterName => $inviter->getFullName(),
                static::$submissionTitle => $publication->getData('title', $locale),
                static::$submissionAbstract => $publication->getData('abstract', $locale),
                static::$reviewDueDate => $this->invitation->getPayload()->reviewDueDate,
                static::$contextName => $context->getLocalizedName(),
                static::$acceptUrl => $this->invitation->getActionURL(InvitationAction::ACCEPT),
                static::$declineUrl => $this->invitation->getActionURL(InvitationAction::DECLINE),
                static::EMAIL_TEMPLATE_STYLE_PROPERTY => $emailTemplateStyle,
            ]
        );
    }
}
