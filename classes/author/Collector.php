<?php
/**
 * @file classes/author/Collector.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class author
 *
 * @brief A helper class to configure a Query Builder to get a collection of announcements
 */

namespace PKP\author;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use PKP\core\interfaces\CollectorInterface;
use PKP\plugins\HookRegistry;

class Collector implements CollectorInterface
{
    public const ORDERBY_SEQUENCE = 'sequence';
    public const ORDERBY_ID = 'id';

    /** @var string The default orderBy value for authors collector */
    public $orderBy = self::ORDERBY_SEQUENCE;

    /** @var DAO */
    public $dao;

    /** @var array|null */
    public $contextIds = null;

    /** @var array|null */
    public $publicationIds = null;

    /** Get authors with a family name */
    protected ?string $familyName = null;

    /** Get authors with a given name */
    protected ?string $givenName = null;

    /** Get authors with a specified country code */
    protected ?string $country = null;

    /** Get authors with a specified affiliation */
    protected ?string $affiliation = null;

    public ?int $count = null;

    public ?int $offset = null;

    public ?bool $includeInBrowse = null;

    public function __construct(DAO $dao)
    {
        $this->dao = $dao;
    }

    /**
     * Filter by contexts
     */
    public function filterByContextIds(?array $contextIds): self
    {
        $this->contextIds = $contextIds;
        return $this;
    }

    /**
     * Filter by publications
     */
    public function filterByPublicationIds(?array $publicationIds): self
    {
        $this->publicationIds = $publicationIds;
        return $this;
    }

    /**
     * Filter by include in browse
     */
    public function filterByIncludeInBrowse(?bool $includeInBrowse): self
    {
        $this->includeInBrowse = $includeInBrowse;
        return $this;
    }

    /**
     * Include orderBy columns to the collector query
     */
    public function orderBy(?string $orderBy): self
    {
        $this->orderBy = $orderBy;
        return $this;
    }

    /**
     * Filter by the given and family name
     *
     *
     */
    public function filterByName(?string $givenName, ?string $familyName): self
    {
        $this->givenName = $givenName;
        $this->familyName = $familyName;
        return $this;
    }

    /**
     * Filter by the specified country code
     *
     * @param string $country Country code (2-letter)
     *
     * */
    public function filterByCountry(?string $country): self
    {
        $this->country = $country;
        return $this;
    }

    /**
     * Filter by the specified affiliation code
     *
     * */
    public function filterByAffiliation(?string $affiliation): self
    {
        $this->affiliation = $affiliation;
        return $this;
    }

    /**
     * Limit the number of objects retrieved
     */
    public function limit(?int $count): self
    {
        $this->count = $count;
        return $this;
    }

    /**
     * Offset the number of objects retrieved, for example to
     * retrieve the second page of contents
     */
    public function offset(?int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    /**
     * @copydoc CollectorInterface::getQueryBuilder()
     */
    public function getQueryBuilder(): Builder
    {
        $q = DB::table('authors as a')
            ->join('publications as p', 'a.publication_id', '=', 'p.publication_id')
            ->join('submissions as s', 'p.submission_id', '=', 's.submission_id');

        if (isset($this->contextIds)) {
            $q->whereIn('s.context_id', $this->contextIds);
        }

        $q->when($this->familyName !== null, function (Builder $q) {
            $q->whereIn('a.author_id', function (Builder $q) {
                $q->select('author_id')
                    ->from($this->dao->settingsTable)
                    ->where('setting_name', '=', 'familyName')
                    ->where('setting_value', $this->familyName);
            });
        });

        $q->when($this->givenName !== null, function (Builder $q) {
            $q->whereIn('a.author_id', function (Builder $q) {
                $q->select('author_id')
                    ->from($this->dao->settingsTable)
                    ->where('setting_name', '=', 'givenName')
                    ->where('setting_value', $this->givenName);
            });
        });

        if (isset($this->publicationIds)) {
            $q->whereIn('a.publication_id', $this->publicationIds);
        }

        $q->when($this->country !== null, function (Builder $q) {
            $q->whereIn('a.author_id', function (Builder $q) {
                $q->select('author_id')
                    ->from($this->dao->settingsTable)
                    ->where('setting_name', '=', 'country')
                    ->where('setting_value', $this->country);
            });
        });

        $q->when($this->affiliation !== null, function (Builder $q) {
            $q->whereIn('a.author_id', function (Builder $q) {
                $q->select('author_id')
                    ->from($this->dao->settingsTable)
                    ->where('setting_name', '=', 'affiliation')
                    ->where('setting_value', $this->affiliation);
            });
        });

        if ($this->includeInBrowse) {
            $q->where('a.include_in_browse', $this->includeInBrowse);
        }

        if (isset($this->count)) {
            $q->limit($this->count);
        }

        if (isset($this->offset)) {
            $q->offset($this->offset);
        }

        switch ($this->orderBy) {
            case self::ORDERBY_SEQUENCE:
                $q->orderBy('a.seq', 'asc');
                break;
            case self::ORDERBY_ID:
            default:
                $q->orderBy('a.author_id', 'asc');
                break;
        }

        // Add app-specific query statements
        HookRegistry::call('Author::Collector', [&$q, $this]);



        return $q;
    }
}
