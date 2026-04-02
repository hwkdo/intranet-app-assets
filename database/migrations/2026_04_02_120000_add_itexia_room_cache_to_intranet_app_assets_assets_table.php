<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('intranet_app_assets_assets', function (Blueprint $table): void {
            $table->unsignedBigInteger('itexia_actual_room_id')->nullable()->after('itexia_check_at');
            $table->unsignedBigInteger('itexia_target_room_id')->nullable()->after('itexia_actual_room_id');
            $table->timestamp('itexia_rooms_synced_at')->nullable()->after('itexia_target_room_id');
        });
    }

    public function down(): void
    {
        Schema::table('intranet_app_assets_assets', function (Blueprint $table): void {
            $table->dropColumn([
                'itexia_actual_room_id',
                'itexia_target_room_id',
                'itexia_rooms_synced_at',
            ]);
        });
    }
};
