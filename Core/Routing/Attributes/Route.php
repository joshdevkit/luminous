<?php

namespace Core\Routing\Attributes;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
class Route
{
    public function __construct(
        public ?string $method = null,
        public ?string $path = null,
        public ?string $prefix = null
    ) {}
}