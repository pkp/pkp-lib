<?php

/**
 * @file classes/payment/QueuedPaymentDAO.inc.php
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class QueuedPaymentDAO
 * @ingroup payment
 * @see QueuedPayment
 *
 * @brief Operations for retrieving and modifying queued payment objects.
 *
 */


class QueuedPaymentDAO extends DAO {
	/**
	 * Constructor.
	 */
	function QueuedPaymentDAO() {
		parent::DAO();
	}

	/**
	 * Retrieve a queued payment by ID.
	 * @param $queuedPaymentId int
	 * @return QueuedPayment or null on failure
	 */
	function &getQueuedPayment($queuedPaymentId) {
		$result =& $this->retrieve(
			'SELECT * FROM queued_payments WHERE queued_payment_id = ?',
			(int) $queuedPaymentId
		);

		$queuedPayment = null;
		if ($result->RecordCount() != 0) {
			$queuedPayment = unserialize($result->fields['payment_data']);
			if (!is_object($queuedPayment)) $queuedPayment = null;
		}
		$result->Close();
		unset($result);
		return $queuedPayment;
	}

	/**
	 * Insert a new queued payment.
	 * @param $queuedPayment QueuedPayment
	 * @param $expiryDate date optional
	 */
	function insertQueuedPayment(&$queuedPayment, $expiryDate = null) {
		$this->update(
			sprintf('INSERT INTO queued_payments
				(date_created, date_modified, expiry_date, payment_data)
				VALUES
				(%s, %s, %s, ?)',
				$this->datetimeToDB(Core::getCurrentDate()),
				$this->datetimeToDB(Core::getCurrentDate()),
				$this->datetimeToDB($expiryDate)),
			array(
				serialize($queuedPayment)
			)
		);

		return $queuedPayment->setId($this->getInsertQueuedPaymentId());
	}

	/**
	 * Update an existing queued payment.
	 * @param $queuedPaymentId int
	 * @param $queuedPayment QueuedPayment
	 */
	function updateQueuedPayment($queuedPaymentId, &$queuedPayment) {
		return $this->update(
			sprintf('UPDATE queued_payments
				SET
					date_modified = %s,
					payment_data = ?
				WHERE queued_payment_id = ?',
				$this->datetimeToDB(Core::getCurrentDate())),
			array(
				serialize($queuedPayment),
				(int) $queuedPaymentId
			)
		);
	}

	/**
	 * Get the ID of the last inserted queued payment.
	 * @return int
	 */
	function getInsertQueuedPaymentId() {
		return $this->getInsertId('queued_payments', 'queued_payment_id');
	}

	/**
	 * Delete a queued payment.
	 * @param $queuedPaymentId int
	 */
	function deleteQueuedPayment($queuedPaymentId) {
		return $this->update(
			'DELETE FROM queued_payments WHERE queued_payment_id = ?',
			array((int) $queuedPaymentId)
		);
	}

	/**
	 * Delete expired queued payments.
	 */
	function deleteExpiredQueuedPayments() {
		return $this->update(
			'DELETE FROM queued_payments WHERE expiry_date < now()'
		);
	}
}

?>
