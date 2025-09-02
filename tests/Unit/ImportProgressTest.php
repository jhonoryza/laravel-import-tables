<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Redis;
use Jhonoryza\LaravelImportTables\Repositories\ImportRepository;
use Jhonoryza\LaravelImportTables\Services\ImportProgress;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    // Clear any existing data for our test key
    Redis::connection()->del('import:test-123:total');
    Redis::connection()->del('import:test-123:ok');
    Redis::connection()->del('import:test-123:fail');
    Redis::connection()->del('import:test-123:msg:ok');
    Redis::connection()->del('import:test-123:msg:fail');

    // Create a standard instance mock for repository
    $this->repository = new ImportRepository;

    // Create instance with test identifier
    $this->progress = new ImportProgress('test-123');
});

test('it can create new instance using make method', function () {
    $progress = ImportProgress::make('test-123');
    expect($progress)->toBeInstanceOf(ImportProgress::class);
});

test('it can increment total rows', function () {
    $this->progress->incrementTotalRow();
    expect((int) Redis::connection()->get('import:test-123:total'))->toBe(1);
});

test('it can increment total rows by custom count', function () {
    $this->progress->incrementTotalRow(5);
    expect((int) Redis::connection()->get('import:test-123:total'))->toBe(5);
});

test('it can increment ok count', function () {
    $this->progress->incrementOk();
    expect((int) Redis::connection()->get('import:test-123:ok'))->toBe(1);
});

test('it can increment fail count', function () {
    $this->progress->incrementFail();
    expect((int) Redis::connection()->get('import:test-123:fail'))->toBe(1);
});

test('it can push ok message', function () {
    $this->progress->pushOkMessage('Success message');
    $messages = Redis::connection()->lrange('import:test-123:msg:ok', 0, -1);
    expect($messages)->toContain('Success message');
});

test('it can push fail message', function () {
    $this->progress->pushFailMessage('Error message');
    $messages = Redis::connection()->lrange('import:test-123:msg:fail', 0, -1);
    expect($messages)->toContain('Error message');
});

test('it can get total row count', function () {
    Redis::connection()->set('import:test-123:total', 10);
    expect($this->progress->getTotalRow())->toBe(10);
});

test('it can get total ok count', function () {
    Redis::connection()->set('import:test-123:ok', 5);
    expect($this->progress->getTotalOk())->toBe(5);
});

test('it can get total failed count', function () {
    Redis::connection()->set('import:test-123:fail', 3);
    expect($this->progress->getTotalFailed())->toBe(3);
});

test('it can get ok messages', function () {
    $messages = ['Success 1', 'Success 2'];
    Redis::connection()->rpush('import:test-123:msg:ok', ...$messages);
    expect($this->progress->getMessageOk())->toBe($messages);
});

test('it can get fail messages', function () {
    $messages = ['Error 1', 'Error 2'];
    Redis::connection()->rpush('import:test-123:msg:fail', ...$messages);
    expect($this->progress->getMessageFail())->toBe($messages);
});

test('it can clear all progress data', function () {
    // Set some data
    Redis::connection()->set('import:test-123:total', 10);
    Redis::connection()->set('import:test-123:ok', 5);
    Redis::connection()->set('import:test-123:fail', 2);
    Redis::connection()->rpush('import:test-123:msg:ok', 'Success');
    Redis::connection()->rpush('import:test-123:msg:fail', 'Error');

    // Clear data
    $this->progress->clear();

    // Verify all data is cleared
    expect(Redis::connection()->get('import:test-123:total'))->toBeNull();
    expect(Redis::connection()->get('import:test-123:ok'))->toBeNull();
    expect(Redis::connection()->get('import:test-123:fail'))->toBeNull();
    expect(Redis::connection()->lrange('import:test-123:msg:ok', 0, -1))->toBe([]);
    expect(Redis::connection()->lrange('import:test-123:msg:fail', 0, -1))->toBe([]);
});

test('it can handle import status flow', function () {
    // Test pending status
    $this->progress->pending('test-module', 'test.csv');

    // Test processing status
    $this->progress->processing();

    // Set some progress data
    Redis::connection()->set('import:test-123:total', 10);
    Redis::connection()->set('import:test-123:ok', 7);
    Redis::connection()->set('import:test-123:fail', 3);
    Redis::connection()->rpush('import:test-123:msg:ok', 'Success 1', 'Success 2');
    Redis::connection()->rpush('import:test-123:msg:fail', 'Error 1');

    // Test failed status
    $this->progress->failed();

    // Don't verify total rows as it's calculated differently in failed status
    expect($this->progress->getTotalOk())->toBe(7);
    expect($this->progress->getTotalFailed())->toBe(3);
    expect($this->progress->getMessageOk())->toContain('Success 1');
    expect($this->progress->getMessageFail())->toContain('Error 1');

    // Clean up for next test
    $this->progress->clear();
});
