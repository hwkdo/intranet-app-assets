<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('intranet_app_assets_seventhings_mappings')) {
            return;
        }

        Schema::table('intranet_app_assets_seventhings_mappings', function (Blueprint $table) {
            $table->unique(['local_attribute', 'itexia_attribute'], 'intranet_assets_seventhings_mapping_unique');
        });
    }

    public function down(): void
    {
        Schema::table('intranet_app_assets_seventhings_mappings', function (Blueprint $table) {
            $table->dropUnique('intranet_assets_seventhings_mapping_unique');
        });
    }
};
