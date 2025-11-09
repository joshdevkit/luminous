<?php

namespace Core\Routing\Attributes;

use Attribute;

/**
 * Shorthand for AuthMiddleware
 * 
 * #[Auth]
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Auth
{
    public function __construct(
        public ?string $guard = null
    ) {}
}