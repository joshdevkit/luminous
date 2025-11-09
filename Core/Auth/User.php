<?php

namespace Core\Auth;

use Core\Auth\Passwords\CanResetPassword;
use Core\Database\Model;
use Core\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Core\Contracts\Auth\CanResetPassword as CanResetPasswordContract;

class User extends Model implements
    AuthenticatableContract,
    CanResetPasswordContract
{
    use Authenticatable, CanResetPassword, MustVerifyEmail;
}