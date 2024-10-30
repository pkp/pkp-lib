<?php

/**
 * @file controllers/grid/users/reviewer/form/CreateReviewerForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CreateReviewerForm
 *
 * @ingroup controllers_grid_users_reviewer_form
 *
 * @brief Form for creating and subsequently adding a reviewer to a submission.
 */

namespace PKP\controllers\grid\users\reviewer\form;

use APP\core\Application;
use PKP\submission\reviewer\suggestion\ReviewerSuggestion;
use APP\facades\Repo;
use APP\notification\NotificationManager;
use APP\submission\Submission;
use APP\template\TemplateManager;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use PKP\core\Core;
use PKP\mail\mailables\ReviewerRegister;
use PKP\notification\Notification;
use PKP\security\Validation;
use PKP\submission\reviewRound\ReviewRound;
use PKP\user\InterestManager;
use Symfony\Component\Mailer\Exception\TransportException;

class CreateReviewerForm extends ReviewerForm
{
    public ?ReviewerSuggestion $reviewerSuggestion = null;

    /**
     * Constructor.
     *
     * @param Submission $submission
     * @param ReviewRound $reviewRound
     * @param ReviewerSuggestion|null $reviewerSuggestion
     */
    public function __construct($submission, $reviewRound, $reviewerSuggestion = null)
    {
        parent::__construct($submission, $reviewRound);
        $this->reviewerSuggestion = $reviewerSuggestion;
        
        $this->setTemplate('controllers/grid/users/reviewer/form/createReviewerForm.tpl');

        // the users register for the site, thus
        // the site primary locale is the required default locale
        $site = Application::get()->getRequest()->getSite();
        $this->addSupportedFormLocale($site->getPrimaryLocale());

        $form = $this;
        $this->addCheck(new \PKP\form\validation\FormValidatorLocale($this, 'givenName', 'required', 'user.profile.form.givenNameRequired', $site->getPrimaryLocale()));
        $this->addCheck(new \PKP\form\validation\FormValidatorCustom($this, 'familyName', 'optional', 'user.profile.form.givenNameRequired.locale', function ($familyName) use ($form) {
            $givenNames = $form->getData('givenName');
            foreach ($familyName as $locale => $value) {
                if (!empty($value) && empty($givenNames[$locale])) {
                    return false;
                }
            }
            return true;
        }));
        $this->addCheck(new \PKP\form\validation\FormValidatorCustom($this, 'username', 'required', 'user.register.form.usernameExists', [Repo::user(), 'getByUsername'], [true], true));
        $this->addCheck(new \PKP\form\validation\FormValidatorUsername($this, 'username', 'required', 'user.register.form.usernameAlphaNumeric'));
        $this->addCheck(new \PKP\form\validation\FormValidatorEmail($this, 'email', 'required', 'user.profile.form.emailRequired'));
        $this->addCheck(new \PKP\form\validation\FormValidatorCustom($this, 'email', 'required', 'user.register.form.emailExists', function ($email) {
            return !Repo::user()->getByEmail($email, true);
        }));
        $this->addCheck(new \PKP\form\validation\FormValidator($this, 'userGroupId', 'required', 'user.profile.form.usergroupRequired'));
    }

    /**
     * @copydoc Form::init()
     */
    public function initData()
    {
        parent::initData();

        $mailable = $this->getMailable();
        $context = Application::get()->getRequest()->getContext();
        $template = Repo::emailTemplate()->getByKey($context->getId(), $mailable::getEmailTemplateKey());
        $this->setData('personalMessage', Mail::compileParams($template->getLocalizedData('body'), $mailable->viewData));

        if ($this->reviewerSuggestion) {
            $this->setData('reviewerSuggestionId', $this->reviewerSuggestion->id);
            $this->setData('familyName', $this->reviewerSuggestion->familyName);
            $this->setData('givenName', $this->reviewerSuggestion->givenName);
            $this->setData('email', $this->reviewerSuggestion->email);
            $this->setData('affiliation', $this->reviewerSuggestion->affiliation);
        }
    }

    /**
     * @copydoc ReviewerForm::fetch
     *
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false)
    {
        $advancedSearchAction = $this->getAdvancedSearchAction($request);
        $this->setReviewerFormAction($advancedSearchAction);
        $site = $request->getSite();
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('sitePrimaryLocale', $site->getPrimaryLocale());
        return parent::fetch($request, $template, $display);
    }

    /**
     * Assign form data to user-submitted data.
     *
     * @see Form::readInputData()
     */
    public function readInputData()
    {
        parent::readInputData();

        $inputData = [
            'givenName',
            'familyName',
            'affiliation',
            'interests',
            'username',
            'email',
            'skipEmail',
            'userGroupId',
        ];

        if ($this->reviewerSuggestion) {
            array_push($inputData, 'reviewerSuggestionId');
        }

        $this->readUserVars($inputData);
    }

    /**
     * @copydoc Form::execute()
     */
    public function execute(...$functionArgs)
    {
        $user = Repo::user()->newDataObject();

        $user->setGivenName($this->getData('givenName'), null);
        $user->setFamilyName($this->getData('familyName'), null);
        $user->setEmail($this->getData('email'));
        $user->setAffiliation($this->getData('affiliation'), null); // Localized
        $user->setInlineHelp(1); // default new reviewers to having inline help visible

        $user->setUsername($this->getData('username'));
        $password = Validation::generatePassword();

        $user->setPassword(Validation::encryptCredentials($this->getData('username'), $password));
        $user->setMustChangePassword(true); // Emailed P/W not safe

        $user->setDateRegistered(Core::getCurrentDate());
        $reviewerId = Repo::user()->add($user);

        // Set the reviewerId in the Form for the parent class to use
        $this->setData('reviewerId', $reviewerId);

        // Insert the user interests
        $interestManager = new InterestManager();
        $interestManager->setInterestsForUser($user, $this->getData('interests'));

        // Assign the selected user group ID to the user
        $userGroupId = (int) $this->getData('userGroupId');
        Repo::userGroup()->assignUserToGroup($reviewerId, $userGroupId);

        if (!$this->getData('skipEmail')) {
            // Send welcome email to user
            $request = Application::get()->getRequest();
            $context = $request->getContext();
            $mailable = new ReviewerRegister($context, $password);
            $mailable->recipients($user);
            $mailable->sender($request->getUser());
            $mailable->replyTo($context->getData('contactEmail'), $context->getData('contactName'));
            $template = Repo::emailTemplate()->getByKey($context->getId(), ReviewerRegister::getEmailTemplateKey());
            $mailable->subject($template->getLocalizedData('subject'));
            $mailable->body($template->getLocalizedData('body'));

            try {
                Mail::send($mailable);
            } catch (TransportException $e) {
                $notificationMgr = new NotificationManager();
                $notificationMgr->createTrivialNotification(
                    $request->getUser()->getId(),
                    Notification::NOTIFICATION_TYPE_ERROR,
                    ['contents' => __('email.compose.error')]
                );
                error_log($e->getMessage());
            }
        }

        if ($this->getData('reviewerSuggestionId')) {
            $this->reviewerSuggestion->markAsApprove(
                Carbon::now(),
                $reviewerId,
                $request->getUser()->getId()
            );
        }

        return parent::execute(...$functionArgs);
    }
}
