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

use PKP\filter\FilterGroup;
use PKP\security\Role;
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
        $userGroup = new UserGroup();
        $userGroup->contextId = $context->getId();

        // Extract the name node element to see if this user group exists already.
        $nodeList = $node->getElementsByTagNameNS($deployment->getNamespace(), 'name');
        if ($nodeList->length > 0) {
            $content = $this->parseLocalizedContent($nodeList->item(0)); // $content[1] contains the localized name.
            $userGroups = UserGroup::query()
                ->withContextIds($context->getId())
                ->get();

            foreach ($userGroups as $testGroup) {
                if (in_array($content[1], $testGroup->name)) {
                    return $testGroup;  // We found one with the same name.
                }
            }

            for ($n = $node->firstChild; $n !== null; $n = $n->nextSibling) {
                if ($n instanceof \DOMElement) {
                    switch ($n->tagName) {
                        case 'role_id':
                            $userGroup->roleId = (int)$n->textContent;
                            break;
                        case 'is_default':
                            $userGroup->isDefault = filter_var($n->textContent, FILTER_VALIDATE_BOOLEAN);
                            break;
                        case 'show_title':
                            $userGroup->showTitle = filter_var($n->textContent, FILTER_VALIDATE_BOOLEAN, ['options' => ['default' => true]]);
                            break;
                        case 'name':
                            $locale = $n->getAttribute('locale');
                            $name = $userGroup->name ?? [];
                            $name[$locale] = $n->textContent;
                            $userGroup->name = $name;
                            break;
                        case 'abbrev':
                            $locale = $n->getAttribute('locale');
                            $abbrev = $userGroup->abbrev ?? [];
                            $abbrev[$locale] = $n->textContent;
                            $userGroup->abbrev = $abbrev;
                            break;
                        case 'permit_self_registration':
                            $userGroup->permitSelfRegistration = filter_var($n->textContent, FILTER_VALIDATE_BOOLEAN);
                            break;
                        case 'permit_metadata_edit':
                            $userGroup->permitMetadataEdit = filter_var($n->textContent, FILTER_VALIDATE_BOOLEAN);
                            break;
                        case 'masthead':
                            $userGroup->masthead = filter_var($n->textContent, FILTER_VALIDATE_BOOLEAN);
                            break;
                    }
                }
            }

            if (!in_array(
                $userGroup->roleId,
                [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_AUTHOR, Role::ROLE_ID_REVIEWER, Role::ROLE_ID_ASSISTANT, Role::ROLE_ID_READER, Role::ROLE_ID_SUBSCRIPTION_MANAGER]
            )) {
                throw new \Exception('Unacceptable role_id ' . $userGroup->roleId);
            }

            $userGroup->save();
            $userGroupId = $userGroup->id;

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
