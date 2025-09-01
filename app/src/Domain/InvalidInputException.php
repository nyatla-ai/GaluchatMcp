<?php
namespace App\Domain;

class InvalidInputException extends \RuntimeException
{
    private array $location;

    public function __construct(string $reason, array $location = [])
    {
        parent::__construct($reason);
        $this->location = $location;
    }

    public function getLocation(): array
    {
        return $this->location;
    }
}
