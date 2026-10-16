<?php

use App\Models\Post;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_series', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->timestamps();
            $table->unique(['user_id', 'name']);
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->foreignId('series_id')->nullable()->after('user_id')->constrained('post_series')->nullOnDelete();
            $table->string('content_hash', 40)->nullable()->index()->after('content'); // to refuse the same text twice
        });

        // hash the posts that already exist
        Post::query()->where('content', '!=', '')->chunkById(200, function ($posts) {
            foreach ($posts as $post) {
                $post->timestamps = false;
                $post->saveQuietly(); // the saving hook fills content_hash
            }
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('series_id');
            $table->dropColumn('content_hash');
        });
        Schema::dropIfExists('post_series');
    }
};
