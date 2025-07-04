<?php

/**
 * @file classes/payment/QueuedPaymentDAO.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class QueuedPaymentDAO
 *
 * @ingroup payment
 *
 * @see QueuedPayment
 *
 * @brief Operations for retrieving and modifying queued payment objects.
 *
 */

namespace PKP\payment;

use APP\core\Application;
use Illuminate\Support\Facades\DB;
use PKP\core\Core;
use PKP\notification\Notification;

class QueuedPaymentDAO extends \PKP\db\DAO
{
    /**
     * Retrieve a queued payment by ID.
     *
     * @param int $queuedPaymentId
     *
     * @return QueuedPayment|null on failure
     */
    public function getById($queuedPaymentId)
    {
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
     *
     * @param QueuedPayment $queuedPayment
     * @param string $expiryDate optional
     */
    public function insertObject($queuedPayment, $expiryDate = null)
    {
        $this->update(
            sprintf(
                'INSERT INTO queued_payments
				(date_created, date_modified, expiry_date, payment_data)
				VALUES
				(%s, %s, %s, ?)',
                $this->datetimeToDB(Core::getCurrentDate()),
                $this->datetimeToDB(Core::getCurrentDate()),
                $this->datetimeToDB($expiryDate)
            ),
            [
                serialize($queuedPayment)
            ]
        );

        return $queuedPayment->setId($this->getInsertId());
    }

    /**
     * Update an existing queued payment.
     *
     * @param int $queuedPaymentId
     * @param QueuedPayment $queuedPayment
     */
    public function updateObject($queuedPaymentId, $queuedPayment)
    {
        return $this->update(
            sprintf(
                'UPDATE queued_payments
				SET
					date_modified = %s,
					payment_data = ?
				WHERE queued_payment_id = ?',
                $this->datetimeToDB(Core::getCurrentDate())
            ),
            [
                serialize($queuedPayment),
                (int) $queuedPaymentId
            ]
        );
    }

    /**
     * Delete a queued payment.
     */
    public function deleteById(int $queuedPaymentId): int
    {
        Notification::withAssoc(Application::ASSOC_TYPE_QUEUED_PAYMENT, $queuedPaymentId)
            ->delete();
        return DB::table('queued_payments')
            ->where('queued_payment_id', '=', $queuedPaymentId)
            ->delete();
    }

    /**
     * Delete expired queued payments.
     */
    public function deleteExpired()
    {
        $this->update('DELETE FROM queued_payments WHERE expiry_date < now()');
    }
}
