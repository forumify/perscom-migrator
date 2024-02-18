<?php

declare(strict_types=1);

namespace IPS\perscommigrator\Migrator;

class _resultItem
{
    public $entityName;
    public $created = 0;
    public $skipped = 0;
    public $error = 0;
    public $errorMessages = [];

    public function __construct(string $entityName)
    {
        $this->entityName = $entityName;
    }
}
