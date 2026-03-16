<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('intranet_app_assets_assets', function (Blueprint $table) {
            $table->timestamp('itexia_check_at')->nullable()->after('itexia_uuid');
        });
    }

    public function down(): void
    {
        Schema::table('intranet_app_assets_assets', function (Blueprint $table) {
            $table->dropColumn('itexia_check_at');
        });
    }
};
