<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extensions', function (Blueprint $table) {
            $table->string('name')->primary();
            $table->string('type')->nullable();
            $table->boolean('enabled')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extensions');
    }
};
