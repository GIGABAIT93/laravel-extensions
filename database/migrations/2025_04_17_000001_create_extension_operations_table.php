<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extension_operations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type', 50)->index();
            $table->string('extension_id')->index();
            $table->string('status', 30)->index();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->text('message')->nullable();
            $table->json('context')->nullable();
            $table->json('result')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['extension_id', 'type', 'status'], 'ext_ops_ext_type_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extension_operations');
    }
};

