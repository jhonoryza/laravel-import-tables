<?php

namespace Tests\Unit;

use Jhonoryza\LaravelImportTables\Models\Import;
use Jhonoryza\LaravelImportTables\Repositories\ImportRepository;
use Tests\TestCase;

uses(TestCase::class);

test('it can create new instance using make method', function () {
    $repository = ImportRepository::make();
    expect($repository)->toBeInstanceOf(ImportRepository::class);
});

test('it can set custom model', function () {
    $model = new Import;
    $repository = ImportRepository::make()->setModel($model);

    expect($repository->getModel())->toBeInstanceOf(Import::class);
});

test('it can set module name', function () {
    $repository = ImportRepository::make()
        ->setModuleName('users')
        ->setKey('users')
        ->setStatusPending()
        ->save();

    expect($repository->getModel()?->module_name)->toBe('users');
});

test('it can set file name', function () {
    $repository = ImportRepository::make()
        ->setModuleName('users')
        ->setKey('users')
        ->setStatusPending()
        ->setFileName('users.csv')
        ->save();

    expect($repository->getModel()?->filename)->toBe('users.csv');
});

test('it can get list of imports', function () {
    // Create some test data within this test
    ImportRepository::make()
        ->setKey('users')
        ->setStatusPending()
        ->setModuleName('users')
        ->setFileName('users1.csv')
        ->save();

    ImportRepository::make()
        ->setKey('users')
        ->setStatusPending()
        ->setModuleName('users')
        ->setFileName('users2.csv')
        ->save();

    $repository = new ImportRepository;
    $list = $repository->getList(module: 'users');

    expect($list)->toHaveCount(1)
        ->and($list->first())->toBeInstanceOf(Import::class)
        ->and($list->first()?->module_name)->toBe('users');
});

test('it can get list of imports filtered by status', function () {
    // Create test data with different statuses within this test
    ImportRepository::make()
        ->setKey('users')
        ->setStatusPending()
        ->setModuleName('users')
        ->setFileName('users1.csv')
        ->save();

    ImportRepository::make()
        ->resolveByKey('users')
        ->setStatusProcessing()
        ->save();

    ImportRepository::make()
        ->resolveByKey('users')
        ->setStatusDone()
        ->save();

    ImportRepository::make()
        ->setKey('users')
        ->setModuleName('users')
        ->setFileName('users2.csv')
        ->setStatusPending()
        ->save();

    $doneList = ImportRepository::getList(status: Import::DONE, module: 'users');
    $pendingList = ImportRepository::getList(status: Import::PENDING, module: 'users');

    expect($doneList)->toHaveCount(1)
        ->and($pendingList)->toHaveCount(1)
        ->and($doneList->first()?->status)->toBe(Import::DONE)
        ->and($pendingList->first()?->status)->toBe(Import::PENDING);
});

test('it can track success and failed rows', function () {
    ImportRepository::make()
        ->setKey('users')
        ->setModuleName('users')
        ->setFileName('users.csv')
        ->setStatusPending()
        ->save();

    ImportRepository::make()
        ->resolveByKey('users')
        ->setStatusProcessing()
        ->save();

    // Simulate processing rows
    ImportRepository::make()
        ->resolveByKey('users')
        ->setStatusDone()
        ->incrementTotal() // row 1
        ->markOk('Successfully imported')
        ->incrementOk()
        ->incrementTotal() // row 2
        ->markFail('Invalid data')
        ->incrementFail()
        ->incrementTotal() // row 3
        ->markOk('Successfully imported')
        ->incrementOk()
        ->save();

    $doneList = ImportRepository::getList(status: Import::DONE, module: 'users');
    $model = $doneList->first()->getModel();
    expect($model?->total_rows)->toBe(3)
        ->and($model?->success_rows)->toBe(2)
        ->and($model?->failed_rows)->toBe(1)
        ->and($model?->success)->toHaveCount(2)
        ->and($model?->errors)->toHaveCount(1);
});

test('it can change import status', function () {
    $repository = ImportRepository::make()
        ->setKey('users')
        ->setModuleName('users')
        ->setStatusPending()
        ->save();

    expect($repository->getModel()?->status)->toBe(Import::PENDING);

    $repository->setStatusProcessing()->save();
    expect($repository->getModel()?->status)->toBe(Import::PROCESSING);

    $repository->setStatusDone()->save();
    expect($repository->getModel()?->status)->toBe(Import::DONE);

    $repository->setStatusFailed()->save();
    expect($repository->getModel()?->status)->toBe(Import::DONE);

    expect(Import::count())->toBe(1);
});

test('it can get import by id', function () {
    $repository = ImportRepository::make()
        ->setKey('users')
        ->setModuleName('users')
        ->setFileName('users.csv')
        ->setStatusPending()
        ->save();

    $id = $repository->getModel()?->id;

    $found = ImportRepository::getById($id);

    expect($found)->toBeInstanceOf(Import::class)
        ->and($found?->id)->toBe($id);
});
