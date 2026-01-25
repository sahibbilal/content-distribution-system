<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('platforms')) {
            Schema::create('platforms', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->enum('platform_type', ['facebook', 'linkedin', 'youtube', 'tiktok', 'kaggle']);
                $table->text('credentials')->nullable(); // Encrypted JSON credentials
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['user_id', 'platform_type']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('platforms');
    }
};

