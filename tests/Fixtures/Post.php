<?php

namespace Zhanghongfei\OrgRbac\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Zhanghongfei\OrgRbac\Concerns\BelongsToTenant;

class Post extends Model
{
    use BelongsToTenant;

    protected $table = 'test_posts';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'title',
    ];
}
