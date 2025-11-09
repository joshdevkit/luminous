<?php


namespace Core\Routing\Attributes;

#[\Attribute(\Attribute::TARGET_METHOD)]
class Delete extends Route
{
    public function __construct(string $path)
    {
        parent::__construct('DELETE', $path);
    }
}