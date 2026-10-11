<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE media MODIFY type ENUM('image','video','audio','dataset') NOT NULL DEFAULT 'image'");
        }
    }

    public function down(): void
    {
        DB::table('media')->where('type', 'audio')->delete();

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE media MODIFY type ENUM('image','video','dataset') NOT NULL DEFAULT 'image'");
        }
    }
};
