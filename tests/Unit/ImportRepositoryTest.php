<?php

namespace Tests\Unit;

use Tests\TestCase;
use Jhonoryza\LaravelImportTables\Models\Import;
use Jhonoryza\LaravelImportTables\Repositories\ImportRepository;

uses(TestCase::class);

beforeEach(function () {
    $this->repository = new ImportRepository();
});

test('it can create new instance using make method', function () {
    $repository = ImportRepository::make();
    expect($repository)->toBeInstanceOf(ImportRepository::class);
});

test('it can set custom model', function () {
    $model = new Import();
    $repository = ImportRepository::make()->setModel($model);
    
    expect($repository->getModel())->toBeInstanceOf(Import::class);
});

test('it can set module name', function () {
    $repository = ImportRepository::make()
        ->setModuleName('users')
        ->save();
    
    expect($repository->getModel()?->module_name)->toBe('users');
});

test('it can set file name', function () {
    $repository = ImportRepository::make()
        ->setFileName('users.csv')
        ->save();
    
    expect($repository->getModel()?->filename)->toBe('users.csv');
});

test('it can get list of imports', function () {
    // Create some test data within this test
    ImportRepository::make()
        ->setModuleName('users')
        ->setFileName('users1.csv')
        ->save();
    
    ImportRepository::make()
        ->setModuleName('users')
        ->setFileName('users2.csv')
        ->save();
    
    $repository = new ImportRepository();
    $list = $repository->getList(module: 'users');
    
    expect($list)->toHaveCount(2)
        ->and($list->first())->toBeInstanceOf(Import::class)
        ->and($list->first()?->module_name)->toBe('users');
});

test('it can get list of imports filtered by status', function () {
    // Create test data with different statuses within this test
    ImportRepository::make()
        ->setModuleName('users')
        ->setStatusDone()
        ->save();
    
    ImportRepository::make()
        ->setModuleName('users')
        ->setStatusPending()
        ->save();
    
    $repository = new ImportRepository()
        ->setModuleName('users');
    
    $doneList = $repository->getList('done');
    $pendingList = $repository->getList('pending');
    
    expect($doneList)->toHaveCount(1)
        ->and($pendingList)->toHaveCount(1)
        ->and($doneList->first()?->status)->toBe('done')
        ->and($pendingList->first()?->status)->toBe('pending');
});

test('it can track success and failed rows', function () {
    $repository = ImportRepository::make()
        ->setModuleName('users')
        ->setFileName('users.csv');
    
    // Simulate processing rows
    $repository->incrementTotal() // row 1
        ->markOk(1, 'Successfully imported')
        ->incrementTotal() // row 2
        ->markFail(2, 'Invalid data')
        ->incrementTotal() // row 3
        ->markOk(3, 'Successfully imported')
        ->save();
    
    $model = $repository->getModel();
    expect($model?->total_rows)->toBe(3)
        ->and($model?->success_rows)->toBe(2)
        ->and($model?->failed_rows)->toBe(1)
        ->and($model?->success)->toHaveCount(2)
        ->and($model?->errors)->toHaveCount(1);
});

test('it can change import status', function () {
    $repository = ImportRepository::make()
        ->setModuleName('users')
        ->setStatusPending()
        ->save();
    
    expect($repository->getModel()?->status)->toBe('pending');
    
    $repository->setStatusProcessing()->save();
    expect($repository->getModel()?->status)->toBe('processing');
    
    $repository->setStatusDone()->save();
    expect($repository->getModel()?->status)->toBe('done');
    
    $repository->setStatusFailed()->save();
    expect($repository->getModel()?->status)->toBe('failed');

    expect(Import::count())->toBe(1);
});

test('it can get import by id', function () {
    $repository = ImportRepository::make()
        ->setModuleName('users')
        ->setFileName('users.csv')
        ->save();
    
    $id = $repository->getModel()?->id;
    
    $found = ImportRepository::getById($id);
    
    expect($found)->toBeInstanceOf(Import::class)
        ->and($found?->id)->toBe($id);
});
