<?php


namespace App\Entities;

// use Core\Contracts\Auth\MustVerifyEmail;
// use Core\Database\Traits\HasUuid;
use Core\Auth\User as Auth;
use Core\Database\Traits\HasFactory;

class User extends Auth 
{
    use HasFactory;

    protected $entities = [
        'name',
        'email',
        'password',
        'role',
        'status',
    ];

    protected $hidden = [
        'password'
    ];

    protected $casts = [
        'password' => 'hashed',
        'active' => 'boolean',
        'role' => 'string',
        'email_verified_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    
    // protected $primaryKey = 'uuid';

    // protected $keyType = 'string';

    // public $incrementing = false;

}
