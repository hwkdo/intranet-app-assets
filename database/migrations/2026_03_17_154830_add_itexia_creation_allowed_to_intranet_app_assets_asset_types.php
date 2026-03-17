<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('intranet_app_assets_asset_types', function (Blueprint $table) {
            $table->boolean('itexia_creation_allowed')->default(false)->after('is_intune_object');
        });
    }

    public function down(): void
    {
        Schema::table('intranet_app_assets_asset_types', function (Blueprint $table) {
            $table->dropColumn('itexia_creation_allowed');
        });
    }
};
