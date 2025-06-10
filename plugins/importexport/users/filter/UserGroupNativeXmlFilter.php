<?php

/**
 * @file plugins/importexport/users/filter/UserGroupNativeXmlFilter.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserGroupNativeXmlFilter
 *
 * @ingroup plugins_importexport_users
 *
 * @brief Base class that converts a set of user groups to a Native XML document
 */

namespace PKP\plugins\importexport\users\filter;

use DOMDocument;
use PKP\filter\FilterGroup;
use PKP\userGroup\UserGroup;

class UserGroupNativeXmlFilter extends \PKP\plugins\importexport\native\filter\NativeExportFilter
{
    /**
     * Constructor
     *
     * @param FilterGroup $filterGroup
     */
    public function __construct($filterGroup)
    {
        $this->setDisplayName('Native XML user group export');
        parent::__construct($filterGroup);
    }

    //
    // Implement template methods from Filter
    //
    /**
     * @see Filter::process()
     *
     * @param array $userGroups Array of user groups
     *
     * @return DOMDocument
     */
    public function &process(&$userGroups)
    {
        // Create the XML document
        $doc = new DOMDocument('1.0', 'utf-8');
        $deployment = $this->getDeployment();

        // Multiple authors; wrap in a <authors> element
        $rootNode = $doc->createElementNS($deployment->getNamespace(), 'user_groups');
        foreach ($userGroups as $userGroup) {
            $rootNode->appendChild($this->createUserGroupNode($doc, $userGroup));
        }
        $doc->appendChild($rootNode);
        $rootNode->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $rootNode->setAttribute('xsi:schemaLocation', $deployment->getNamespace() . ' ' . $deployment->getSchemaFilename());

        return $doc;
    }

    //
    // UserGroup conversion functions
    //
    /**
     * Create and return a user group node.
     *
     * @param DOMDocument $doc
     * @param UserGroup $userGroup
     *
     * @return \DOMElement
     */
    public function createUserGroupNode($doc, $userGroup)
    {
        $deployment = $this->getDeployment();
        $context = $deployment->getContext();

        // Create the user_group node
        $userGroupNode = $doc->createElementNS($deployment->getNamespace(), 'user_group');

        // Add metadata
        $userGroupNode->appendChild($doc->createElementNS($deployment->getNamespace(), 'role_id', $userGroup->roleId));
        $userGroupNode->appendChild($doc->createElementNS($deployment->getNamespace(), 'context_id', $userGroup->contextId));
        $userGroupNode->appendChild($doc->createElementNS($deployment->getNamespace(), 'is_default', $userGroup->isDefault ? 'true' : 'false'));
        $userGroupNode->appendChild($doc->createElementNS($deployment->getNamespace(), 'show_title', $userGroup->showTitle ? 'true' : 'false'));
        $userGroupNode->appendChild($doc->createElementNS($deployment->getNamespace(), 'permit_self_registration', $userGroup->permitSelfRegistration ? 'true' : 'false'));
        $userGroupNode->appendChild($doc->createElementNS($deployment->getNamespace(), 'permit_metadata_edit', $userGroup->permitMetadataEdit ? 'true' : 'false'));

        $this->createLocalizedNodes($doc, $userGroupNode, 'name', $userGroup->name);
        $this->createLocalizedNodes($doc, $userGroupNode, 'abbrev', $userGroup->abbrev);

        $assignedStages = $userGroup->getAssignedStageIds()->toArray();

        $userGroupNode->appendChild($doc->createElementNS($deployment->getNamespace(), 'stage_assignments', htmlspecialchars(join(':', $assignedStages), ENT_COMPAT, 'UTF-8')));
        $userGroupNode->appendChild($doc->createElementNS($deployment->getNamespace(), 'masthead', $userGroup->masthead ? 'true' : 'false'));
        return $userGroupNode;
    }
}
