<?php

/**
 * @file plugins/importexport/users/filter/UserXmlPKPUserFilter.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserXmlPKPUserFilter
 * @ingroup plugins_importexport_users
 *
 * @brief Base class that converts a User XML document to a set of users
 */

import('lib.pkp.plugins.importexport.native.filter.NativeImportFilter');

class UserXmlPKPUserFilter extends NativeImportFilter {
	/**
	 * Constructor
	 * @param $filterGroup FilterGroup
	 */
	function __construct($filterGroup) {
		$this->setDisplayName('User XML user import');
		parent::__construct($filterGroup);
	}

	//
	// Implement template methods from NativeImportFilter
	//
	/**
	 * Return the plural element name
	 * @return string
	 */
	function getPluralElementName() {
		return 'PKPUsers';
	}

	//
	// Implement template methods from PersistableFilter
	//
	/**
	 * @copydoc PersistableFilter::getClassName()
	 */
	function getClassName() {
		return 'lib.pkp.plugins.importexport.users.filter.UserXmlPKPUserFilter';
	}

	/**
	 * Handle a user_groups element
	 * @param $node DOMElement
	 * @return array Array of UserGroup objects
	 */
	function parseUserGroup($node) {

		$filterDao = DAORegistry::getDAO('FilterDAO'); /* @var $filterDao FilterDAO */
		$importFilters = $filterDao->getObjectsByGroup('user-xml=>usergroup');
		assert(count($importFilters)==1); // Assert only a single unserialization filter
		$importFilter = array_shift($importFilters);
		$importFilter->setDeployment($this->getDeployment());
		$userGroupDoc = new DOMDocument();
		$userGroupDoc->appendChild($userGroupDoc->importNode($node, true));
		return $importFilter->execute($userGroupDoc);
	}

	/**
	 * Handle a users element
	 * @param $node DOMElement
	 * @return array Array of User objects
	 */
	function parseUser($node) {
		$deployment = $this->getDeployment();
		$context = $deployment->getContext();
		$site = $deployment->getSite();

		// Create the data object
		$userDao = DAORegistry::getDAO('UserDAO'); /* @var $userDao UserDAO */
		$user = $userDao->newDataObject();

		// Password encryption
		$encryption = null;

		// Handle metadata in subelements
		for ($n = $node->firstChild; $n !== null; $n=$n->nextSibling) if (is_a($n, 'DOMElement')) switch($n->tagName) {
			case 'username': $user->setUsername($n->textContent); break;
			case 'givenname':
				$locale = $n->getAttribute('locale');
				if (empty($locale)) $locale = $site->getPrimaryLocale();
				$user->setGivenName($n->textContent, $locale);
				break;
			case 'familyname':
				$locale = $n->getAttribute('locale');
				if (empty($locale)) $locale = $site->getPrimaryLocale();
				$user->setFamilyName($n->textContent, $locale);
				break;
			case 'affiliation':
				$locale = $n->getAttribute('locale');
				if (empty($locale)) $locale = $site->getPrimaryLocale();
				$user->setAffiliation($n->textContent, $locale);
				break;
			case 'country': $user->setCountry($n->textContent); break;
			case 'email': $user->setEmail($n->textContent); break;
			case 'url': $user->setUrl($n->textContent); break;
			case 'orcid': $user->setOrcid($n->textContent); break;
			case 'phone': $user->setPhone($n->textContent); break;
			case 'billing_address': $user->setBillingAddress($n->textContent); break;
			case 'mailing_address': $user->setMailingAddress($n->textContent); break;
			case 'biography':
				$locale = $n->getAttribute('locale');
				if (empty($locale)) $locale = $site->getPrimaryLocale();
				$user->setBiography($n->textContent, $locale);
				break;
			case 'gossip': $user->setGossip($n->textContent); break;
			case 'signature':
				$locale = $n->getAttribute('locale');
				if (empty($locale)) $locale = $site->getPrimaryLocale();
				$user->setSignature($n->textContent, $locale);
				break;
			case 'date_registered': $user->setDateRegistered($n->textContent); break;
			case 'date_last_login': $user->setDateLastLogin($n->textContent); break;
			case 'date_last_email': $user->setDateLastEmail($n->textContent); break;
			case 'date_validated': $user->setDateValidated($n->textContent); break;
			case 'inline_help':$n->textContent == 'true' ? $user->setInlineHelp(true) : $user->setInlineHelp(false) ; break;
			case 'auth_id': $user->setAuthId($n->textContent); break;
			case 'auth_string': $user->setAuthString($n->textContent); break;
			case 'disabled_reason': $user->setDisabledReason($n->textContent); break;
			case 'locales': $user->setLocales(preg_split('/:/', $n->textContent)); break;
			case 'password':
				if ($n->getAttribute('must_change') == 'true') {
					$user->setMustChangePassword(true);
				}

				if ($n->getAttribute('is_disabled') == 'true') {
					$user->setDisabled(true);
				}

				if ($n->getAttribute('encryption')) {
					$encryption = $n->getAttribute('encryption');
				}

				$passwordValueNodeList = $n->getElementsByTagNameNS($deployment->getNamespace(), 'value');
				if ($passwordValueNodeList->length == 1) {
					$password = $passwordValueNodeList->item(0);
					$user->setPassword($password->textContent);
				} else {
					$this->addError(__('plugins.importexport.user.error.userHasNoPassword', array('username' => $user->getUsername())));
				}

				break;
		}

		// Password Import Validation
		$password = $this->importUserPasswordValidation($user, $encryption);

		$userByUsername = $userDao->getByUsername($user->getUsername(), true);
		$userByEmail = $userDao->getUserByEmail($user->getEmail(), true);
		// username and email are both required and unique, so either
		// both exist for one and the same user, or both do not exist
		if ($userByUsername && $userByEmail && $userByUsername->getId() == $userByEmail->getId()) {
			$user = $userByUsername;
			$userId = $user->getId();
		} elseif (!$userByUsername && !$userByEmail) {
			// if user names do not exists in the site primary locale
			// copy one of the existing for the default/required site primary locale
			if (empty($user->getGivenName($site->getPrimaryLocale()))) {
				// get all user given names, family names and affiliations
				$userGivenNames = $user->getGivenName(null);
				$userFamilyNames = $user->getFamilyName(null);
				$userAffiliations = $user->getAffiliation(null);
				// get just not empty user given names, family names and affiliations
				$notEmptyGivenNames =  $notEmptyFamilyNames = $notEmptyAffiliations = array();
				$notEmptyGivenNames = array_filter($userGivenNames, function($a) {
					return !empty($a);
				});
				// if all given names are empty, import fails
				if (empty($notEmptyGivenNames)) {
					fatalError("User given name is empty.");
				}
				if (!empty($userFamilyNames)) {
					$notEmptyFamilyNames = array_filter($userFamilyNames, function($a) {
						return !empty($a);
					});
				}
				if (!empty($userAffiliations)) {
					$notEmptyAffiliations = array_filter($userAffiliations, function($a) {
						return !empty($a);
					});
				}
				// see if both, given and family name, exist in the same locale
				$commonLocales = array_intersect_key($notEmptyGivenNames, $notEmptyFamilyNames);
				if (empty($commonLocales)) {
					// if not: copy only the given name
					$firstLocale = reset(array_keys($notEmptyGivenNames));
					$user->setGivenName($notEmptyGivenNames[$firstLocale], $site->getPrimaryLocale());
				} else {
					// else: take the first common locale for given and family name
					$firstLocale = reset(array_keys($commonLocales));
					// see if there is affiliation in a common locale
					$affiliationCommonLocales = array_intersect_key($notEmptyAffiliations, $commonLocales);
					if (!empty($affiliationCommonLocales)) {
						// take the first common locale to all, given name, family name and affiliation
						$firstLocale = reset(array_keys($affiliationCommonLocales));
						// copy affiliation
						if (empty($notEmptyAffiliations[$site->getPrimaryLocale()])) {
							$user->setAffiliation($notEmptyAffiliations[$firstLocale], $site->getPrimaryLocale());
						}
					}
					//copy given and family name
					$user->setGivenName($notEmptyGivenNames[$firstLocale], $site->getPrimaryLocale());
					if (empty($notEmptyFamilyNames[$site->getPrimaryLocale()])) {
						$user->setFamilyName($notEmptyFamilyNames[$firstLocale], $site->getPrimaryLocale());
					}
				}
			}
			$userId = $userDao->insertObject($user);

			// Insert reviewing interests, now that there is a userId.
			$interestNodeList = $node->getElementsByTagNameNS($deployment->getNamespace(), 'review_interests');
			if ($interestNodeList->length == 1) {
				$n = $interestNodeList->item(0);
				if ($n) {
					$interests = preg_split('/,\s*/', $n->textContent);
					import('lib.pkp.classes.user.InterestManager');
					$interestManager = new InterestManager();
					$interestManager->setInterestsForUser($user, $interests);
				}
			}

			// send USER_REGISTER e-mail only if it is a new inserted/registered user
			// else, if the user already exists, its metadata will not be change (just groups will be re-assigned below)
			if ($password) {
				import('lib.pkp.classes.mail.MailTemplate');
				$mail = new MailTemplate('USER_REGISTER');
				$mail->setReplyTo($context->getSetting('contactEmail'), $context->getSetting('contactName'));
				$mail->assignParams(array('username' => $user->getUsername(), 'password' => $password, 'userFullName' => $user->getFullName()));
				$mail->addRecipient($user->getEmail(), $user->getFullName());
				$mail->send();
			}
		} else {
			// the username and the email do not match to the one and the same existing user
			$this->addError(__('plugins.importexport.user.error.usernameEmailMismatch', array('username' => $user->getUsername(), 'email' => $user->getEmail())));
		}

		// We can only assign a user to a user group if persisted to the database by $userId
		if ($userId) {
	  		$userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /* @var $userGroupDao UserGroupDAO */
  			$userGroupsFactory = $userGroupDao->getByContextId($context->getId());
  			$userGroups = $userGroupsFactory->toArray();

	  		// Extract user groups from the User XML and assign the user to those (existing) groups.
  			// Note:  It is possible for a user to exist with no user group assignments so there is
  			// no fatalError() as is the case with PKPAuthor import.
	  		$userGroupNodeList = $node->getElementsByTagNameNS($deployment->getNamespace(), 'user_group_ref');
  			if ($userGroupNodeList->length > 0) {
  				for ($i = 0 ; $i < $userGroupNodeList->length ; $i++) {
  					$n = $userGroupNodeList->item($i);
  					foreach ($userGroups as $userGroup) {
  						if (in_array($n->textContent, $userGroup->getName(null))) {
  							// Found a candidate; assign user to it.
	  						$userGroupDao->assignUserToGroup($userId, $userGroup->getId());
  						}
  					}
  				}
	  		}
		}

		return $user;
	}

	/**
	 * Handle a singular element import.
	 * @param $node DOMElement
	 */
	function handleElement($node) {
		$deployment = $this->getDeployment();
		$context = $deployment->getContext();

		for ($n = $node->firstChild; $n !== null; $n=$n->nextSibling) {
			if (is_a($n, 'DOMElement')) {
				$this->handleChildElement($n);
			}
		}

	}

	/**
	 * Handle an element whose parent is the submission element.
	 * @param $n DOMElement
	 */
	function handleChildElement($n) {
		switch ($n->tagName) {
			case 'user_group':
				$this->parseUserGroup($n);
				break;
			case 'user':
				$this->parseUser($n);
				break;
			default:
				fatalError('Unknown element ' . $n->tagName);
		}
	}

	/**
	 * Validation process for imported passwords
	 * @param $userToImport User ByRef. The user that is being imported.
	 * @param $encryption string null, sha1, md5 (or any other encryption algorithm defined)
	 * @return string if a new password is generated, the function returns it.
	 */
	function importUserPasswordValidation($userToImport, $encryption) {
		$passwordHash = $userToImport->getPassword();
		$password = null;
		if (!$encryption) {
			$siteDao = DAORegistry::getDAO('SiteDAO'); /* @var $siteDao SiteDAO */
			$site = $siteDao->getSite();
			if (strlen($passwordHash) >= $site->getMinPasswordLength()) {
				$userToImport->setPassword(Validation::encryptCredentials($userToImport->getUsername(), $passwordHash));
			} else {
				$this->addError(__('plugins.importexport.user.error.plainPasswordNotValid', array('username' => $userToImport->getUsername())));
			}
		} else {
			if (password_needs_rehash($passwordHash, PASSWORD_BCRYPT)) {

				$password = Validation::generatePassword();
				$userToImport->setPassword(Validation::encryptCredentials($userToImport->getUsername(), $password));

				$userToImport->setMustChangePassword(true);

				$this->addError(__('plugins.importexport.user.error.passwordHasBeenChanged', array('username' => $userToImport->getUsername())));
			} else {
				$userToImport->setPassword($passwordHash);
			}
		}

		return $password;
	}
}


