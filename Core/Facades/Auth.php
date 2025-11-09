<?php

namespace Core\Facades;

/**
 * Class Auth
 *
 * @method static bool attempt(array $credentials) Attempt to authenticate a user with the given credentials.
 * @method static void login(object $user) Log in the specified user instance.
 * @method static void logout() Log out the currently authenticated user.
 * @method static \App\Entities\User|null user() Get the currently authenticated user instance.
 * @method static bool check() Determine if the current user is authenticated.
 * @method static bool guest() Determine if no user is currently authenticated.
 * @method static mixed id() Get the ID of the currently authenticated user.
 * @method static string hashPassword(string $password) Hash the given password using a secure algorithm.
 * @method static void fakeUser() Create a fake authenticated user for testing or development.
 *
 * @see \Core\Auth\AuthManager
 * @package Core\Facades
 */
class Auth extends Facade
{
    /**
     * Get the registered name of the component in the service container.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'auth';
    }
}
