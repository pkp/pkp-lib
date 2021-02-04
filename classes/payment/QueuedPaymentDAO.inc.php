<?php

/**
 * @file classes/payment/QueuedPaymentDAO.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class QueuedPaymentDAO
 * @ingroup payment
 * @see QueuedPayment
 *
 * @brief Operations for retrieving and modifying queued payment objects.
 *
 */

import('lib.pkp.classes.payment.QueuedPayment');

class QueuedPaymentDAO extends DAO {

	/**
	 * Retrieve a queued payment by ID.
	 * @param $queuedPaymentId int
	 * @return QueuedPayment or null on failure
	 */
	function getById($queuedPaymentId) {
		$result = $this->retrieve(
			'SELECT * FROM queued_payments WHERE queued_payment_id = ?',
			[(int) $queuedPaymentId]
		);
		if ($row = $result->current()) {
			$queuedPayment = unserialize($row->payment_data);
			$queuedPayment->setId($row->queued_payment_id);
			return $queuedPayment;
		}
		return null;
	}

	/**
	 * Insert a new queued payment.
	 * @param $queuedPayment QueuedPayment
	 * @param $expiryDate date optional
	 */
	function insertObject($queuedPayment, $expiryDate = null) {
		$this->update(
			sprintf('INSERT INTO queued_payments
				(date_created, date_modified, expiry_date, payment_data)
				VALUES
				(%s, %s, %s, ?)',
				$this->datetimeToDB(Core::getCurrentDate()),
				$this->datetimeToDB(Core::getCurrentDate()),
				$this->datetimeToDB($expiryDate)),
			[
				serialize($queuedPayment)
			]
		);

		return $queuedPayment->setId($this->getInsertId());
	}

	/**
	 * Update an existing queued payment.
	 * @param $queuedPaymentId int
	 * @param $queuedPayment QueuedPayment
	 */
	function updateObject($queuedPaymentId, $queuedPayment) {
		return $this->update(
			sprintf('UPDATE queued_payments
				SET
					date_modified = %s,
					payment_data = ?
				WHERE queued_payment_id = ?',
				$this->datetimeToDB(Core::getCurrentDate())),
			[
				serialize($queuedPayment),
				(int) $queuedPaymentId
			]
		);
	}

	/**
	 * Get the ID of the last inserted queued payment.
	 * @return int
	 */
	function getInsertId() {
		return $this->_getInsertId('queued_payments', 'queued_payment_id');
	}

	/**
	 * Delete a queued payment.
	 * @param $queuedPaymentId int
	 */
	function deleteById($queuedPaymentId) {
		$notificationDao = DAORegistry::getDAO('NotificationDAO'); /* @var $notificationDao NotificationDAO */
		$notificationDao->deleteByAssoc(ASSOC_TYPE_QUEUED_PAYMENT, $queuedPaymentId);
		$this->update(
			'DELETE FROM queued_payments WHERE queued_payment_id = ?',
			[(int) $queuedPaymentId]
		);
	}

	/**
	 * Delete expired queued payments.
	 */
	function deleteExpired() {
		$this->update('DELETE FROM queued_payments WHERE expiry_date < now()');
	}
}


