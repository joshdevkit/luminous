<?php

namespace Core\Routing\Attributes;

use Attribute;

/**
 * Shorthand for RateLimitMiddleware
 * 
 * Usage:
 * #[Throttle(60, 1)]  // 60 requests per 1 minute
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Throttle
{
    public function __construct(
        public int $maxAttempts = 60,
        public int $decayMinutes = 1
    ) {}
}