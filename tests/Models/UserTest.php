<?php

namespace Jovencio\Tests\Models;

use Illuminate\Database\Eloquent\Model;

class UserTest extends Model
{
    public function posts()
    {
        return $this->hasMany(PostTest::class);
    }
}
