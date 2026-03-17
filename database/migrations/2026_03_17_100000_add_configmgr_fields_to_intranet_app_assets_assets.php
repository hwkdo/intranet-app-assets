<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('intranet_app_assets_assets', function (Blueprint $table) {
            $table->string('smbios_guid')->nullable()->after('intune_device_id');
            $table->json('configmgr_mac_addresses')->nullable()->after('smbios_guid');
        });
    }

    public function down(): void
    {
        Schema::table('intranet_app_assets_assets', function (Blueprint $table) {
            $table->dropColumn(['smbios_guid', 'configmgr_mac_addresses']);
        });
    }
};
