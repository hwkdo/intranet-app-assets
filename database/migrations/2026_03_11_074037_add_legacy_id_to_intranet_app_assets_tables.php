<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'intranet_app_assets_asset_types',
            'intranet_app_assets_asset_vendors',
            'intranet_app_assets_assets',
            'intranet_app_assets_handovers',
            'intranet_app_assets_returns',
            'intranet_app_assets_asset_attachments',
            'intranet_app_assets_asset_notes',
        ];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedBigInteger('legacy_id')->nullable()->unique()->after('id');
            });
        }
    }

    public function down(): void
    {
        $tables = [
            'intranet_app_assets_asset_types',
            'intranet_app_assets_asset_vendors',
            'intranet_app_assets_assets',
            'intranet_app_assets_handovers',
            'intranet_app_assets_returns',
            'intranet_app_assets_asset_attachments',
            'intranet_app_assets_asset_notes',
        ];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('legacy_id');
            });
        }
    }
};
