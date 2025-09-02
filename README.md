<h1 align="center">Laravel Import Tables</h1>
<p align="center">
    <a href="https://packagist.org/packages/jhonoryza/laravel-import-tables">
        <img src="https://poser.pugx.org/jhonoryza/laravel-import-tables/d/total.svg" alt="Total Downloads">
    </a>
    <a href="https://packagist.org/packages/jhonoryza/laravel-import-tables">
        <img src="https://poser.pugx.org/jhonoryza/laravel-import-tables/v/stable.svg" alt="Latest Stable Version">
    </a>
    <a href="https://packagist.org/packages/jhonoryza/laravel-import-tables">
        <img src="https://poser.pugx.org/jhonoryza/laravel-import-tables/license.svg" alt="License">
    </a>
</p>

This package provide ability to track import progress and history

## Requirement

- PHP 8.1 - 8.4
- Laravel 9, 10, 11, 12
- Redis
- [Laravel Excel](https://docs.laravel-excel.com)

## Getting Started

```bash
composer require jhonoryza/laravel-import-tables
```

run migration to create `imports` table

```bash
php artisan migrate
```

## How it works

when import is start all the progress data will be saved to redis,
after import event or failed event triggered, the progress data will be flushed to the database imports table.

## Change redis connection

publish the configuration

```bash
php artisan vendor:publish --tag import-tables
```

this will copy default config to `config/import-tables.php`

you can change redis connection to be used here

## Usage Example

create controller class `ImportController`

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\UserImport;
use Jhonoryza\LaravelImportTables\Repositories\ImportRepository;
use Jhonoryza\LaravelImportTables\Services\ImportProgress;

class ImportController extends Controller
{
    /**
     * get all import data
     */
    public function index(Request $request, ImportRepository $repo)
    {
        $imports = $repo->getList(
            status: $request->get('status'),
            module: $request->get('module'),
            limit: 50
        );

        return response()->json($imports);
    }

    /**
     * get specific import data
     */
    public function show($id, ImportRepository $repo)
    {
        $import = $repo->getById($id);

        return response()->json($import);
    }

    /**
     * import function
     */
    public function store(Request $request)
    {
        $request->validate([
            'file'   => 'required|file|mimes:xlsx,csv',
            'module' => 'required|string',
        ]);

        $file = $request->file('file');

        $this->validateDuplicate($file);

        $identifier = 'user';
        $this->validateProcessingImport($identifier);

        ImportProgress::make($identifier)
            ->pending('user', $file->getClientOriginalName());

        $import = new UserImport($identifier);
        $import->queue($request->file('file'));

        return response()->json([
            'message' => 'processing user import',
            'import_id' => $repo->getModel()->id,
        ]);
    }

    private function validateProcessingImport($identifier): void
    {
        $isProcessing = ImportProgress::make($identifier)
            ->isProcessing();

        if ($isProcessing) {
            session()->flash('failed', "please try again, there is ongoing process user import.");
            throw ValidationException::withMessages([]);
        }
    }

    private function validateDuplicate($file): void
    {
        $data = Excel::toArray([], $file)[0]; // First Sheet
        $emails = collect($data)
            ->skip(1) // skip header
            ->pluck(0) // first column
            ->filter();

        $duplicates = $emails
            ->values()
            ->duplicates();

        if ($duplicates->isNotEmpty()) {
            $message = 'Duplicate mails in Excel file: ' . $duplicates->join(', ');
            session()->flash('failed', $message);
            throw ValidationException::withMessages([]);
        }
        $emails = $emails->all();

        $duplicates = DB::table('users')
            ->select('email')
            ->whereNull('deleted_at')
            ->whereIn('email', $emails)
            ->get();

        if ($duplicates->isNotEmpty()) {
            $duplicateCount = $duplicates->count();
            $displayLimit = 5;

            if ($duplicateCount > $displayLimit) {
                $codesToDisplay = $duplicates->take($displayLimit)->implode('email', ', ');
                $message = "There is {$duplicateCount} duplicate mails. some example: {$codesToDisplay}, ...";
            } else {
                $codesToDisplay = $duplicates->implode('email', ', ');
                $message = "Duplicate mails founded: {$codesToDisplay}";
            }
            session()->flash('failed', $message);
            throw ValidationException::withMessages([]);
        }
    }
}
```

create import class `UserImport`

```php
<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\ShouldQueue;
use Maatwebsite\Excel\Events\AfterChunk;
use Maatwebsite\Excel\Events\AfterImport;
use Maatwebsite\Excel\Events\BeforeImport;
use Maatwebsite\Excel\Validators\Failure;
use App\Models\User;
use Jhonoryza\LaravelImportTables\Services\ImportProgress;

class UserImport implements ToModel, WithHeadingRow, WithChunkReading, WithBatchInserts, WithEvents, ShouldQueue, SkipsEmptyRows, WithValidation, SkipsOnFailure
{
    use SkipsFailures;
    use Importable;

    private string $identifier;

    public function __construct(string $identifier)
    {
        $this->identifier = $identifier;
    }

    public function batchSize(): int
    {
        return 500;
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    public function rules(): array
    {
        return [
            '*.name' => [
                'required',
            ],
            '*.email' => [
                'required',
            ],
        ];
    }

    public function model(array $rows)
    {
        return User::create([
            'name'  => $row['name'],
            'email' => $row['email'],
        ]);
    }

    public function registerEvents(): array
    {
        return [
            BeforeImport::class => function () {
                ImportProgress::make($this->identifier)
                    ->processing();
            },
            AfterImport::class => function () {
                ImportProgress::make($this->identifier)
                    ->done();
            },
        ];
    }

    public function getFailures()
    {
        return $this->failures();
    }

    public function onFailure(Failure ...$failures)
    {
        $progress = ImportProgress::make($this->identifier);

        foreach ($this->failures as $failure) {
            $err = 'row: ' . $failure->row() . ' err: ' . collect($failure->errors())->implode(',');
            $progress
                ->pushFailMessage($err)
                ->incrementFail();
        }

        $this->failures = array_merge($this->failures, $failures);
}

    public function failed(\Throwable $e)
    {
        ImportProgress::make($this->identifier)
            ->pushFailMessage($e->getMessage())
            ->failed();
    }
}
```

another sample using `ToCollection` approach

```php
public function collection(Collection $collection)
{
    $rows = $collection->map(fn($row) => [
        'is_receive_repeatedly' => 1,
        'voucher_id' => $this->voucherId,
        'code' => $row['code'],
        'allocation' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ])->toArray();

    try{
        DB::beginTransaction();

        VoucherCode::insert($rows);

        DB::commit();

        ImportProgress::make($this->identifier)
            ->incrementOk(count($rows))
            ->pushOkMessage("Inserted " . count($rows) . " rows");

    } catch (Throwable $th) {
        DB::rollBack();

        ImportProgress::make($this->identifier)
            ->incrementFail($collection->count())
            ->pushFailMessage("Error: " . $th->getMessage());
    }
}
```

the rest implementation is the same

---

## Security

If you've found a bug regarding security, please mail [jardik.oryza@gmail.com](mailto:jardik.oryza@gmail.com) instead of
using the issue tracker.

## License

The MIT License (MIT). Please see [License File](license.md) for more information.