<?php

declare(strict_types=1);

namespace IPS\perscommigrator\Migrator;

class _resultItem
{
    public string $entityName;
    public int $created = 0;
    public int $skipped = 0;
    public int $error = 0;
    public array $errorMessages = [];

    public function __construct(string $entityName)
    {
        $this->entityName = $entityName;
    }
}
