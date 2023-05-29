<?php
/**
 * @file classes/category/Collector.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Collector
 *
 * @brief A helper class to configure a Query Builder to get a collection of categories
 */

namespace PKP\category;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use PKP\core\interfaces\CollectorInterface;
use PKP\plugins\Hook;

/**
 * @template T of Category
 */
class Collector implements CollectorInterface
{
    public DAO $dao;
    public ?array $contextIds = null;
    public ?array $parentIds = null;
    public ?array $paths = null;
    public ?array $publicationIds = null;
    public ?int $count = null;
    public ?int $offset = null;

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
     * @return LazyCollection<int,T>
     */
    public function getMany(): LazyCollection
    {
        return $this->dao->getMany($this);
    }

    /**
     * Filter categories by one or more contexts
     */
    public function filterByContextIds(?array $contextIds): self
    {
        $this->contextIds = $contextIds;
        return $this;
    }

    /**
     * Filter categories by one or more parent category IDs
     */
    public function filterByParentIds(?array $parentIds): self
    {
        $this->parentIds = $parentIds;
        return $this;
    }

    /**
     * Filter categories by one or more publication IDs
     */
    public function filterByPublicationIds(?array $publicationIds): self
    {
        $this->publicationIds = $publicationIds;
        return $this;
    }

    /**
     * Filter categories by one or more paths
     */
    public function filterByPaths(?array $paths): self
    {
        $this->paths = $paths;
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
        $qb = DB::table($this->dao->table . ' as c')
            ->leftJoin('categories AS pc', 'c.parent_id', '=', 'pc.category_id')
            ->select(['c.*']);

        $qb->when($this->contextIds !== null, function ($query) {
            $query->whereIn('c.context_id', $this->contextIds);
        });

        $qb->when($this->paths !== null, function ($query) {
            $query->whereIn('c.path', $this->paths);
        });

        $qb->when($this->publicationIds !== null, function ($query) {
            $query->whereIn('c.category_id', function ($query) {
                $query->select('category_id')->from('publication_categories')->whereIn('publication_id', $this->publicationIds);
            });
        });

        $qb->when($this->parentIds !== null, function ($query) {
            // parentIds may contain mixed values and nulls; make sure the mix translates into the query accurately
            $nonNullParentIds = array_filter($this->parentIds);
            if (count($nonNullParentIds)) {
                $query->whereIn('c.parent_id', array_filter($this->parentIds));
            }
            if (in_array(null, $this->parentIds)) {
                if (count($nonNullParentIds)) {
                    $query->orWhereNull('c.parent_id');
                } else {
                    $query->whereNull('c.parent_id');
                }
            }
        });

        $qb->orderBy(DB::raw('(COALESCE((pc.seq * 8192) + pc.category_id, 0) * 8192) + CASE WHEN pc.category_id IS NULL THEN 8192 * ((c.seq * 8192) + c.category_id) ELSE c.seq END'));

        if (isset($this->count)) {
            $qb->limit($this->count);
        }

        if (isset($this->offset)) {
            $qb->offset($this->offset);
        }

        Hook::call('Category::Collector', [&$qb, $this]);

        return $qb;
    }
}
