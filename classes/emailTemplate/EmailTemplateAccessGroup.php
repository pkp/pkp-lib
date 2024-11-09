<?php

namespace PKP\emailTemplate;

use Eloquence\Behaviours\HasCamelCasing;
use Eloquence\Database\Model;
use Illuminate\Contracts\Database\Eloquent\Builder;

class EmailTemplateAccessGroup extends Model
{
    use HasCamelCasing;
    public $timestamps = false;
    protected $primaryKey = 'email_template_user_group_access_id';
    protected $table = 'email_template_user_group_access';
    protected $fillable = ['userGroupId', 'contextId','emailKey'];


    public function scopeWithEmailKey(Builder $query, ?array $keys): Builder
    {
        return $query->when(!empty($keys), function ($query) use ($keys) {
            return $query->whereIn('email_key', $keys);
        });
    }

    public function scopeWithContextId(Builder $query, int $contextId): Builder
    {
        return $query->where('context_id', $contextId);
    }

    public function scopeWithGroupIds(Builder $query, array $ids): Builder
    {
        return $query->whereIn('user_group_id', $ids);
    }
}
