<?php

/**
 * ISubmissionVersionedDAO short summary.
 *
 * ISubmissionVersionedDAO description.
 *
 * @version 1.0
 * @author defstat
 */
interface ISubmissionVersionedDAO {

	function newVersion($submissionId);
	function getMasterTableName();
}
