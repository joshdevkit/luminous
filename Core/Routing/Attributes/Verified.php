<?php

namespace Core\Routing\Attributes;

use Attribute;

/**
 * Shorthand for VerifiedMiddleware
 * 
 * Usage:
 * #[Verified]
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Verified
{
    public function __construct() {}
}