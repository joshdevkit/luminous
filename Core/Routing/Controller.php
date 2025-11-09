<?php

namespace Core\Routing;

use Core\Contracts\Http\RequestInterface;

abstract class Controller
{
    protected RequestInterface $parambinder;

    public function setRequest(RequestInterface $parambinder): void
    {
        $this->parambinder = $parambinder;
    }
}