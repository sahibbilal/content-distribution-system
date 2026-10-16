<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->json('hashtags')->nullable()->after('tags'); // shared hashtags, added to the end of the text on social platforms
            $table->json('platform_overrides')->nullable()->after('hashtags'); // {platformId: {content?, hashtags?}}: a different text or hashtags for one channel
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn(['hashtags', 'platform_overrides']);
        });
    }
};
