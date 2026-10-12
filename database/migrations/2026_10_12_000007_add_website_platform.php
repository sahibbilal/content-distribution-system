<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE platforms MODIFY platform_type ENUM('facebook','linkedin','youtube','tiktok','twitter','instagram','website') NOT NULL");
        }
    }

    public function down(): void
    {
        DB::table('platforms')->where('platform_type', 'website')->delete();

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE platforms MODIFY platform_type ENUM('facebook','linkedin','youtube','tiktok','twitter','instagram') NOT NULL");
        }
    }
};
