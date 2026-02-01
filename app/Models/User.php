<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{

    public $timestamps = true;

    protected $fillable = [
        'username',
        'password',
        'role',
    ];

    protected $hidden = [
        'password',
    ];
}
