<?php

/**
 * @defgroup user
 */

/**
 * @file classes/user/PKPUser.inc.php
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPUser
 * @ingroup user
 * @see UserDAO
 *
 * @brief Basic class describing users existing in the system.
 */

class PKPUser extends DataObject {
	/**
	 * Constructor
	 */
	function PKPUser() {
		parent::DataObject();
	}

	//
	// Get/set methods
	//

	/**
	 * Get the ID of the user. DEPRECATED in favour of getId.
	 * @return int
	 */
	function getUserId() {
		if (Config::getVar('debug', 'deprecation_warnings')) trigger_error('Deprecated function.');
		return $this->getId();
	}

	/**
	 * Set the ID of the user. DEPRECATED in favour of setId.
	 * @param $userId int
	 */
	function setUserId($userId) {
		if (Config::getVar('debug', 'deprecation_warnings')) trigger_error('Deprecated function.');
		return $this->setId($userId);
	}

	/**
	 * Get username.
	 * @return string
	 */
	function getUsername() {
		return $this->getData('username');
	}

	/**
	 * Set username.
	 * @param $username string
	 */
	function setUsername($username) {
		return $this->setData('username', $username);
	}

	/**
	 * Get implicit auth ID string.
	 * @return String
	 */
	function getAuthStr() {
		return $this->getData('authStr');
	}

	/**
	 * Set Shib ID string for this user.
	 * @param $authStr string
	 */
	function setAuthStr($authStr) {
		return $this->setData('authStr', $authStr);
	}

	/**
	 * Get localized user signature.
	 */
	function getLocalizedSignature() {
		return $this->getLocalizedData('signature');
	}

	function getUserSignature() {
		if (Config::getVar('debug', 'deprecation_warnings')) trigger_error('Deprecated function.');
		return $this->getLocalizedSignature();
	}

	/**
	 * Get email signature.
	 * @param $locale string
	 * @return string
	 */
	function getSignature($locale) {
		return $this->getData('signature', $locale);
	}

	/**
	 * Set signature.
	 * @param $signature string
	 * @param $locale string
	 */
	function setSignature($signature, $locale) {
		return $this->setData('signature', $signature, $locale);
	}

	/**
	 * Get password (encrypted).
	 * @return string
	 */
	function getPassword() {
		return $this->getData('password');
	}

	/**
	 * Set password (assumed to be already encrypted).
	 * @param $password string
	 */
	function setPassword($password) {
		return $this->setData('password', $password);
	}

	/**
	 * Get first name.
	 * @return string
	 */
	function getFirstName() {
		return $this->getData('firstName');
	}

	/**
	 * Set first name.
	 * @param $firstName string
	 */
	function setFirstName($firstName)
	{
		return $this->setData('firstName', $firstName);
	}

	/**
	 * Get middle name.
	 * @return string
	 */
	function getMiddleName() {
		return $this->getData('middleName');
	}

	/**
	 * Set middle name.
	 * @param $middleName string
	 */
	function setMiddleName($middleName) {
		return $this->setData('middleName', $middleName);
	}

	/**
	 * Get initials.
	 * @return string
	 */
	function getInitials() {
		return $this->getData('initials');
	}

	/**
	 * Set initials.
	 * @param $initials string
	 */
	function setInitials($initials) {
		return $this->setData('initials', $initials);
	}

	/**
	 * Get last name.
	 * @return string
	 */
	function getLastName() {
		return $this->getData('lastName');
	}

	/**
	 * Set last name.
	 * @param $lastName string
	 */
	function setLastName($lastName) {
		return $this->setData('lastName', $lastName);
	}

	/**
	 * Get name suffix.
	 * @return string
	 */
	function getSuffix() {
		return $this->getData('suffix');
	}

	/**
	 * Set suffix.
	 * @param $suffix string
	 */
	function setSuffix($suffix) {
		return $this->setData('suffix', $suffix);
	}

	/**
	 * Get user salutation.
	 * @return string
	 */
	function getSalutation() {
		return $this->getData('salutation');
	}

	/**
	 * Set user salutation.
	 * @param $salutation string
	 */
	function setSalutation($salutation) {
		return $this->setData('salutation', $salutation);
	}

	/**
	 * Get user gender.
	 * @return string
	 */
	function getGender() {
		return $this->getData('gender');
	}

	/**
	 * Set user gender.
	 * @param $gender string
	 */
	function setGender($gender) {
		return $this->setData('gender', $gender);
	}

	/**
	 * Get affiliation (position, institution, etc.).
	 * @param $locale string
	 * @return string
	 */
	function getAffiliation($locale) {
		return $this->getData('affiliation', $locale);
	}

	/**
	 * Set affiliation.
	 * @param $affiliation string
	 * @param $locale string
	 */
	function setAffiliation($affiliation, $locale) {
		return $this->setData('affiliation', $affiliation, $locale);
	}

	/**
	 * Get localized user affiliation.
	 */
	function getLocalizedAffiliation() {
		return $this->getLocalizedData('affiliation');
	}

	/**
	 * Get email address.
	 * @return string
	 */
	function getEmail() {
		return $this->getData('email');
	}

	/**
	 * Set email address.
	 * @param $email string
	 */
	function setEmail($email) {
		return $this->setData('email', $email);
	}

	/**
	 * Get URL.
	 * @return string
	 */
	function getUrl() {
		return $this->getData('url');
	}

	/**
	 * Set URL.
	 * @param $url string
	 */
	function setUrl($url) {
		return $this->setData('url', $url);
	}

	/**
	 * Get phone number.
	 * @return string
	 */
	function getPhone() {
		return $this->getData('phone');
	}

	/**
	 * Set phone number.
	 * @param $phone string
	 */
	function setPhone($phone) {
		return $this->setData('phone', $phone);
	}

	/**
	 * Get fax number.
	 * @return string
	 */
	function getFax() {
		return $this->getData('fax');
	}

	/**
	 * Set fax number.
	 * @param $fax string
	 */
	function setFax($fax) {
		return $this->setData('fax', $fax);
	}

	/**
	 * Get mailing address.
	 * @return string
	 */
	function getMailingAddress() {
		return $this->getData('mailingAddress');
	}

	/**
	 * Set mailing address.
	 * @param $mailingAddress string
	 */
	function setMailingAddress($mailingAddress) {
		return $this->setData('mailingAddress', $mailingAddress);
	}

	/**
	 * Get billing address.
	 * @return string
	 */
	function getBillingAddress() {
		return $this->getData('billingAddress');
	}

	/**
	 * Set billing address.
	 * @param $billingAddress string
	 */
	function setBillingAddress($billingAddress) {
		return $this->setData('billingAddress', $billingAddress);
	}

	/**
	 * Get country.
	 * @return string
	 */
	function getCountry() {
		return $this->getData('country');
	}

	/**
	 * Set country.
	 * @param $country string
	 */
	function setCountry($country) {
		return $this->setData('country', $country);
	}

	/**
	 * Get localized user biography.
	 */
	function getLocalizedBiography() {
		return $this->getLocalizedData('biography');
	}

	function getUserBiography() {
		if (Config::getVar('debug', 'deprecation_warnings')) trigger_error('Deprecated function.');
		return $this->getLocalizedBiography();
	}

	/**
	 * Get user biography.
	 * @param $locale string
	 * @return string
	 */
	function getBiography($locale) {
		return $this->getData('biography', $locale);
	}

	/**
	 * Set user biography.
	 * @param $biography string
	 * @param $locale string
	 */
	function setBiography($biography, $locale) {
		return $this->setData('biography', $biography, $locale);
	}

	/**
	 * Get the user's reviewing interests as an array. DEPRECATED in favour of direct interaction with the InterestManager.
	 * @return array
	 */
	function getUserInterests() {
		if (Config::getVar('debug', 'deprecation_warnings')) trigger_error('Deprecated function.');
		import('lib.pkp.classes.user.InterestManager');
		$interestManager = new InterestManager();
		return $interestManager->getInterestsForUser($this);
	}

	/**
	 * Get the user's interests displayed as a comma-separated string
	 * @return string
	 */
	function getInterestString() {
		import('lib.pkp.classes.user.InterestManager');
		$interestManager = new InterestManager();
		return $interestManager->getInterestsString($this);
	}

	/**
	 * Get localized user gossip.
	 */
	function getLocalizedGossip() {
		return $this->getLocalizedData('gossip');
	}

	/**
	 * Get user gossip.
	 * @param $locale string
	 * @return string
	 */
	function getGossip($locale) {
		return $this->getData('gossip', $locale);
	}

	/**
	 * Set user gossip.
	 * @param $gossip string
	 * @param $locale string
	 */
	function setGossip($gossip, $locale) {
		return $this->setData('gossip', $gossip, $locale);
	}

	/**
	 * Get user's working languages.
	 * @return array
	 */
	function getLocales() {
		$locales = $this->getData('locales');
		return isset($locales) ? $locales : array();
	}

	/**
	 * Set user's working languages.
	 * @param $locales array
	 */
	function setLocales($locales) {
		return $this->setData('locales', $locales);
	}

	/**
	 * Get date user last sent an email.
	 * @return datestamp (YYYY-MM-DD HH:MM:SS)
	 */
	function getDateLastEmail() {
		return $this->getData('dateLastEmail');
	}

	/**
	 * Set date user last sent an email.
	 * @param $dateLastEmail datestamp (YYYY-MM-DD HH:MM:SS)
	 */
	function setDateLastEmail($dateLastEmail) {
		return $this->setData('dateLastEmail', $dateLastEmail);
	}

	/**
	 * Get date user registered with the site.
	 * @return datestamp (YYYY-MM-DD HH:MM:SS)
	 */
	function getDateRegistered() {
		return $this->getData('dateRegistered');
	}

	/**
	 * Set date user registered with the site.
	 * @param $dateRegistered datestamp (YYYY-MM-DD HH:MM:SS)
	 */
	function setDateRegistered($dateRegistered) {
		return $this->setData('dateRegistered', $dateRegistered);
	}

	/**
	 * Get date user email was validated with the site.
	 * @return datestamp (YYYY-MM-DD HH:MM:SS)
	 */
	function getDateValidated() {
		return $this->getData('dateValidated');
	}

	/**
	 * Set date user email was validated with the site.
	 * @param $dateValidated datestamp (YYYY-MM-DD HH:MM:SS)
	 */
	function setDateValidated($dateValidated) {
		return $this->setData('dateValidated', $dateValidated);
	}

	/**
	 * Get date user last logged in to the site.
	 * @return datestamp
	 */
	function getDateLastLogin() {
		return $this->getData('dateLastLogin');
	}

	/**
	 * Set date user last logged in to the site.
	 * @param $dateLastLogin datestamp
	 */
	function setDateLastLogin($dateLastLogin) {
		return $this->setData('dateLastLogin', $dateLastLogin);
	}

	/**
	 * Check if user must change their password on their next login.
	 * @return boolean
	 */
	function getMustChangePassword() {
		return $this->getData('mustChangePassword');
	}

	/**
	 * Set whether or not user must change their password on their next login.
	 * @param $mustChangePassword boolean
	 */
	function setMustChangePassword($mustChangePassword) {
		return $this->setData('mustChangePassword', $mustChangePassword);
	}

	/**
	 * Check if user is disabled.
	 * @return boolean
	 */
	function getDisabled() {
		return $this->getData('disabled');
	}

	/**
	 * Set whether or not user is disabled.
	 * @param $disabled boolean
	 */
	function setDisabled($disabled) {
		return $this->setData('disabled', $disabled);
	}

	/**
	 * Get the reason the user was disabled.
	 * @return string
	 */
	function getDisabledReason() {
		return $this->getData('disabled_reason');
	}

	/**
	 * Set the reason the user is disabled.
	 * @param $reasonDisabled string
	 */
	function setDisabledReason($reasonDisabled) {
		return $this->setData('disabled_reason', $reasonDisabled);
	}

	/**
	 * Get ID of authentication source for this user.
	 * @return int
	 */
	function getAuthId() {
		return $this->getData('authId');
	}

	/**
	 * Set ID of authentication source for this user.
	 * @param $authId int
	 */
	function setAuthId($authId) {
		return $this->setData('authId', $authId);
	}

	/**
	 * Get the inline help display status for this user.
	 * @return int
	 */
	function getInlineHelp() {
		return $this->getData('inlineHelp');
	}

	/**
	 * Set the inline help display status for this user.
	 * @param $inlineHelp int
	 */
	function setInlineHelp($inlineHelp) {
		return $this->setData('inlineHelp', $inlineHelp);
	}

	/**
	 * Get the user's complete name.
	 * Includes first name, middle name (if applicable), and last name.
	 * The suffix is only included when the name is not reversed with $lastFirst
	 * @param $lastFirst boolean return in "LastName, FirstName" format
	 * @return string
	 */
	function getFullName($lastFirst = false) {
		$salutation = $this->getData('salutation');
		$firstName = $this->getData('firstName');
		$middleName = $this->getData('middleName');
		$lastName = $this->getData('lastName');
		$suffix = $this->getData('suffix');
		if ($lastFirst) {
			return "$lastName, " . ($salutation != ''?"$salutation ":'') . "$firstName" . ($middleName != ''?" $middleName":'');
		} else {
			return ($salutation != ''?"$salutation ":'') . "$firstName " . ($middleName != ''?"$middleName ":'') . $lastName . ($suffix != ''?", $suffix":'');
		}
	}

	function getContactSignature() {
		$signature = $this->getFullName();
		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_USER);
		if ($a = $this->getLocalizedAffiliation()) $signature .= "\n" . $a;
		if ($p = $this->getPhone()) $signature .= "\n" . __('user.phone') . ' ' . $p;
		if ($f = $this->getFax()) $signature .= "\n" . __('user.fax') . ' ' . $f;
		$signature .= "\n" . $this->getEmail();
		return $signature;
	}
}

?>
