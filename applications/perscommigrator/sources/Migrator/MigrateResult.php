<?php

declare(strict_types=1);

namespace IPS\perscommigrator\Migrator;

class _migrateResult
{
    public $started = 0;
    public $ended = 0;
    public $items = [];

    public function start(): self
    {
        $this->started = time();
        return $this;
    }

    public function end(): self
    {
        $this->ended = time();
        return $this;
    }

    public function getDuration(): int
    {
        return $this->ended - $this->started;
    }

    public function hasErrors(): bool
    {
        foreach ($this->items as $item) {
            if (!empty($item->errorMessages)) {
                return true;
            }
        }
        return false;
    }
}
