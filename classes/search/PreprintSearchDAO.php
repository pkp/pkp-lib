<?php

/**
 * @file classes/search/PreprintSearchDAO.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PreprintSearchDAO
 *
 * @ingroup search
 *
 * @see PreprintSearch
 *
 * @brief DAO class for preprint search index.
 */

namespace APP\search;

use PKP\search\SubmissionSearchDAO;
use PKP\submission\PKPSubmission;

class PreprintSearchDAO extends SubmissionSearchDAO
{
    /**
     * Retrieve the top results for a phrases with the given
     * limit (default 500 results).
     *
     * @param null|mixed $publishedFrom
     * @param null|mixed $publishedTo
     * @param null|mixed $type
     *
     * @return array of results (associative arrays)
     */
    public function getPhraseResults($server, $phrase, $publishedFrom = null, $publishedTo = null, $type = null, $limit = 500, $cacheHours = 24)
    {
        if (empty($phrase)) {
            return [];
        }

        $sqlFrom = '';
        $sqlWhere = '';
        $params = [];

        for ($i = 0, $count = count($phrase); $i < $count; $i++) {
            if (!empty($sqlFrom)) {
                $sqlFrom .= ', ';
                $sqlWhere .= ' AND ';
            }
            $sqlFrom .= 'submission_search_object_keywords o' . $i . ' NATURAL JOIN submission_search_keyword_list k' . $i;
            if (strstr($phrase[$i], '%') === false) {
                $sqlWhere .= 'k' . $i . '.keyword_text = ?';
            } else {
                $sqlWhere .= 'k' . $i . '.keyword_text LIKE ?';
            }
            if ($i > 0) {
                $sqlWhere .= ' AND o0.object_id = o' . $i . '.object_id AND o0.pos+' . $i . ' = o' . $i . '.pos';
            }

            $params[] = $phrase[$i];
        }

        if (!empty($type)) {
            $sqlWhere .= ' AND (o.type & ?) != 0';
            $params[] = $type;
        }

        if (!empty($publishedFrom)) {
            $sqlWhere .= ' AND p.date_published >= ' . $this->datetimeToDB($publishedFrom);
        }

        if (!empty($publishedTo)) {
            $sqlWhere .= ' AND p.date_published <= ' . $this->datetimeToDB($publishedTo);
        }

        if (!empty($server)) {
            $sqlWhere .= ' AND s.context_id = ?';
            $params[] = $server->getId();
        }

        $result = $this->retrieve(
            'SELECT
				o.submission_id,
				MAX(s.context_id) AS server_id,
				MAX(p.date_published) AS s_pub,
				COUNT(*) AS count
			FROM
				submissions s
				JOIN publications p ON (p.publication_id = s.current_publication_id),
				submission_search_objects o NATURAL JOIN ' . $sqlFrom . '
			WHERE
				s.submission_id = o.submission_id AND
				s.status = ' . PKPSubmission::STATUS_PUBLISHED . ' AND
				' . $sqlWhere . '
			GROUP BY o.submission_id
			ORDER BY count DESC
			LIMIT ' . $limit,
            $params,
            3600 * $cacheHours // Cache for 24 hours
        );

        $returner = [];
        foreach ($result as $row) {
            $returner[$row->submission_id] = [
                'count' => $row->count,
                'server_id' => $row->server_id,
                'publicationDate' => $this->datetimeFromDB($row->s_pub)
            ];
        }
        return $returner;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\APP\search\PreprintSearchDAO', '\PreprintSearchDAO');
}
