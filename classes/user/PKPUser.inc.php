<?php
/**
 * @defgroup user User
 * Implements data objects and DAOs concerned with managing user accounts.
 */

/**
 * @file classes/user/PKPUser.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPUser
 * @ingroup user
 * @see UserDAO
 *
 * @brief Basic class describing users existing in the system.
 */

import('lib.pkp.classes.identity.Identity');

class PKPUser extends Identity {
	/**
	 * Constructor
	 */
	function PKPUser() {
		parent::Identity();
	}

	//
	// Get/set methods
	//

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
