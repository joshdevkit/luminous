<?php

namespace Core\Routing\Attributes;

#[\Attribute(\Attribute::TARGET_METHOD)]
class Get extends Route
{
    public function __construct(string $path)
    {
        parent::__construct('GET', $path);
    }
}