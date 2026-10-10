<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ai_integrations')) {
            Schema::create('ai_integrations', function (Blueprint $table) {
                $table->id();
                $table->string('provider')->unique();
                $table->string('model')->nullable();
                $table->text('api_key')->nullable(); // encrypted
                $table->boolean('enabled')->default(false);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_integrations');
    }
};
