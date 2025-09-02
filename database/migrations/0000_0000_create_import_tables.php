<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imports', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->string('module_name')->default('default');
            $table->string('filename')->nullable();
            $table->enum('status', ['pending', 'processing', 'done', 'failed', 'stuck'])->default('pending');
            $table->integer('total_rows')->default(0);
            $table->integer('success_rows')->default(0);
            $table->integer('failed_rows')->default(0);
            $table->json('success')->nullable();
            $table->json('errors')->nullable();
            $table->timestamps();

            $table->index(['module_name', 'status', 'created_at'], 'idx_imports');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imports');
    }
};