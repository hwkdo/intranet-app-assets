<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intranet_app_assets_handovers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->nullable()->constrained('intranet_app_assets_assets')->nullOnDelete();
            $table->foreignId('recipient_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('issuer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->longText('signature')->nullable();
            $table->string('file')->nullable();
            $table->string('formwerk_handover')->nullable();
            $table->string('formwerk_return')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intranet_app_assets_handovers');
    }
};
