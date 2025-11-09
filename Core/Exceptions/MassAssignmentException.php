<?php

namespace Core\Exceptions;

class MassAssignmentException extends \RuntimeException
{
    public static function forKeys(string $className, array $keys): self
    {
        $keysList = implode(', ', array_map(fn($k) => "'$k'", $keys));
        $count = count($keys);
        $plural = $count > 1 ? 'columns' : 'column';
        
        $message = "Mass assignment error on [{$className}]: " .
            "The {$plural} [{$keysList}] " .
            ($count > 1 ? "are" : "is") . " not defined in the \$entities array. " .
            "Please add " . ($count > 1 ? "them" : "it") . " to the \$entities property or remove " .
            ($count > 1 ? "them" : "it") . " from the input data.";
        
        return new self($message);
    }
}