<?php

namespace Jhonoryza\LaravelImportTables\Repositories;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Jhonoryza\LaravelImportTables\Models\Import;

class ImportRepository
{
    protected ?Model $model = null;

    protected string $key = '';

    protected string $moduleName = 'default';

    protected string $status = '';

    protected string $fileName = '';

    protected array $messageErrors = [];

    protected array $messageOk = [];

    protected int $totalRows = 0;

    protected int $totalOk = 0;

    protected int $totalFail = 0;

    public function __construct(?Model $model = null)
    {
        $this->model = $model ?? null;
    }

    public static function make(): self
    {
        return new self;
    }

    public function setModel(Model $model): self
    {
        $this->model = $model;

        $this->key = $this->model->key ?? $this->key;
        $this->moduleName = $this->model->module_name ?? $this->moduleName;
        $this->fileName = $this->model->filename ?? $this->fileName;
        $this->status = $this->model->status ?? $this->status;
        $this->totalRows = $this->model->total_rows ?? $this->totalRows;
        $this->totalOk = $this->model->success_rows ?? $this->totalOk;
        $this->totalFail = $this->model->failed_rows ?? $this->totalFail;
        $this->messageErrors = $this->model->errors ?? $this->messageErrors;
        $this->messageOk = $this->model->success ?? $this->messageOk;

        return $this;
    }

    public function setKey($key): self
    {
        $this->resolveByKey($key);
        $this->key = $key;

        return $this;
    }

    public function resolveById(string|int $id): self
    {
        $model = Import::query()
            ->find($id);

        if ($model) {
            $this->setModel($model);
        }

        return $this;
    }

    public function resolveByKey(string $key): self
    {
        $model = Import::query()
            ->where('key', $key)
            ->whereIn('status', [Import::PENDING, Import::PROCESSING])
            ->first();

        if ($model) {
            $this->setModel($model);
        }

        return $this;
    }

    public function setModuleName(string $name): self
    {
        $this->moduleName = $name;

        return $this;
    }

    public function setFileName(string $path): self
    {
        $this->fileName = $path;

        return $this;
    }

    public function getModel(): ?Model
    {
        return $this->model;
    }

    public static function getList(
        ?string $status = null,
        ?string $module = null,
        int $limit = 30
    ): Collection {
        return Import::query()
            ->where('module_name', $module ?? 'default')
            ->latest()
            ->when(
                $status,
                fn ($query, $value) => $query->where('status', $value)
            )
            ->limit($limit)
            ->get();
    }

    public static function resolveProcessingStatusMoreThan(Carbon $time): void
    {
        Import::query()
            ->latest()
            ->where('status', Import::PROCESSING)
            ->where('updated_at', '<', $time)
            ->get()
            ->each(function ($import) {
                self::make()
                    ->resolveById($import->id)
                    ->setStatusStuck()
                    ->markFail(null, 'Import did not finish (possibly worker down or fatal error)')
                    ->save();
            });
    }

    public static function getById($importId): ?Model
    {
        return Import::query()
            ->findOrFail($importId);
    }

    public function setStatusPending(): self
    {
        if ($this->status == '') {
            $this->status = Import::PENDING;
        }

        return $this;
    }

    public function isPending(): bool
    {
        return $this->status == Import::PENDING;
    }

    public function setStatusProcessing(): self
    {
        if ($this->status == Import::PENDING) {
            $this->status = Import::PROCESSING;
        }

        return $this;
    }

    public function isProcessing(): bool
    {
        return $this->status == Import::PROCESSING;
    }

    public function setStatusDone(): self
    {
        if ($this->status == Import::PROCESSING) {
            $this->status = Import::DONE;
        }

        return $this;
    }

    public function isDone(): bool
    {
        return $this->status == Import::DONE;
    }

    public function setStatusStuck(): self
    {
        if ($this->status == Import::PROCESSING) {
            $this->status = Import::STUCK;
        }

        return $this;
    }

    public function isStuck(): bool
    {
        return $this->status == Import::STUCK;
    }

    public function setStatusFailed(): self
    {
        if ($this->status == Import::PROCESSING) {
            $this->status = Import::FAILED;
        }

        return $this;
    }

    public function isFailed(): bool
    {
        return $this->status == Import::FAILED;
    }

    public function incrementTotal(?int $value = null): self
    {
        $this->totalRows += $value ? $value : 1;

        return $this;
    }

    public function setTotalRows($value): self
    {
        $this->totalRows = $value;

        return $this;
    }

    public function incrementOk(?int $value = null): self
    {
        $this->totalOk += $value ? $value : 1;

        return $this;
    }

    public function setTotalOk($value): self
    {
        $this->totalOk = $value;

        return $this;
    }

    public function incrementFail(?int $value = null): self
    {
        $this->totalFail += $value ? $value : 1;

        return $this;
    }

    public function setTotalFail($value): self
    {
        $this->totalFail = $value;

        return $this;
    }

    public function markFail($message): self
    {
        $errors = $this->messageErrors ?? [];
        $errors[] = $message;
        $this->messageErrors = $errors;

        return $this;
    }

    public function setMessageFail($messages): self
    {
        $this->messageErrors = $messages;

        return $this;
    }

    public function markOk($message): self
    {
        $success = $this->messageOk ?? [];
        $success[] = $message;
        $this->messageOk = $success;

        return $this;
    }

    public function setMessageOk($messages): self
    {
        $this->messageOk = $messages;

        return $this;
    }

    public function save(): self
    {
        if ($this->model == null && $this->status == Import::PENDING) {
            $this->model = new Import;
            $this->model->key = $this->key;
            $this->model->module_name = $this->moduleName;
            $this->model->filename = $this->fileName;
            $this->model->status = $this->status;
            $this->model->total_rows = $this->totalRows;
            $this->model->success_rows = $this->totalOk;
            $this->model->failed_rows = $this->totalFail;
            $this->model->errors = $this->messageErrors;
            $this->model->success = $this->messageOk;
        } elseif ($this->model && $this->status != Import::PENDING) {
            $this->model->status = $this->status;
            $this->model->total_rows = $this->totalRows;
            $this->model->success_rows = $this->totalOk;
            $this->model->failed_rows = $this->totalFail;
            $this->model->errors = $this->messageErrors;
            $this->model->success = $this->messageOk;
        }

        if ($this->model?->isDirty()) {
            $this->model->save();
        }

        return $this;
    }
}
