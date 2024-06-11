<?php
/**
 * @file classes/institution/Collector.php
 *
 * Copyright (c) 2022 Simon Fraser University
 * Copyright (c) 2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Collector
 *
 * @brief A helper class to configure a Query Builder to get a collection of institutions
 */

namespace PKP\institution;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use PKP\core\interfaces\CollectorInterface;

/**
 * @template T of Institution
 */
class Collector implements CollectorInterface
{
    public DAO $dao;
    public ?array $contextIds = null;
    public ?array $ips = null;
    public ?string $searchPhrase = null;
    public ?int $count = null;
    public ?int $offset = null;
    public bool $includeSoftDeletes = false;

    public function __construct(DAO $dao)
    {
        $this->dao = $dao;
    }

    public function getCount(): int
    {
        return $this->dao->getCount($this);
    }

    /**
     * @return Collection<int,int>
     */
    public function getIds(): Collection
    {
        return $this->dao->getIds($this);
    }

    /**
     * @copydoc DAO::getMany()
     *
     * @return LazyCollection<int,T>
     */
    public function getMany(): LazyCollection
    {
        return $this->dao->getMany($this);
    }

    /**
     * Filter institutions by one or more contexts
     */
    public function filterByContextIds(?array $contextIds): self
    {
        $this->contextIds = $contextIds;
        return $this;
    }

    /**
     * Filter institutions by one or more IPs
     */
    public function filterByIps(?array $ips): self
    {
        $this->ips = $ips;
        return $this;
    }

    /**
     * Filter institutions by those matching a search query
     */
    public function searchPhrase(?string $phrase): self
    {
        $this->searchPhrase = $phrase;
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
     * Consider soft deleted institutions
     */
    public function includeSoftDeletes(bool $include = true): self
    {
        $this->includeSoftDeletes = $include;
        return $this;
    }

    /**
     * @copydoc CollectorInterface::getQueryBuilder()
     */
    public function getQueryBuilder(): Builder
    {
        $qb = DB::table($this->dao->table . ' as i')->select('i.*');

        if (!is_null($this->contextIds)) {
            $qb->whereIn('i.context_id', $this->contextIds);
        }

        if (!$this->includeSoftDeletes) {
            $qb->whereNull('i.deleted_at');
        }

        if ($this->searchPhrase !== null) {
            $words = explode(' ', $this->searchPhrase);
            if (count($words)) {
                foreach ($words as $word) {
                    $word = addcslashes($word, '%_');
                    $qb->where(function ($qb) use ($word) {
                        $qb->whereIn('i.institution_id', function ($qb) use ($word) {
                            $qb->select('iss.institution_id')
                                ->from($this->dao->settingsTable . ' as iss')
                                ->where('iss.setting_name', '=', 'name')
                                ->where(DB::raw('lower(iss.setting_value)'), 'LIKE', DB::raw("lower('%{$word}%')"));
                        })
                            ->orWhereIn('i.institution_id', function ($qb) use ($word) {
                                $qb->select('ips.institution_id')
                                    ->from('institution_ip as ips')
                                    ->where(DB::raw('lower(ips.ip_string)'), 'LIKE', DB::raw("lower('%{$word}%')"));
                            });
                    });
                }
            }
        }

        if (!is_null($this->ips)) {
            foreach ($this->ips as $index => $ip) {
                $ip = sprintf('%u', ip2long($ip));
                if ($index === 0) {
                    $qb->whereIn('i.institution_id', function ($qb) use ($ip) {
                        $qb->select('ip.institution_id')
                            ->from('institution_ip as ip')
                            ->where(function ($qb) use ($ip) {
                                $qb->whereNotNull('ip.ip_end')
                                    ->where('ip.ip_start', '<=', $ip)
                                    ->where('ip.ip_end', '>=', $ip);
                            })
                            ->orWhere(function ($qb) use ($ip) {
                                $qb->whereNull('ip.ip_end')
                                    ->where('ip.ip_start', '=', $ip);
                            });
                    });
                    continue;
                }
                $qb->orWhereIn('i.institution_id', function ($qb) use ($ip) {
                    $qb->select('ip.institution_id')
                        ->from('institution_ip as ip')
                        ->where(function ($qb) use ($ip) {
                            $qb->whereNotNull('ip.ip_end')
                                ->where('ip.ip_start', '<=', $ip)
                                ->where('ip.ip_end', '>=', $ip);
                        })
                        ->orWhere(function ($qb) use ($ip) {
                            $qb->whereNull('ip.ip_end')
                                ->where('ip.ip_start', '=', $ip);
                        });
                });
            }
        }

        if (!is_null($this->count)) {
            $qb->limit($this->count);
        }

        if (!is_null($this->offset)) {
            $qb->offset($this->offset);
        }

        return $qb;
    }
}
