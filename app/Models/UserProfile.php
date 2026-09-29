<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserProfile extends Model
{
    protected $guarded = ['user_id'];

    protected function casts(): array
    {
        return ['date_of_birth' => 'date:Y-m-d'];
    }
}
