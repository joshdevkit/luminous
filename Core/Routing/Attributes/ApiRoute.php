<?php

namespace Core\Routing\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class ApiRoute
{
    public string $prefix;

    public function __construct(string $prefix)
    {
        $this->prefix = '/' . trim($prefix, '/');
    }
}