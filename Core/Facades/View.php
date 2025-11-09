<?php

namespace Core\Facades;

/**
 * ---------------------------------------------------------------
 * Facade: View
 * ---------------------------------------------------------------
 * 
 * The View Facade provides a static interface to the underlying
 * view factory or view engine bound in the service container.
 * 
 * This allows developers to render templates or pass data to views
 * without directly resolving the view service from the container.
 * 
 * 
 * @method static \Core\Contracts\View\ViewInterface make(string $view, array $data = [])
 * @method static bool exists(string $view)
 * 
 * @see \Core\View\ViewFactory
 * @see \Core\Contracts\View\ViewInterface
 */
class View extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'view';
    }
}
