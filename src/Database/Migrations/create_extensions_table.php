<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateExtensionsTable extends Migration
{
    public function up(): void
    {
        Schema::create(config('extensions.table', 'extensions'), function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('active')->default(false);
            $table->string('type')->default('module');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('extensions.table', 'extensions'));
    }
}
