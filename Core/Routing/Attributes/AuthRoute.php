<?php

namespace Core\Routing\Attributes;

#[\Attribute(\Attribute::TARGET_METHOD)]
class AuthRoute
{
    public function __construct(
        public ?string $type = 'login' 
    ) {}
}
