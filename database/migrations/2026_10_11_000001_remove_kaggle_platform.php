<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('platforms')->where('platform_type', 'kaggle')->delete();

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE platforms MODIFY platform_type ENUM('facebook','linkedin','youtube','tiktok') NOT NULL");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE platforms MODIFY platform_type ENUM('facebook','linkedin','youtube','tiktok','kaggle') NOT NULL");
        }
    }
};
