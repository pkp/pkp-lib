<?php

/**
 * @file controllers/grid/users/author/form/PKPAuthorForm.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2003-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPAuthorForm
 *
 * @ingroup controllers_grid_users_author_form
 *
 * @deprecated 3.4
 *
 * @brief Form for adding/editing a author
 */

namespace PKP\controllers\grid\users\author\form;

use APP\author\Author;
use APP\facades\Repo;
use APP\publication\Publication;
use APP\submission\Submission;
use APP\template\TemplateManager;
use Exception;
use PKP\affiliation\Affiliation;
use PKP\context\Context;
use PKP\facades\Locale;
use PKP\form\Form;
use PKP\security\Role;

class PKPAuthorForm extends Form
{
    /** @var Publication publication associated with the contributor being edited */
    public $_publication;

    /** @var Author the author being edited */
    public $_author;

    /**
     * Constructor.
     *
     * @param Publication $publication
     */
    public function __construct($publication, $author)
    {
        parent::__construct('controllers/grid/users/author/form/authorForm.tpl');
        $this->setPublication($publication);
        $this->setAuthor($author);

        // the publication locale should be the default/required locale
        $this->setDefaultFormLocale($publication->getData('locale'));

        // Validation checks for this form
        $form = $this;
        $this->addCheck(new \PKP\form\validation\FormValidatorLocale($this, 'givenName', 'required', 'user.profile.form.givenNameRequired', $this->defaultLocale));
        $this->addCheck(new \PKP\form\validation\FormValidatorCustom($this, 'familyName', 'optional', 'user.profile.form.givenNameRequired.locale', function ($familyName) use ($form) {
            $givenNames = $form->getData('givenName');
            foreach ($familyName as $locale => $value) {
                if (!empty($value) && empty($givenNames[$locale])) {
                    return false;
                }
            }
            return true;
        }));
        $this->addCheck(new \PKP\form\validation\FormValidatorLocale($this, 'affiliation', 'otional', 'user.profile.form.affiliationRequired.locale', $this->defaultLocale));
        $this->addCheck(new \PKP\form\validation\FormValidatorEmail($this, 'email', 'required', 'form.emailRequired'));
        $this->addCheck(new \PKP\form\validation\FormValidatorUrl($this, 'userUrl', 'optional', 'user.profile.form.urlInvalid'));
        $this->addCheck(new \PKP\form\validation\FormValidator($this, 'userGroupId', 'required', 'submission.submit.form.contributorRoleRequired'));
        $this->addCheck(new \PKP\form\validation\FormValidatorPost($this));
        $this->addCheck(new \PKP\form\validation\FormValidatorCSRF($this));
    }

    //
    // Getters and Setters
    //
    /**
     * Get the author
     *
     */
    public function getAuthor(): ?Author
    {
        return $this->_author;
    }

    /**
     * Set the author
     *
     * @param Author $author
     */
    public function setAuthor($author)
    {
        $this->_author = $author;
    }

    /**
     * Get the Publication
     *
     * @return Publication
     */
    public function getPublication()
    {
        return $this->_publication;
    }

    /**
     * Set the Publication
     *
     * @param Publication $publication
     */
    public function setPublication($publication)
    {
        $this->_publication = $publication;
    }


    //
    // Overridden template methods
    //
    /**
     * Initialize form data from the associated author.
     */
    public function initData()
    {
        $author = $this->getAuthor();

        if ($author) {
            $this->_data = [
                'authorId' => $author->getId(),
                'givenName' => $author->getGivenName(null),
                'familyName' => $author->getFamilyName(null),
                'preferredPublicName' => $author->getPreferredPublicName(null),
                'affiliation' => $this->getFormFieldFromAffiliation(current($author->getAffiliations())), // in this form only used by the QuickSubmitPlugin author has only one affiliation
                'country' => $author->getCountry(),
                'email' => $author->getEmail(),
                'userUrl' => $author->getUrl(),
                'competingInterests' => $author->getCompetingInterests(null),
                'userGroupId' => $author->getUserGroupId(),
                'biography' => $author->getBiography(null),
                'primaryContact' => $this->getPublication()->getData('primaryContactId') === $author->getId(),
                'includeInBrowse' => $author->getIncludeInBrowse(),
            ];
        } else {
            // assume authors should be listed unless otherwise specified.
            $this->_data = ['includeInBrowse' => true];
        }
        // in order to be able to use the hook
        return parent::initData();
    }

    /**
     * @copydoc Form::fetch()
     *
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false)
    {
        $context = $request->getContext();
        $authorUserGroups = Repo::userGroup()->getByRoleIds([Role::ROLE_ID_AUTHOR], $context->getId());
        $publication = $this->getPublication();
        $countries = [];
        foreach (Locale::getCountries() as $country) {
            $countries[$country->getAlpha2()] = $country->getLocalName();
        }
        asort($countries);
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'submissionId' => $publication->getData('submissionId'),
            'publicationId' => $publication->getId(),
            'countries' => $countries,
            'authorUserGroups' => $authorUserGroups,
            'requireAuthorCompetingInterests' => $context->getData('requireAuthorCompetingInterests'),
        ]);

        return parent::fetch($request, $template, $display);
    }

    /**
     * Assign form data to user-submitted data.
     *
     * @see Form::readInputData()
     */
    public function readInputData()
    {
        $this->readUserVars([
            'authorId',
            'givenName',
            'familyName',
            'preferredPublicName',
            'affiliation',
            'country',
            'email',
            'userUrl',
            'competingInterests',
            'userGroupId',
            'biography',
            'primaryContact',
            'includeInBrowse',
        ]);
    }

    /**
     * Save author
     *
     * @see Form::execute()
     */
    public function execute(...$functionParams)
    {
        $publication = $this->getPublication(); /** @var Publication $publication */
        $submission = Repo::submission()->get($publication->getData('submissionId'));
        $context = app()->get('context')->get($submission->getData('contextId'));

        $author = $this->getAuthor();
        if (!$author) {
            // this is a new submission contributor
            $this->_author = Repo::author()->newDataObject();
            $author = $this->getAuthor();
            $author->setData('publicationId', $publication->getId());
            $author->setData('seq', count($publication->getData('authors')));
            $existingAuthor = false;
        } else {
            $existingAuthor = true;
            if ($publication->getId() !== $author->getData('publicationId')) {
                throw new \Exception('Invalid author!');
            }
        }

        $author->setGivenName(array_map(trim(...), $this->getData('givenName')), null);
        $author->setFamilyName($this->getData('familyName'), null);
        $author->setPreferredPublicName($this->getData('preferredPublicName'), null);
        $newAffiliation = $this->getAffiliationFromFormField($this->getData('affiliation'), $submission, $context);
        $author->setAffiliations($newAffiliation ? [$newAffiliation] : []);
        $author->setCountry($this->getData('country'));
        $author->setEmail($this->getData('email'));
        $author->setUrl($this->getData('userUrl'));
        if ($context->getData('requireAuthorCompetingInterests')) {
            $author->setCompetingInterests($this->getData('competingInterests'), null);
        }
        $author->setUserGroupId($this->getData('userGroupId'));
        $author->setBiography($this->getData('biography'), null); // localized
        $author->setIncludeInBrowse(($this->getData('includeInBrowse') ? true : false));

        // in order to be able to use the hook
        parent::execute(...$functionParams);

        if ($existingAuthor) {
            Repo::author()->edit($author, []);
            $authorId = $author->getId();
        } else {
            $authorId = Repo::author()->add($author);
        }

        if ($this->getData('primaryContact')) {
            $params = ['primaryContactId' => $authorId];
            $errors = Repo::publication()->validate(
                $publication,
                $params,
                $submission,
                $context
            );
            if (!empty($errors)) {
                throw new Exception('Invalid primary contact ID. This author can not be a primary contact.');
            }
            Repo::publication()->edit($publication, $params);
        } else {
            // Log an event when publication data is updated
            $publication = Repo::publication()->edit($publication, []);
        }
        return $authorId;
    }

    /**
     * Get this affiliation form field value (as array($locale => $name)) from author affiliation
     */
    protected function getFormFieldFromAffiliation(Affiliation $affiliation): array
    {
        $publication = $this->getPublication(); /** @var Publication $publication */
        $submission = Repo::submission()->get($publication->getData('submissionId'));
        $context = app()->get('context')->get($submission->getData('contextId'));

        $allowedLocales = $submission->getPublicationLanguages($context->getSupportedSubmissionMetadataLocales());
        return $affiliation->getAffiliationName(null, $allowedLocales);
    }

    /**
     * Get author affiliation from this affiliation form field.
     */
    protected function getAffiliationFromFormField(array $oldAffiliation, Submission $submission, Context $context): ?Affiliation
    {
        $allowedLocales = $submission->getPublicationLanguages($context->getSupportedSubmissionMetadataLocales());

        $affiliation = Repo::affiliation()->newDataObject();
        foreach ($oldAffiliation as $locale => $name) {
            $ror = Repo::ror()->getCollector()->filterByName($name)->getMany()->first();
            if ($ror) {
                $affiliation->setRor($ror->getRor());
                break;
            } else {
                if (in_array($locale, $allowedLocales)) {
                    $affiliation->setName($name, $locale);
                }
            }
        }
        if ($affiliation->getRor()) {
            $affiliation->setName(null);
        }
        return ($affiliation->getRor() || $affiliation->getName()) ? $affiliation : null;
    }
}
