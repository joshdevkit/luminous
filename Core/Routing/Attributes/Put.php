<?php

namespace Core\Routing\Attributes;

#[\Attribute(\Attribute::TARGET_METHOD)]
class Put extends Route
{
    public function __construct(string $path)
    {
        parent::__construct('PUT', $path);
    }
}