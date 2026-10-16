<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('episodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('series', 50);
            $table->string('kind', 10)->default('video'); // video | text | image
            $table->text('prompt')->nullable(); // what was asked for (text and image jobs)
            $table->json('platform_ids')->nullable(); // where a text or image job is posted
            $table->string('day_key')->nullable(); // 'series-a:2026-10-15' for the daily video, so there is one a day; null for on-demand jobs
            $table->date('run_date');
            $table->string('topic')->nullable();
            $table->string('status', 30)->default('pending');
            $table->string('failed_step', 30)->nullable(); // status the episode was in when it failed (used by retry)
            $table->longText('script_ur')->nullable();
            $table->json('sources')->nullable();
            $table->string('voice_path')->nullable();
            $table->json('alignment')->nullable();
            $table->string('captions_path')->nullable();
            $table->string('avatar_path')->nullable();
            $table->json('outputs')->nullable(); // {long: media id, shorts: [media ids], thumbnail: media id, titles, descriptions}
            $table->json('post_ids')->nullable(); // posts created for this episode (used by "Cancel posting")
            $table->dateTime('scheduled_for')->nullable();
            $table->text('rejection_note')->nullable();
            $table->text('error')->nullable();
            $table->string('worker_job_id')->nullable();
            $table->timestamp('step_started_at')->nullable(); // when the current worker step began (watchdog)
            $table->timestamps();

            $table->unique(['user_id', 'day_key']);
        });

        // One row per step. Script rows also keep the text, so every draft, edit and instruction is kept as history.
        Schema::create('episode_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('episode_id')->constrained()->onDelete('cascade');
            $table->string('step', 40);
            $table->string('status', 10); // started|ok|failed
            $table->text('message')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('episode_logs');
        Schema::dropIfExists('episodes');
    }
};
