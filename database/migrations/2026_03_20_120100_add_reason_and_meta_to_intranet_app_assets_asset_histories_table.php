<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('intranet_app_assets_asset_histories', function (Blueprint $table): void {
            $table->text('reason')->nullable()->after('event');
            $table->json('meta')->nullable()->after('reason');
        });
    }

    public function down(): void
    {
        Schema::table('intranet_app_assets_asset_histories', function (Blueprint $table): void {
            $table->dropColumn(['reason', 'meta']);
        });
    }
};
