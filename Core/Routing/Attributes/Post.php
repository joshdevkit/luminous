<?php

namespace Core\Routing\Attributes;

#[\Attribute(\Attribute::TARGET_METHOD)]
class Post extends Route
{
    public function __construct(string $path)
    {
        parent::__construct('POST', $path);
    }
}