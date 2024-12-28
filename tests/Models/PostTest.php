<?php

namespace Jovencio\Tests\Models;

use Illuminate\Database\Eloquent\Model;

class PostTest extends Model
{
   public function user()
   {
       return $this->belongsTo(UserTest::class, "user_test_id");
   }
}
