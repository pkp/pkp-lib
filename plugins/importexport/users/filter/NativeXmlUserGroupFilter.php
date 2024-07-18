<?php

/**
 * @file plugins/importexport/users/filter/NativeXmlUserGroupFilter.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NativeXmlUserGroupFilter
 *
 * @ingroup plugins_importexport_users
 *
 * @brief Base class that converts a Native XML document to a set of user groups
 */

namespace PKP\plugins\importexport\users\filter;

use APP\facades\Repo;
use PKP\filter\FilterGroup;
use PKP\userGroup\relationships\UserGroupStage;
use PKP\userGroup\UserGroup;

class NativeXmlUserGroupFilter extends \PKP\plugins\importexport\native\filter\NativeImportFilter
{
    /**
     * Constructor
     *
     * @param FilterGroup $filterGroup
     */
    public function __construct($filterGroup)
    {
        $this->setDisplayName('Native XML user group import');
        parent::__construct($filterGroup);
    }

    //
    // Implement template methods from NativeImportFilter
    //
    /**
     * Return the plural element name
     *
     * @return string
     */
    public function getPluralElementName()
    {
        return 'user_groups';
    }

    /**
     * Get the singular element name
     *
     * @return string
     */
    public function getSingularElementName()
    {
        return 'user_group';
    }

    /**
     * Handle a user_group element
     *
     * @param \DOMElement $node
     *
     * @return UserGroup Array of UserGroup objects
     */
    public function handleElement($node)
    {
        $deployment = $this->getDeployment();
        $context = $deployment->getContext();

        // Create the UserGroup object.
        $userGroup = Repo::userGroup()->newDataObject();
        $userGroup->setContextId($context->getId());

        // Extract the name node element to see if this user group exists already.
        $nodeList = $node->getElementsByTagNameNS($deployment->getNamespace(), 'name');
        if ($nodeList->length > 0) {
            $content = $this->parseLocalizedContent($nodeList->item(0)); // $content[1] contains the localized name.
            $userGroups = Repo::userGroup()->getCollector()
                ->filterByContextIds([$context->getId()])
                ->getMany();

            foreach ($userGroups as $testGroup) {
                if (in_array($content[1], $testGroup->getName(null))) {
                    return $testGroup;  // we found one with the same name.
                }
            }

            for ($n = $node->firstChild; $n !== null; $n = $n->nextSibling) {
                if ($n instanceof \DOMElement) {
                    switch ($n->tagName) {
                        case 'role_id': $userGroup->setRoleId($n->textContent);
                            break;
                        case 'is_default': $userGroup->setDefault($n->textContent ?? false);
                            break;
                        case 'show_title': $userGroup->setShowTitle($n->textContent ?? true);
                            break;
                        case 'name': $userGroup->setName($n->textContent, $n->getAttribute('locale'));
                            break;
                        case 'abbrev': $userGroup->setAbbrev($n->textContent, $n->getAttribute('locale'));
                            break;
                        case 'permit_self_registration': $userGroup->setPermitSelfRegistration($n->textContent ?? false);
                            break;
                        case 'permit_metadata_edit': $userGroup->setPermitMetadataEdit($n->textContent ?? false);
                            break;
                    }
                }
            }

            $userGroupId = Repo::userGroup()->add($userGroup);

            $stageNodeList = $node->getElementsByTagNameNS($deployment->getNamespace(), 'stage_assignments');
            if ($stageNodeList->length == 1) {
                $n = $stageNodeList->item(0);
                $assignedStages = preg_split('/:/', $n->textContent);
                foreach ($assignedStages as $stage) {
                    if ($stage >= WORKFLOW_STAGE_ID_SUBMISSION && $stage <= WORKFLOW_STAGE_ID_PRODUCTION) {
                        UserGroupStage::create([
                            'contextId' => $context->getId(),
                            'userGroupId' => $userGroupId,
                            'stageId' => $stage
                        ]);
                    }
                }
            }

            return $userGroup;
        } else {
            throw new \Exception('Unable to find "name" userGroup node element.  Check import XML document structure for validity.');
        }
    }
}
