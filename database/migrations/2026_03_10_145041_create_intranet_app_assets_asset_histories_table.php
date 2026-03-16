<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intranet_app_assets_asset_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('intranet_app_assets_assets')->cascadeOnDelete();
            $table->string('event');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intranet_app_assets_asset_histories');
    }
};
