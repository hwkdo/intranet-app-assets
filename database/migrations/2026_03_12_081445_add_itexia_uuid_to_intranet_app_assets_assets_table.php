<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('intranet_app_assets_assets', function (Blueprint $table) {
            $table->string('itexia_uuid')->nullable()->after('itexia_id');
        });
    }

    public function down(): void
    {
        Schema::table('intranet_app_assets_assets', function (Blueprint $table) {
            $table->dropColumn('itexia_uuid');
        });
    }
};
