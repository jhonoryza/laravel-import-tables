<?php

namespace Jhonoryza\LaravelImportTables\Models;

use Illuminate\Database\Eloquent\Model;

class Import extends Model
{
    const PENDING = 'pending';

    const PROCESSING = 'processing';

    const DONE = 'done';

    const FAILED = 'failed';

    const STUCK = 'stuck';

    protected $fillable = [
        'key',
        'module_name',
        'filename',
        'status',
        'total_rows',
        'success_rows',
        'failed_rows',
        'success',
        'errors',
    ];

    protected $casts = [
        'success' => 'array',
        'errors' => 'array',
    ];
}
