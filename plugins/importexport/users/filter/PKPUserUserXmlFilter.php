<?php

/**
 * @file plugins/importexport/users/filter/PKPUserUserXmlFilter.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2000-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPUserUserXmlFilter
 *
 * @brief Base class that converts a set of users to a User XML document
 */

namespace PKP\plugins\importexport\users\filter;

use APP\facades\Repo;
use DOMDocument;
use PKP\config\Config;
use PKP\db\DAORegistry;
use PKP\filter\FilterDAO;
use PKP\filter\FilterGroup;
use PKP\plugins\importexport\native\filter\NativeExportFilter;
use PKP\security\Role;
use PKP\user\User;
use PKP\userGroup\relationships\UserUserGroup;
use PKP\userGroup\UserGroup;

class PKPUserUserXmlFilter extends NativeExportFilter
{
    /**
     * Constructor
     *
     * @param FilterGroup $filterGroup
     */
    public function __construct($filterGroup)
    {
        $this->setDisplayName('User XML user export');
        parent::__construct($filterGroup);
    }

    //
    // Implement template methods from Filter
    //
    /**
     * @see Filter::process()
     *
     * @param array $users Array of users
     *
     * @return DOMDocument
     */
    public function &process(&$users)
    {
        // Create the XML document
        $doc = new DOMDocument('1.0', 'utf-8');
        $deployment = $this->getDeployment();

        $rootNode = $doc->createElementNS($deployment->getNamespace(), 'PKPUsers');
        $this->addUserGroups($doc, $rootNode);

        // Multiple users; wrap in a <users> element
        $usersNode = $doc->createElementNS($deployment->getNamespace(), 'users');
        foreach ($users as $user) {
            $usersNode->appendChild($this->createPKPUserNode($doc, $user));
        }
        $rootNode->appendChild($usersNode);
        $doc->appendChild($rootNode);
        $rootNode->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $rootNode->setAttribute('xsi:schemaLocation', $deployment->getNamespace() . ' ' . $deployment->getSchemaFilename());

        return $doc;
    }

    //
    // \PKP\author\Author conversion functions
    //
    /**
     * Create and return a user node.
     *
     * @param DOMDocument $doc
     * @param User $user
     *
     * @return \DOMElement
     */
    public function createPKPUserNode($doc, $user)
    {
        $deployment = $this->getDeployment();
        $context = $deployment->getContext();

        // Create the user node
        $userNode = $doc->createElementNS($deployment->getNamespace(), 'user');

        // Add metadata
        $this->createLocalizedNodes($doc, $userNode, 'givenname', $user->getGivenName(null));
        $this->createLocalizedNodes($doc, $userNode, 'familyname', $user->getFamilyName(null));

        if ($user->getAffiliation(null) && count(array_filter($user->getAffiliation(null))) > 0) {
            $affiliationNode = $doc->createElementNS($deployment->getNamespace(), 'affiliation');
            $this->createLocalizedNodes($doc, $affiliationNode, 'name', $user->getAffiliation(null));
            $userNode->appendChild($affiliationNode);
        }

        $this->createOptionalNode($doc, $userNode, 'country', $user->getCountry());
        $userNode->appendChild($doc->createElementNS($deployment->getNamespace(), 'email', htmlspecialchars($user->getEmail(), ENT_COMPAT, 'UTF-8')));
        $this->createOptionalNode($doc, $userNode, 'url', $user->getUrl());
        $this->createOptionalNode($doc, $userNode, 'orcid', $user->getOrcid());
        if (is_array($user->getBiography(null))) {
            $this->createLocalizedNodes($doc, $userNode, 'biography', $user->getBiography(null));
        }

        $userNode->appendChild($doc->createElementNS($deployment->getNamespace(), 'username', htmlspecialchars($user->getUsername(), ENT_COMPAT, 'UTF-8')));

        $this->createOptionalNode($doc, $userNode, 'gossip', $user->getGossip());

        if (is_array($user->getSignature(null))) {
            $this->createLocalizedNodes($doc, $userNode, 'signature', $user->getSignature(null));
        }

        $passwordNode = $doc->createElementNS($deployment->getNamespace(), 'password');
        $passwordNode->setAttribute('is_disabled', $user->getDisabled() ? 'true' : 'false');
        $passwordNode->setAttribute('must_change', $user->getMustChangePassword() ? 'true' : 'false');
        $passwordNode->setAttribute('encryption', Config::getVar('security', 'encryption'));
        $passwordNode->appendChild($doc->createElementNS($deployment->getNamespace(), 'value', htmlspecialchars($user->getPassword(), ENT_COMPAT, 'UTF-8')));

        $userNode->appendChild($passwordNode);

        $this->createOptionalNode($doc, $userNode, 'date_registered', $user->getDateRegistered());
        $this->createOptionalNode($doc, $userNode, 'date_last_login', $user->getDateLastLogin());
        $this->createOptionalNode($doc, $userNode, 'date_last_email', $user->getDateLastEmail());
        $this->createOptionalNode($doc, $userNode, 'date_validated', $user->getDateValidated());
        $this->createOptionalNode($doc, $userNode, 'inline_help', $user->getInlineHelp() ? 'true' : 'false');
        $this->createOptionalNode($doc, $userNode, 'auth_string', $user->getAuthStr());
        $this->createOptionalNode($doc, $userNode, 'phone', $user->getPhone());
        $this->createOptionalNode($doc, $userNode, 'mailing_address', $user->getMailingAddress());
        $this->createOptionalNode($doc, $userNode, 'billing_address', $user->getBillingAddress());
        $this->createOptionalNode($doc, $userNode, 'locales', join(':', $user->getLocales()));
        if ($user->getDisabled()) {
            $this->createOptionalNode($doc, $userNode, 'disabled_reason', $user->getDisabledReason());
        }

        $userGroups = UserGroup::withUserIds([$user->getId()])
            ->withContextIds([$context->getId()])
            ->get();

        foreach ($userGroups as $userGroup) {
            $userUserGroups = UserUserGroup::withUserGroupIds([$userGroup->id])
                ->withUserId($user->getId())
                ->get();
            foreach ($userUserGroups as $userUserGroup) {
                $userUserGroupNode = $doc->createElementNS($deployment->getNamespace(), 'user_user_group');
                $userUserGroupNode->appendChild($doc->createElementNS($deployment->getNamespace(), 'user_group_ref', htmlspecialchars($userGroup->name[$context->getPrimaryLocale()], ENT_COMPAT, 'UTF-8')));
                if ($userUserGroup->dateStart) {
                    $userUserGroupNode->appendChild($doc->createElementNS($deployment->getNamespace(), 'date_start', $userUserGroup->dateStart));
                }
                if ($userUserGroup->dateEnd) {
                    $userUserGroupNode->appendChild($doc->createElementNS($deployment->getNamespace(), 'date_end', $userUserGroup->dateEnd));
                }
                $masthead = $userUserGroup->masthead;
                if ($userGroup->roleId = Role::ROLE_ID_REVIEWER) {
                    $masthead = true;
                }
                $userUserGroupNode->appendChild($doc->createElementNS($deployment->getNamespace(), 'masthead', $masthead ? 'true' : 'false'));
                $userNode->appendChild($userUserGroupNode);
            }
        }

        // Add Reviewing Interests, if any.
        $interests = Repo::userInterest()->getInterestsString($user);
        $this->createOptionalNode($doc, $userNode, 'review_interests', $interests);

        return $userNode;
    }

    public function addUserGroups($doc, $rootNode)
    {
        $deployment = $this->getDeployment();
        $context = $deployment->getContext();
        $userGroupsNode = $doc->createElementNS($deployment->getNamespace(), 'user_groups');

        $userGroups = UserGroup::withContextIds([$context->getId()])->get();

        $filterDao = DAORegistry::getDAO('FilterDAO'); /** @var FilterDAO $filterDao */
        $userGroupExportFilters = $filterDao->getObjectsByGroup('usergroup=>user-xml');
        assert(count($userGroupExportFilters) == 1); // Assert only a single serialization filter
        $exportFilter = array_shift($userGroupExportFilters);
        $exportFilter->setDeployment($this->getDeployment());

        $userGroupsArray = $userGroups->all();
        $userGroupsDoc = $exportFilter->execute($userGroupsArray);
        if ($userGroupsDoc->documentElement instanceof \DOMElement) {
            $clone = $doc->importNode($userGroupsDoc->documentElement, true);
            $rootNode->appendChild($clone);
        }
    }
}
