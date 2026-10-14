<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Admin extends Authenticatable
{
    use HasApiTokens;
    
    protected $fillable = ['f_name', 'l_name', 'phone', 'email', 'password'];

    protected $hidden = ['password'];


}
