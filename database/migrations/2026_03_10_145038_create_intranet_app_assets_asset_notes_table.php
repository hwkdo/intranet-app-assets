<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intranet_app_assets_asset_notes', function (Blueprint $table) {
            $table->id();
            $table->longText('note');
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->morphs('noteable');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intranet_app_assets_asset_notes');
    }
};
