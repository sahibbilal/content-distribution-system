<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The editable rulebook: one row per user and section. A missing row means "use the starter text".
        Schema::create('ai_instructions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('section', 30);
            $table->longText('body');
            $table->timestamps();

            $table->unique(['user_id', 'section']);
        });

        // A workspace: its own instructions, defaults and memory (Trends, Jokes, ...)
        Schema::create('ai_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name', 100);
            $table->string('type', 20)->default('custom'); // trends | jokes | custom (pdf and video come next)
            $table->longText('instructions')->nullable(); // what this project creates
            $table->json('platform_ids')->nullable(); // default channels
            $table->json('options')->nullable(); // {web_search, hashtags, make_image} defaults
            $table->timestamps();
        });

        // Memory: everything the assistant wrote, so it does not repeat itself
        Schema::create('ai_generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('project_id')->nullable()->constrained('ai_projects')->nullOnDelete();
            $table->text('prompt');
            $table->longText('long');
            $table->text('short')->nullable();
            $table->json('hashtags')->nullable();
            $table->string('hook', 255); // first line, shown in the memory list and given back to the AI
            $table->foreignId('post_id')->nullable()->constrained('posts')->nullOnDelete(); // set once a post was made from it
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_generations');
        Schema::dropIfExists('ai_projects');
        Schema::dropIfExists('ai_instructions');
    }
};
