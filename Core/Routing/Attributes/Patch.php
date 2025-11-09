<?php


namespace Core\Routing\Attributes;

#[\Attribute(\Attribute::TARGET_METHOD)]
class Patch extends Route
{
    public function __construct(string $path)
    {
        parent::__construct('PATCH', $path);
    }
}