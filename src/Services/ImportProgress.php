<?php

namespace Jhonoryza\LaravelImportTables\Services;

use Illuminate\Support\Facades\Redis;
use Jhonoryza\LaravelImportTables\Repositories\ImportRepository;

class ImportProgress
{
    protected string $key;
    protected string $connection;

    public function __construct(int|string $identifier)
    {
        $this->connection = config('import-tables.redis-connection');
        $this->key = "import:{$identifier}";
    }

    public static function make(int|string $identifier): self
    {
        return new static($identifier);
    }

    public function incrementTotalRow(int $count = 1): self
    {
        Redis::connection($this->connection)
            ->incrby("{$this->key}:total", $count);

        return $this;
    }

    public function incrementOk(int $count = 1): self
    {
        Redis::connection($this->connection)
            ->incrby("{$this->key}:ok", $count);

        return $this;
    }

    public function incrementFail(int $count = 1): self
    {
        Redis::connection($this->connection)
            ->incrby("{$this->key}:fail", $count);

        return $this;
    }

    public function pushOkMessage(string $msg, int $keepLast = 100): self
    {
        Redis::connection($this->connection)
            ->rpush("{$this->key}:msg:ok", $msg);
        Redis::connection($this->connection)
            ->ltrim("{$this->key}:msg:ok", -$keepLast, -1);

        return $this;
    }

    public function pushFailMessage(string $msg, int $keepLast = 100): self
    {
        Redis::connection($this->connection)
            ->rpush("{$this->key}:msg:fail", $msg);
        Redis::connection($this->connection)
            ->ltrim("{$this->key}:msg:fail", -$keepLast, -1);

        return $this;
    }

    public function getTotalRow(): int
    {
        return (int) (Redis::connection($this->connection)
            ->get("{$this->key}:total") ?? 0);
    }

    public function getTotalOk(): int
    {
        return (int) (Redis::connection($this->connection)
            ->get("{$this->key}:ok") ?? 0);
    }

    public function getTotalFailed(): int
    {
        return (int) (Redis::connection($this->connection)
            ->get("{$this->key}:fail") ?? 0);
    }

    public function getMessageOk(int $limit = 100): array
    {
        return Redis::connection($this->connection)
            ->lrange("{$this->key}:msg:ok", -$limit, -1);
    }

    public function getMessageFail(int $limit = 100): array
    {
        return Redis::connection($this->connection)
            ->lrange("{$this->key}:msg:fail", -$limit, -1);
    }

    public function clear(): self
    {
        Redis::connection($this->connection)
            ->del([
                "{$this->key}:total",
                "{$this->key}:ok",
                "{$this->key}:fail",
                "{$this->key}:msg:ok",
                "{$this->key}:msg:fail",
            ]);

        return $this;
    }

    public function isProcessing(): bool
    {
        return ImportRepository::make()
            ->resolveByKey($this->key)
            ->isProcessing();
    }

    public function pending(string $module, string $fileName): self
    {
        $this->clear();

        ImportRepository::make()
            ->setModuleName($module)
            ->setKey($this->key)
            ->setFileName($fileName)
            ->setStatusPending()
            ->save();

        return $this;
    }

    public function processing(): self
    {
        ImportRepository::make()
            ->resolveByKey($this->key)
            ->setStatusProcessing()
            ->save();

        return $this;
    }

    public function failed(): self
    {
        $this->incrementTotalRow($this->getTotalFailed() + $this->getTotalOk());
        ImportRepository::make()
            ->resolveByKey($this->key)
            ->setStatusFailed()
            ->setMessageOk($this->getMessageOk())
            ->setMessageFail($this->getMessageFail())
            ->setTotalRows($this->getTotalRow())
            ->setTotalFail($this->getTotalFailed())
            ->setTotalOk($this->getTotalOk())
            ->save();

        return $this;
    }

    public function done(): self
    {
        $this->incrementTotalRow($this->getTotalFailed() + $this->getTotalOk());
        ImportRepository::make()
            ->resolveByKey($this->key)
            ->setStatusDone()
            ->setMessageOk($this->getMessageOk())
            ->setMessageFail($this->getMessageFail())
            ->setTotalRows($this->getTotalRow())
            ->setTotalFail($this->getTotalFailed())
            ->setTotalOk($this->getTotalOk())
            ->save();

        return $this;
    }
}
